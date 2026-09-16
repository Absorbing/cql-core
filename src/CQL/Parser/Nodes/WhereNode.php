<?php

namespace CQL\Parser\Nodes;

readonly class WhereNode
{
    /**
     * Create a new WhereNode instance.
     *
     * The condition is the root of a condition tree:
     * - ConditionNode: a comparison (left op right), or a logical
     *   combination where operator is AND/OR and left/right are themselves
     *   condition nodes
     * - UnaryConditionNode: NOT (negated condition) or EXISTS (presence test)
     * - string|ExpressionNode: a bare expression evaluated for truthiness
     *
     * @param ConditionNode|UnaryConditionNode|ExpressionNode|string $condition
     */
    public function __construct(
        public readonly mixed $condition
    ) {
    }
}