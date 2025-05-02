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
        if ($this->query->where === null) {
            return;
        }

        $condition = $this->query->where->condition;
        $operator = OperatorRegistry::resolve($condition->operator);

        $this->collection = $this->collection->filter(
            function ($row) use ($condition, $operator) {
                return $operator::evaluate($row[$condition->left] ?? null, $condition->right) > 0;
            }
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

        $this->collection = $this->collection->map(
            function ($row) use ($columns) {
                return array_intersect_key($row, array_flip($columns));
            }
        );
    }
}