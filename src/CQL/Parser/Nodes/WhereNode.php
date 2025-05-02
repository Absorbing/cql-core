<?php

namespace CQL\Parser\Nodes;

class WhereNode
{
    /**
     * Create a new WhereNode instance.
     *
     * @param ConditionNode $condition
     */
    public function __construct(
        public readonly ConditionNode $condition
    ) {
    }
}