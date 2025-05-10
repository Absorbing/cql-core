<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\ExpressionOperatorInterface;
use CQL\Engine\Operators\Contracts\MathOperatorInterface;

class DivideOperator extends BaseMathOperator implements MathOperatorInterface, ExpressionOperatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function symbols(): array
    {
        return ['/'];
    }

    /**
     * {@inheritDoc}
     *
     * @return int|float
     */
    public static function evaluate(mixed $left, mixed $right): int|float
    {
        return $left / $right;
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public static function precedence(): int
    {
        return 5;
    }
}