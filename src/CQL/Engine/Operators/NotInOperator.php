<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\ComparisonOperatorInterface;

class NotInOperator extends BaseComparisonOperator implements ComparisonOperatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function symbols(): array
    {
        return ['NOT IN'];
    }

    /**
     * Check whether the left operand does NOT appear in the right
     * operand's values.
     *
     * @param mixed $left
     * @param mixed $right A list of candidate values.
     * @return bool
     */
    public static function evaluate(mixed $left, mixed $right): bool
    {
        return !InOperator::evaluate($left, $right);
    }
}
