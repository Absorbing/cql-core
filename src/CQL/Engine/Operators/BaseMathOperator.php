<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\MathOperatorInterface;

class BaseMathOperator implements MathOperatorInterface
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
    public static function evaluate(mixed $left, mixed $right): int|float
    {
        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public static function precedence(): int
    {
        return 4;
    }
}