<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\ComparisonOperatorInterface;

class BaseComparisonOperator implements ComparisonOperatorInterface
{
    /**
     * {@inheritDoc}
     */
    public static function symbols(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public static function evaluate(mixed $left, mixed $right): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public static function precedence(): int
    {
        return 3;
    }
}