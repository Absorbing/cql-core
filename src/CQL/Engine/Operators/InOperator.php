<?php

namespace CQL\Engine\Operators;

use CQL\Engine\Operators\Contracts\ComparisonOperatorInterface;

class InOperator extends BaseComparisonOperator implements ComparisonOperatorInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function symbols(): array
    {
        return ['IN'];
    }

    /**
     * Check whether the left operand appears in the right operand's values.
     *
     * Uses loose comparison so CSV string values match numeric literals
     * (e.g. '30' IN (18, 30)), consistent with the rest of the engine.
     *
     * @param mixed $left
     * @param mixed $right A list of candidate values
     * @return bool
     */
    public static function evaluate(mixed $left, mixed $right): bool
    {
        $candidates = is_array($right) ? $right : [$right];

        return in_array($left, $candidates);
    }
}
