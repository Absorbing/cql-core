<?php

namespace CQL\Parser\Node;

class WhereNode
{
    /**
     * @var ConditionNode
     */
    public ConditionNode $condition;

    /**
     * Create a new WhereNode instance.
     *
     * @param ConditionNode $condition
     */
    public function __construct(ConditionNode $condition)
    {
        $this->condition = $condition;
    }
}