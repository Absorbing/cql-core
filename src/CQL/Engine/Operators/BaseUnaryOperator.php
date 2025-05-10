<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\UnaryOperatorInterface;
use CQL\Engine\Operators\Enum\OperandPosition;

class BaseUnaryOperator implements UnaryOperatorInterface
{
    /**
     * {@inheritDoc}
     */
    public static function symbols(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public static function evaluate(mixed $value): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public static function precedence(): int
    {
        return 2;
    }

    /**
     * {@inheritDoc}
     */
    public static function operandPosition(): OperandPosition
    {
        return OperandPosition::RIGHT;
    }
}