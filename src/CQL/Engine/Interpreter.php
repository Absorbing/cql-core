<?php

namespace CQL\Engine;

use CQL\Data\Support\Collection;
use CQL\Data\CSVDataSource;
use CQL\Engine\Operators\Contracts\OperatorInterface;
use CQL\Parser\Nodes\QueryNode;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\ExpressionNode;

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
        $source = new CSVDataSource(
            $query->define->path,
            $query->define->hasHeaders
        );

        $source->load();
        $this->collection = new Collection($source->getRows());
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

        $this->collection = $this->collection->map(
            function ($row) use ($columns) {
                return array_intersect_key($row, array_flip($columns));
            }
        );
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
        if ($operand instanceof \CQL\Parser\Nodes\ExpressionNode) {
            $left = $this->evaluateOperand($operand->left, $row);
            $right = $this->evaluateOperand($operand->right, $row);
            $operator = \CQL\Engine\Operators\Registry\OperatorRegistry::resolve($operand->operator);

            return $operator::evaluate($left, $right);
        }

        if (is_string($operand)) {
            if (is_numeric($operand)) {
                return $operand + 0;
            }

            return $row[$operand] ?? null;
        }

        return $operand;
    }
}