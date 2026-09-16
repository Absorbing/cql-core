<?php

namespace CQL\Parser\Nodes;

readonly class UnaryConditionNode
{
    /**
     * Create a new UnaryConditionNode instance.
     *
     * For NOT the operand is a nested condition (ConditionNode,
     * UnaryConditionNode, or a bare expression evaluated for truthiness).
     * For EXISTS the operand is an expression whose value is tested for
     * presence (non-null, non-empty).
     *
     * @param string $operator 'NOT' or 'EXISTS'
     * @param mixed $operand
     */
    public function __construct(
        public readonly string $operator,
        public readonly mixed $operand
    ) {
    }
}
