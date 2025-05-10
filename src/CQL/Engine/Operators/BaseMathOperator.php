<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\MathOperatorInterface;

class BaseMathOperator implements MathOperatorInterface
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
     * @return int|float
     */
    public static function evaluate(mixed $left, mixed $right): int|float
    {
        return 0;
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public static function precedence(): int
    {
        return 4;
    }
}