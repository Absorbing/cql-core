<?php

namespace CQL\Engine;

use CQL\Data\Support\Collection;
use CQL\Data\CSVDataSource;
use CQL\Engine\Operators\Contracts\OperatorInterface;
use CQL\Parser\Nodes\QueryNode;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Exceptions\InterpreterException;

class Interpreter
{
    /**
     * @var QueryNode
     */
    protected QueryNode $query;

    /**
     * @var Collection
     */
    protected Collection $collection;

    public function __construct(QueryNode $query)
    {
        $this->query = $query;

        $source = new CSVDataSource(
            $query->define->path,
            $query->define->hasHeaders
        );

        $source->load();
        $this->collection = new Collection($source->getRows());
    }

    public function execute(): Collection
    {
        if ($this->query->where !== null) {
            $this->applyWhere();
        }

        $this->applySelect();
        return $this->collection;
    }

    protected function applyWhere(): void
    {
        $condition = $this->query->where->condition;
        $operator = OperatorRegistry::resolve($condition->operator);

        if (!$operator instanceof OperatorInterface) {
            throw new InterpreterException("Unsupported operator ({$condition->operator}) in WHERE clause.");
        }

        $this->collection = $this->collection->filter(
            function ($row) use ($condition, $operator) {
                return $operator::evaluate($row[$condition->left] ?? null, $condition->right);
            }
        );
    }

    protected function applySelect(): void
    {
        $columns = $this->query->select->columns;

        $this->collection = $this->collection->map(
            function ($row) use ($columns) {
                return array_intersect_key($row, array_flip($columns));
            }
        );
    }
}