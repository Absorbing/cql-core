<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\ComparisonOperatorInterface;

class BaseComparisonOperator implements ComparisonOperatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function symbols(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public static function evaluate(mixed $left, mixed $right): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public static function precedence(): int
    {
        return 3;
    }
}