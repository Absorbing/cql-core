<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\ComparisonOperatorInterface;

class InOperator extends BaseComparisonOperator implements ComparisonOperatorInterface
{
    /**
     * {@inheritDoc}
     */
    public static function symbols(): array
    {
        return ['IN'];
    }

    /**
     * {@inheritDoc}
     */
    public static function evaluate(mixed $left, mixed $right): bool
    {
        return false;
    }
}