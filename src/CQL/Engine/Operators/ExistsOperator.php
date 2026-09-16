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
     * Check whether a value exists.
     *
     * A value exists when it is neither null (column missing from the row)
     * nor an empty string (empty CSV cell). '0' and 'false' count as
     * existing - EXISTS tests presence, not truthiness.
     *
     * @param mixed $value
     * @return bool
     */
    public static function evaluate(mixed $value): bool
    {
        return $value !== null && $value !== '';
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
     *
     * @return OperandPosition
     */
    public static function operandPosition(): OperandPosition
    {
        return OperandPosition::RIGHT;
    }

}