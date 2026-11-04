<?php

namespace CQL\Parser\Nodes;

readonly class AssignmentNode
{
    /**
     * Create a new AssignmentNode instance.
     *
     * @param string $column Target column name (may be alias-qualified, e.g. "users.age").
     * @param mixed $expression Value expression (literal, column reference, or ExpressionNode).
     */
    public function __construct(
        public readonly string $column,
        public readonly mixed $expression
    ) {
    }
}
