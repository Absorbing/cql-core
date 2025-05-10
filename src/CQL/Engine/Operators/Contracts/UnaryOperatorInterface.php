<?php

namespace CQL\Engine\Operators\Contracts;

use CQL\Engine\Operators\Enum\OperandPosition;

interface UnaryOperatorInterface extends ResolvableOperatorInterface
{
    /**
     * Get the symbols associated with this operator.
     *
     * @return array<string>
     */
    public static function symbols(): array;

    /**
     * Get the precedence of this operator.
     *
     * @return int
     */
    public static function precedence(): int;

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