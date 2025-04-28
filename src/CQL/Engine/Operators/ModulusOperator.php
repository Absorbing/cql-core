<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\MathOperatorInterface;

class ModulusOperator implements MathOperatorInterface
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
}