<?php

namespace CQL\Engine\Operators\Contracts;

interface MathOperatorInterface extends OperatorInterface
{
    /**
     * Evaluate the operator with the given left and right operands.
     *
     * @param mixed $left
     * @param mixed $right
     * @return int|float
     */
    public static function evaluate(mixed $left, mixed $right): int|float;
}