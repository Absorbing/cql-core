<?php

namespace CQL\Engine;

use CQL\Data\Support\Collection;
use CQL\Engine\Concerns\EvaluatesExpressions;
use CQL\Data\CSVDataSource;
use CQL\Parser\Nodes\QueryNode;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Parser\Nodes\WildcardNode;

class Interpreter
{
    use EvaluatesExpressions;

    /**
     * @var Collection<array-key, mixed>
     */
    protected Collection $collection;

    /**
     * @var bool
     */
    protected bool $streaming = false;

    /**
     * @var int|null Threshold in bytes for automatic streaming mode (null = disabled)
     */
    protected ?int $autoStreamingThreshold = null;

    /**
     * Create a new Interpreter instance.
     *
     * @param QueryNode $query
     * @param bool|null $streaming Enable streaming mode (true/false), or null for automatic based on file size
     * @param int $autoStreamingThreshold File size threshold in bytes for automatic streaming (default: 50MB)
     */
    public function __construct(
        protected QueryNode $query,
        ?bool $streaming = null,
        int $autoStreamingThreshold = 52428800  // 50 MB
    ) {
        $this->autoStreamingThreshold = $autoStreamingThreshold;

        $defineMap = [];
        foreach ($query->defines as $define) {
            $defineMap[$define->alias] = $define;
        }

        $fromAlias = $query->from->table;

        if (!isset($defineMap[$fromAlias])) {
            throw new InterpreterException("Undefined data source alias '{$fromAlias}'");
        }

        $define = $defineMap[$fromAlias];

        // Determine streaming mode
        if ($streaming === null) {
            // Automatic mode: check file size
            $cleanPath = \CQL\Parser\Nodes\LiteralNode::decode($define->path);
            $fileSize = file_exists($cleanPath) ? filesize($cleanPath) : 0;
            $this->streaming = $fileSize !== false && $fileSize > $this->autoStreamingThreshold;
        } else {
            // Explicit mode
            $this->streaming = $streaming;
        }

        $source = new CSVDataSource(
            $define->path,
            $define->hasHeaders,
            $define->delimiter ?? ',',
            $define->alias,
            $this->streaming
        );

        $source->load();
        $this->collection = new Collection($source->getRows());

        foreach ($query->from->joins as $join) {
            if (!isset($defineMap[$join->rightAlias])) {
                throw new InterpreterException("JOIN target '{$join->rightAlias}' is not defined.");
            }

            $rightDefine = $defineMap[$join->rightAlias];
            
            // Use same streaming mode for joined tables
            $rightSource = new CSVDataSource(
                $rightDefine->path,
                $rightDefine->hasHeaders,
                $rightDefine->delimiter ?? ',',
                $rightDefine->alias,
                $this->streaming
            );

            $rightSource->load();
            $rightRows = $rightSource->getRows();

            $rightIndex = [];
            foreach ($rightRows as $row) {
                $key = $row["{$join->rightAlias}.{$join->rightKey}"] ?? $row[$join->rightKey] ?? null;
                if ($key !== null) {
                    $rightIndex[$key][] = $row;
                }
            }

            $this->collection = $this->collection->flatMap(function ($leftRow) use ($join, $rightIndex) {
                $leftKey = $leftRow["{$join->leftAlias}.{$join->leftKey}"] ?? $leftRow[$join->leftKey] ?? null;

                if (!isset($rightIndex[$leftKey])) {
                    return [];
                }

                return array_map(
                    fn($rightRow) => array_merge($leftRow, $rightRow),
                    $rightIndex[$leftKey]
                );
            });
        }

    }

    /**
     * Execute the query.
     *
     * @return Collection<array-key, mixed>
     */
    public function execute(): Collection
    {
        if ($this->query->where !== null) {
            $this->applyWhere();
        }

        if ($this->query->groupBy !== null) {
            $this->applyGroupBy();
        }

        $this->applySelect();
        return $this->collection;
    }

    /**
     * Apply the WHERE clause.
     *
     * @return void
     */
    protected function applyWhere(): void
    {
        if (!$this->query->where) {
            return;
        }

        $condition = $this->query->where->condition;

        $this->collection = $this->collection->filter(
            fn($row): bool => $this->evaluateCondition($condition, $row)
        );
    }

