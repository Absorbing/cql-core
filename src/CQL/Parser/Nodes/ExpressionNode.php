<?php

namespace CQL\Parser\Nodes;

class ExpressionNode
{
    public function __construct(
        public mixed $left,
        public string $operator,
        public mixed $right
    ) {
    }
}