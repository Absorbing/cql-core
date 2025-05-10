<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\UnaryOperatorInterface;
use CQL\Engine\Operators\Enum\OperandPosition;
use CQL\Engine\Operators\Enum\OperatorType;

class ExistsOperator extends BaseUnaryOperator implements UnaryOperatorInterface
{
    /**
     * Get the symbols for this operator.
     *
     * @return array<string>
     */
    public static function symbols(): array
    {
        return ['EXISTS'];
    }

    /**
     * Evaluate the operator with the given left and right operands.
     *
     * @param mixed $value
     * @return bool
     */
    public static function evaluate(mixed $value): bool
    {
        return true;
    }

    /**
     * Get the precedence of this operator.
     *
     * @return int
     */
    public static function precedence(): int
    {
        return 4;
    }

    /**
     * Get the operand position for this operator.
     */
    public static function operandPosition(): OperandPosition
    {
        return OperandPosition::RIGHT;
    }

}