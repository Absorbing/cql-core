<?php

namespace CQL\Engine\Operators\Contracts;

interface OperatorInterface extends ResolvableOperatorInterface
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

    /**
     * Get the precedence of the operator.
     *
     * @return int
     */
    public static function precedence(): int;
}