<?php

namespace CQL\Engine\Operators\Contracts;

interface OperatorInterface
{
    /**
     * Return the symbol of the operator.
     *
     * @return array<string>
     */
    public static function symbols(): array;

    /**
     * Evaluate the operator with the given left and right operands.
     *
     * @param mixed $left
     * @param mixed $right
     * @return int|float
     */
    public static function evaluate(mixed $left, mixed $right): mixed;
}