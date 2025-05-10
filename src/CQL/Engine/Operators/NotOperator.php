<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\UnaryOperatorInterface;
use CQL\Engine\Operators\Enum\OperandPosition;

class NotOperator extends BaseUnaryOperator implements UnaryOperatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function symbols(): array
    {
        return ['NOT'];
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public static function evaluate(mixed $value): bool
    {
        return (!(bool)$value);
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public static function precedence(): int
    {
        return 3;
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