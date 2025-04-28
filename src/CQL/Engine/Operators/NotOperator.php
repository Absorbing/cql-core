<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\LogicalOperatorInterface;

class NotOperator implements LogicalOperatorInterface
{
    /**
     * {@inheritDoc}
     */
    public static function symbols(): array
    {
        return ['NOT'];
    }

    /**
     * {@inheritDoc}
     */
    public static function evaluate(mixed $left, mixed $right): bool
    {
        return (!(bool)$left);
    }
}