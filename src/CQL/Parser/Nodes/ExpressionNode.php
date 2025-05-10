<?php

namespace CQL\Parser\Nodes;

readonly class ExpressionNode
{
    /**
     * Create a new expression node instance.
     *
     * @param mixed $left
     * @param string $operator
     * @param mixed $right
     */
    public function __construct(
        public mixed $left,
        public string $operator,
        public mixed $right
    ) {
    }
}