<?php

namespace CQL\Parser\Node;

class ConditionNode
{
    /**
     * @var string
     */
    public string $left;

    /**
     * @var string
     */
    public string $operator;

    /**
     * @var string
     */
    public string $right;

    /**
     * Create a new ConditionNode instance.
     *
     * @param string $left
     * @param string $operator
     * @param string $right
     */
    public function __construct(string $left, string $operator, string $right)
    {
        $this->left = $left;
        $this->operator = $operator;
        $this->right = $right;
    }
}