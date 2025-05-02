<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\ExpressionOperatorInterface;
use CQL\Engine\Operators\Contracts\MathOperatorInterface;

class ModulusOperator extends BaseMathOperator implements MathOperatorInterface, ExpressionOperatorInterface
{
    /**
     * {@inheritDoc}
     */
    public static function symbols(): array
    {
        return ['%'];
    }

    /**
     * {@inheritDoc}
     */
    public static function evaluate(mixed $left, mixed $right): int|float
    {
        return $left % $right;
    }

    /**
     * {@inheritDoc}
     */
    public static function precedence(): int
    {
        return 5;
    }
}