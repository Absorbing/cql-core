<?php

namespace CQL\Engine;

use CQL\Data\Support\Collection;
use CQL\Data\CSVDataSource;
use CQL\Engine\Operators\Contracts\OperatorInterface;
use CQL\Parser\Nodes\QueryNode;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Parser\Nodes\WildcardNode;

class Interpreter
{
    /**
     * @var Collection<array-key, mixed>
     */
    protected Collection $collection;

    /**
     * Create a new Interpreter instance.
     *
     * @param QueryNode $query
     */
    public function __construct(
        protected QueryNode $query
    ) {

        $defineMap = [];
        foreach ($query->defines as $define) {
            $defineMap[$define->alias] = $define;
        }

        $fromAlias = $query->from->table;

        if (!isset($defineMap[$fromAlias])) {
            throw new InterpreterException("Undefined data source alias '{$fromAlias}'");
        }

        $define = $defineMap[$fromAlias];

        $source = new CSVDataSource(
            $define->path,
            $define->hasHeaders,
            $define->delimiter ?? ',',
            $define->alias
        );

        $source->load();
        $this->collection = new Collection($source->getRows());

        foreach ($query->from->joins as $join) {
            if (!isset($defineMap[$join->rightAlias])) {
                throw new InterpreterException("JOIN target '{$join->rightAlias}' is not defined.");
            }

            $rightDefine = $defineMap[$join->rightAlias];
            $rightSource = new CSVDataSource(
                $rightDefine->path,
                $rightDefine->hasHeaders,
                $rightDefine->delimiter ?? ',',
                $rightDefine->alias
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
        $operator = OperatorRegistry::resolve($condition->operator);

        $this->collection = $this->collection->filter(function ($row) use ($condition, $operator): bool {
            $left = $this->evaluateOperand($condition->left, $row);
            $right = $this->evaluateOperand($condition->right, $row);
            return (bool)$operator::evaluate($left, $right);
        });
    }

    /**
     * Apply the SELECT clause
     *
     * @return void
     */
    protected function applySelect(): void
    {
        $columns = $this->query->select->columns;
        $keyMap = [];

        $allKeys = [];
        foreach ($this->collection as $row) {
            foreach (array_keys($row) as $key) {
                $allKeys[$key] = true;
            }
        }
        $allKeys = array_keys($allKeys);

        foreach ($columns as $column) {
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
     * Evaluate an operand.
     *
     * @param mixed $operand
     * @param array<string, mixed> $row
     * @return mixed
     */
    protected function evaluateOperand(mixed $operand, array $row): mixed
    {
        if ($operand instanceof ExpressionNode) {
            $left = $this->evaluateOperand($operand->left, $row);
            $right = $this->evaluateOperand($operand->right, $row);
            $operator = OperatorRegistry::resolve($operand->operator);

            return $operator::evaluate($left, $right);
        }

        if (is_string($operand)) {
            if (is_numeric($operand)) {
                return $operand + 0;
            }

            if (isset($row[$operand])) {
                return $row[$operand];
            }

            if (!str_contains($operand, '.')) {
                foreach ($row as $key => $value) {
                    if (str_ends_with($key, ".$operand")) {
                        return $value; // ambiguous fallback
                    }
                }
            }

            return null;
        }

        return $operand;
    }
}