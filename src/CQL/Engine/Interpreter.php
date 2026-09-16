<?php

namespace CQL\Engine;

use CQL\Data\Support\Collection;
use CQL\Engine\Concerns\EvaluatesExpressions;
use CQL\Data\CSVDataSource;
use CQL\Data\SourceRegistry;
use CQL\ResultColumn;
use CQL\Parser\Nodes\QueryNode;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Parser\Nodes\WildcardNode;

class Interpreter
{
    /** @var array<string> */
    protected array $sourceColumns = [];

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
     * @param array<string|int, string|int|float|bool|null> $parameters Bound values.
     * @param int $autoStreamingThreshold File size threshold in bytes for automatic streaming (default: 50MB)
     */
    public function __construct(
        protected QueryNode $query,
        ?bool $streaming = null,
        int $autoStreamingThreshold = 52428800,
        array $parameters = [],
        ?SourceRegistry $sources = null,
    ) {
        $this->parameters = $parameters;
        $this->autoStreamingThreshold = $autoStreamingThreshold;

        $sources ??= new SourceRegistry();
        $source = $sources->resolve($query->from->table, $query->defines, $streaming, $autoStreamingThreshold);
        $this->streaming = $source->isStreaming();

        $source->load();
        foreach ($source->getHeaders() ?? [] as $column) {
            $this->sourceColumns[] = $query->from->table . '.' . $column;
        }
        $this->collection = new Collection($source->getRows());

        foreach ($query->from->joins as $join) {
            $rightSource = $sources->resolve($join->rightAlias, $query->defines, $streaming, $autoStreamingThreshold);

            $rightSource->load();
            foreach ($rightSource->getHeaders() ?? [] as $column) {
                $this->sourceColumns[] = $join->rightAlias . '.' . $column;
            }
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
     * Describe projected columns from source schemas, including empty results.
     * @return array<ResultColumn>
     */
    public function getColumns(): array
    {
        $columns = [];
        $hasAggregates = false;
        foreach ($this->query->select->columns as $column) {
            if ($column instanceof \CQL\Parser\Nodes\FunctionNode && in_array(strtoupper($column->name), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'], true)) {
                $hasAggregates = true;
            }
        }
        foreach ($this->query->select->columns as $column) {
            if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                $groupFunction = false;
                foreach ($this->query->groupBy->columns ?? [] as $group) {
                    if ($group instanceof \CQL\Parser\Nodes\FunctionNode && $group->name === $column->name && $group->argument == $column->argument) {
                        $groupFunction = true;
                    }
                }
                $default = strtolower($column->name);
                if (!$groupFunction && ($hasAggregates || $this->query->groupBy !== null)) {
                    $default .= '_' . ($column->argument === '*' ? 'all' : $column->argument);
                }
                $columns[] = new ResultColumn($column->alias ?? $default);
                continue;
            }
            if ($hasAggregates && $this->query->groupBy === null) {
                continue;
            }
            if ($column instanceof WildcardNode) {
                foreach ($this->sourceColumns as $key) {
                    if ($column->prefix !== null && !str_starts_with($key, $column->prefix . '.')) {
                        continue;
                    }
                    $short = $this->getShortColumnName($key);
                    $matches = array_filter($this->sourceColumns, fn($candidate) => str_ends_with($candidate, '.' . $short));
                    $name = $column->prefix !== null || count($matches) > 1 ? $key : $short;
                    [$alias, $field] = explode('.', $key, 2);
                    $columns[] = new ResultColumn($name, $alias, $field);
                }
                continue;
            }
            $reference = $column instanceof \CQL\Parser\Nodes\AliasedColumnNode ? $column->expression : $column;
            $name = $column instanceof \CQL\Parser\Nodes\AliasedColumnNode ? $column->alias : $reference;
            $matches = array_values(array_filter($this->sourceColumns, fn($key) => $key === $reference || (!str_contains($reference, '.') && str_ends_with($key, '.' . $reference))));
            if (count($matches) > 1) {
                throw new InterpreterException("Ambiguous result column '{$reference}'");
            }
            if ($matches === [] && $this->sourceColumns !== []) {
                throw new InterpreterException("Unknown result column '{$reference}'");
            }
            [$alias, $field] = $matches === [] ? [null, null] : explode('.', $matches[0], 2);
            $columns[] = new ResultColumn($name, $alias, $field);
        }
        $names = array_column($columns, 'name');
        if (count(array_unique($names)) !== count($names)) {
            throw new InterpreterException('Duplicate result column names; use unique AS aliases');
        }
        return $columns;
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
        if ($this->query->groupBy === null) {
            foreach ($this->query->select->columns as $column) {
                if ($column instanceof \CQL\Parser\Nodes\FunctionNode && in_array(strtoupper($column->name), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'], true)) {
                    $this->applyAggregatesWithoutGroupBy();
                    return;
                }
            }
        }
        $this->collection = $this->collection->map(fn(array $row): array => $this->projectRow($row));
    }

    /**
     * Project one row using the same rules for buffered and cursor results.
     * @param array<string, mixed> $row Input row.
     * @return array<string, mixed>
     */
    protected function projectRow(array $row): array
    {
        $result = [];
        foreach ($this->query->select->columns as $column) {
            if ($column instanceof \CQL\Parser\Nodes\FunctionNode) {
                if ($this->query->groupBy !== null) {
                    $name = $column->alias ?? strtolower($column->name) . '_' . ($column->argument === '*' ? 'all' : $column->argument);
                    foreach ($this->query->groupBy->columns as $group) {
                        if ($group instanceof \CQL\Parser\Nodes\FunctionNode && $group->name === $column->name && $group->argument == $column->argument) {
                            $name = $column->alias ?? strtolower($column->name);
                        }
                    }
                    $result[$name] = $row[$name] ?? null;
                } else {
                    $name = $column->alias ?? strtolower($column->name);
                    $result[$name] = $this->evaluateDateFunction(strtoupper($column->name), $this->resolveColumnValue($column->argument, $row));
                }
                continue;
            }
            if ($column instanceof WildcardNode) {
                foreach ($row as $key => $value) {
                    if ($column->prefix !== null && !str_starts_with($key, $column->prefix . '.')) {
                        continue;
                    }
                    $short = $this->getShortColumnName($key);
                    $matches = array_filter($this->sourceColumns, fn($candidate) => str_ends_with($candidate, '.' . $short));
                    $result[$column->prefix !== null || count($matches) > 1 ? $key : $short] = $value;
                }
                continue;
            }
            $reference = $column instanceof \CQL\Parser\Nodes\AliasedColumnNode ? $column->expression : $column;
            $name = $column instanceof \CQL\Parser\Nodes\AliasedColumnNode ? $column->alias : $reference;
            $lookup = $this->query->groupBy !== null ? $this->getShortColumnName($reference) : $reference;
            $result[$name] = $this->resolveColumnValue($lookup, $row);
        }
        return $result;
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