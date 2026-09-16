<?php

namespace CQL\Engine\Operators\Contracts;

use CQL\Engine\Operators\Enum\OperandPosition;

/**
 * Contract for operators that take a single operand (e.g. NOT, EXISTS).
 *
 * @package CQL\Engine\Operators\Contracts
 */
interface UnaryOperatorInterface extends ResolvableOperatorInterface
{
    /**
     * Evaluate the operator with the given value.
     *
     * @param mixed $value
     * @return bool
     */
    public static function evaluate(mixed $value): bool;

    /**
     * Get the operand position: 'left' or 'right'.
     *
     * @return OperandPosition
     */
    public static function operandPosition(): OperandPosition;
}
