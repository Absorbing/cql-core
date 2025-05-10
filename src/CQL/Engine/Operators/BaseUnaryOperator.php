<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\UnaryOperatorInterface;
use CQL\Engine\Operators\Enum\OperandPosition;

class BaseUnaryOperator implements UnaryOperatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function symbols(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public static function evaluate(mixed $value): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public static function precedence(): int
    {
        return 2;
    }

    /**
     * {@inheritDoc}
     *
     * @return OperandPosition
     */
    public static function operandPosition(): OperandPosition
    {
        return OperandPosition::RIGHT;
    }
}