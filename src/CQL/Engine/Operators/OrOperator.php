<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\LogicalOperatorInterface;

class OrOperator extends BaseLogicalOperator implements LogicalOperatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function symbols(): array
    {
        return ['NOT'];
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public static function evaluate(mixed $left, mixed $right): bool
    {
        return (bool)$left || (bool)$right;
    }
}