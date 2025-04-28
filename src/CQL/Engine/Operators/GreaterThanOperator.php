<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\ComparisonOperatorInterface;

class GreaterThanOperator implements ComparisonOperatorInterface
{
    /**
     * {@inheritDoc}
     */
    public static function symbols(): array
    {
        return ['>'];
    }

    /**
     * {@inheritDoc}
     */
    public static function evaluate(mixed $left, mixed $right): bool
    {
        return $left > $right;
    }
}