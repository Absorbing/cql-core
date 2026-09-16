<?php

namespace CQL\Engine\Operators\Contracts;

/**
 * Contract for operators that take a left and right operand
 * (comparison, logical, and math operators).
 *
 * @package CQL\Engine\Operators\Contracts
 */
interface BinaryOperatorInterface extends ResolvableOperatorInterface
{
    /**
     * Evaluate the operator with the given left and right operands.
     *
     * @param mixed $left
     * @param mixed $right
     * @return mixed
     */
    public static function evaluate(mixed $left, mixed $right): mixed;
}