    /**
     * Apply the SELECT clause
     *
     * @return void
     */
    protected function applySelect(): void
    {
        $columns = $this->query->select->columns;
        
        // Check if we have aggregate functions
        $hasAggregates = false;
        foreach ($columns as $column) {
            if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                $funcName = strtoupper($column->name);
                if (in_array($funcName, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'], true)) {
                    $hasAggregates = true;
                    break;
                }
            }
        }

        // If we have aggregates but no GROUP BY, aggregate the entire dataset
        if ($hasAggregates && $this->query->groupBy === null) {
            $this->applyAggregatesWithoutGroupBy();
            return;
        }

        // Check if we have date functions without aggregates
        $hasDateFunctions = false;
        foreach ($columns as $column) {
            if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                $funcName = strtoupper($column->name);
                if (in_array($funcName, ['YEAR', 'MONTH', 'DAY', 'DATE'], true)) {
                    $hasDateFunctions = true;
                    break;
                }
            }
        }

        // If we have date functions, evaluate them row by row
        if ($hasDateFunctions && !$hasAggregates) {
            $this->collection = $this->collection->map(function ($row) use ($columns) {
                $result = [];
                
                foreach ($columns as $column) {
                    if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                        $value = $this->resolveColumnValue($column->argument, $row);
                        $funcResult = $this->evaluateDateFunction(strtoupper($column->name), $value);
                        $outputKey = $column->alias ?? strtolower($column->name);
                        $result[$outputKey] = $funcResult;
                    } elseif (is_string($column)) {
                        // Regular column
                        $value = $this->resolveColumnValue($column, $row);
                        $result[$column] = $value;
                    }
                }
                
                return $result;
            });
            return;
        }

        // Regular column selection
        $keyMap = [];

        $allKeys = [];
        foreach ($this->collection as $row) {
            foreach (array_keys($row) as $key) {
                $allKeys[$key] = true;
            }
        }
        $allKeys = array_keys($allKeys);

        foreach ($columns as $column) {
            if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                // Function results are already in the row from GROUP BY
                $outputKey = $column->alias ?? strtolower($column->name) . '_' . ($column->argument === '*' ? 'all' : $column->argument);
                $keyMap[] = [$outputKey, $outputKey];
                continue;
            }

            if ($column instanceof \CQL\Parser\Nodes\AliasedColumnNode) {
                foreach ($allKeys as $fullKey) {
                    if ($fullKey === $column->expression) {
                        $keyMap[] = [$fullKey, $column->alias];
                    } elseif (str_ends_with($fullKey, ".{$column->expression}")) {
                        $keyMap[] = [$fullKey, $column->alias];
                    }
                }
            }

            if (is_string($column)) {
                foreach ($allKeys as $fullKey) {
                    if ($fullKey === $column) {
                        $keyMap[] = [$fullKey, $fullKey];
                    } elseif (str_ends_with($fullKey, ".$column")) {
                        $keyMap[] = [$fullKey, $column];
                    }
                }
            }

            if ($column instanceof WildcardNode) {
                foreach ($allKeys as $fullKey) {
                    if ($column->prefix === null) {
                        $shortKey = substr($fullKey, strrpos($fullKey, '.') + 1);

                        $count = 0;
                        foreach ($allKeys as $otherKey) {
                            if (str_ends_with($otherKey, ".$shortKey")) {
                                $count++;
                            }
                        }

                        if ($count > 1) {
                            $keyMap[] = [$fullKey, $fullKey];
                        } else {
                            $keyMap[] = [$fullKey, $shortKey];
                        }
                    } elseif (str_starts_with($fullKey, "{$column->prefix}.")) {
                        $keyMap[] = [$fullKey, $fullKey];
                    }
                }
            }
        }

        if (empty($keyMap)) {
            return;
        }

        $this->collection = $this->collection->map(function ($row) use ($keyMap) {
            $mapped = [];
            foreach ($keyMap as [$original, $output]) {
                if (array_key_exists($original, $row)) {
                    $mapped[$output] = $row[$original];
                }
            }
            return $mapped;
        });
    }

    /**
     * Check if streaming mode is enabled.
     *
     * @return bool
     */
    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * Get the auto-streaming threshold in bytes.
     *
     * @return int|null
     */
    public function getAutoStreamingThreshold(): ?int
    {
        return $this->autoStreamingThreshold;
    }

    /**
     * Apply GROUP BY clause.
     *
     * @return void
     */
    protected function applyGroupBy(): void
    {
        $groupByColumns = $this->query->groupBy->columns;
        $groups = [];

        // Group rows by the specified columns
        foreach ($this->collection as $row) {
            $groupKey = [];
            foreach ($groupByColumns as $column) {
                if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                    // Evaluate function for grouping
                    $colValue = $this->resolveColumnValue($column->argument, $row);
                    $value = $this->evaluateDateFunction(strtoupper($column->name), $colValue);
                } else {
                    $value = $this->resolveColumnValue($column, $row);
                }
                
                $groupKey[] = $value;
            }
            $groupKeyStr = json_encode($groupKey);

            if (!isset($groups[$groupKeyStr])) {
                $groups[$groupKeyStr] = [
                    'key' => $groupKey,
                    'rows' => []
                ];
            }

            $groups[$groupKeyStr]['rows'][] = $row;
        }

        // Apply aggregates to each group
        $aggregatedRows = [];
        foreach ($groups as $group) {
            $aggregatedRow = [];

            // Add group by columns to result
            foreach ($groupByColumns as $i => $column) {
                if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                    $outputKey = $column->alias ?? strtolower($column->name);
                    $aggregatedRow[$outputKey] = $group['key'][$i];
                } else {
                    $shortColumn = $this->getShortColumnName($column);
                    $aggregatedRow[$shortColumn] = $group['key'][$i];
                }
            }

            // Calculate aggregates
            foreach ($this->query->select->columns as $column) {
                if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                    // Check if this function is already in GROUP BY
                    $isGroupByFunc = false;
                    foreach ($groupByColumns as $gbCol) {
                        if ($gbCol instanceof \CQL\Parser\Nodes\FunctionNode &&
                            strtoupper($gbCol->name) === strtoupper($column->name) &&
                            $gbCol->argument === $column->argument) {
                            $isGroupByFunc = true;
                            break;
                        }
                    }
                    
                    if (!$isGroupByFunc) {
                        $result = $this->evaluateAggregate($column, $group['rows']);
                        $outputKey = $column->alias ?? strtolower($column->name) . '_' . ($column->argument === '*' ? 'all' : $column->argument);
                        $aggregatedRow[$outputKey] = $result;
                    }
                }
            }

            $aggregatedRows[] = $aggregatedRow;
        }

        $this->collection = new Collection($aggregatedRows);
    }

    /**
     * Apply aggregates without GROUP BY (aggregate entire dataset).
     *
     * @return void
     */
    protected function applyAggregatesWithoutGroupBy(): void
    {
        $allRows = $this->collection->toArray();
        $result = [];

        foreach ($this->query->select->columns as $column) {
            if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                $value = $this->evaluateAggregate($column, $allRows);
                $outputKey = $column->alias ?? strtolower($column->name) . '_' . ($column->argument === '*' ? 'all' : $column->argument);
                $result[$outputKey] = $value;
            }
        }

        $this->collection = new Collection([$result]);
    }

    /**
     * Evaluate an aggregate function.
     *
     * @param \CQL\Parser\Nodes\FunctionNode $function
     * @param array<array<string, mixed>> $rows
     * @return mixed
     */
    protected function evaluateAggregate(\CQL\Parser\Nodes\FunctionNode $function, array $rows): mixed
    {
        $functionName = strtoupper($function->name);

        switch ($functionName) {
            case 'COUNT':
                if ($function->argument === '*') {
                    return count($rows);
                }
                // Count non-null values
                $count = 0;
                foreach ($rows as $row) {
                    $value = $this->resolveColumnValue($function->argument, $row);
                    if ($value !== null) {
                        $count++;
                    }
                }
                return $count;

            case 'SUM':
                $sum = 0;
                foreach ($rows as $row) {
                    $value = $this->resolveColumnValue($function->argument, $row);
                    if (is_numeric($value)) {
                        $sum += (float)$value;
                    }
                }
                return $sum;

            case 'AVG':
                $sum = 0;
                $count = 0;
                foreach ($rows as $row) {
                    $value = $this->resolveColumnValue($function->argument, $row);
                    if (is_numeric($value)) {
                        $sum += (float)$value;
                        $count++;
                    }
                }
                return $count > 0 ? $sum / $count : null;

            case 'MIN':
                $min = null;
                foreach ($rows as $row) {
                    $value = $this->resolveColumnValue($function->argument, $row);
                    if ($value !== null && (is_numeric($value) || is_string($value))) {
                        if ($min === null || $value < $min) {
                            $min = $value;
                        }
                    }
                }
                return $min;

            case 'MAX':
                $max = null;
                foreach ($rows as $row) {
                    $value = $this->resolveColumnValue($function->argument, $row);
                    if ($value !== null && (is_numeric($value) || is_string($value))) {
                        if ($max === null || $value > $max) {
                            $max = $value;
                        }
                    }
                }
                return $max;

            case 'YEAR':
            case 'MONTH':
            case 'DAY':
                // Date functions on first row (typically used with GROUP BY)
                if (empty($rows)) {
                    return null;
                }
                $value = $this->resolveColumnValue($function->argument, $rows[0]);
                return $this->evaluateDateFunction($functionName, $value);

            default:
                throw new InterpreterException("Unknown aggregate function: {$functionName}");
        }
    }

}