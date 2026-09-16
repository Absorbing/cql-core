<?php

namespace CQL\Engine\Operators\Contracts;

interface ComparisonOperatorInterface extends BinaryOperatorInterface
{
    /**
     * Evaluate the operator with the given left and right operands.
     *
     * @param mixed $left
     * @param mixed $right
     * @return bool
     */
    public static function evaluate(mixed $left, mixed $right): bool;
}
