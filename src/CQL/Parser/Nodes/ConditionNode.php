<?php

namespace CQL\Parser\Nodes;

class ConditionNode
{
    /**
     * Create a new ConditionNode instance.
     *
     * @param mixed $left
     * @param string $operator
     * @param mixed $right
     */
    public function __construct(
        public readonly mixed $left,
        public readonly string $operator,
        public readonly mixed $right
    ) {
    }
}