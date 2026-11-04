<?php

namespace CQL\Parser\Nodes;

/**
 * Represents a function call in the query.
 */
readonly class FunctionNode
{
    /**
     * Create a new FunctionNode instance.
     *
     * @param string $name Function name (COUNT, SUM, AVG, MIN, MAX, etc.).
     * @param mixed $argument Function argument (column name, expression, or *).
     * @param string|null $alias Optional alias for the result.
     */
    public function __construct(
        public string $name,
        public mixed $argument,
        public ?string $alias = null
    ) {
    }
}
