<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\ComparisonOperatorInterface;

class LessThanOperator extends BaseComparisonOperator implements ComparisonOperatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function symbols(): array
    {
        return ['<'];
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public static function evaluate(mixed $left, mixed $right): bool
    {
        return $left < $right;
    }
}
