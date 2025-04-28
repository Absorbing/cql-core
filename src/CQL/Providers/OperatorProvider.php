<?php

namespace CQL\Providers;

use CQL\Engine\Operators\AddOperator;
use CQL\Engine\Operators\AndOperator;
use CQL\Engine\Operators\DivideOperator;
use CQL\Engine\Operators\EqualOperator;
use CQL\Engine\Operators\GreaterThanOperator;
use CQL\Engine\Operators\GreaterThanOrEqualOperator;
use CQL\Engine\Operators\LessThanOperator;
use CQL\Engine\Operators\LessThanOrEqualOperator;
use CQL\Engine\Operators\ModulusOperator;
use CQL\Engine\Operators\MultiplyOperator;
use CQL\Engine\Operators\NotEqualOperator;
use CQL\Engine\Operators\NotOperator;
use CQL\Engine\Operators\OrOperator;
use CQL\Engine\Operators\PowerOperator;
use CQL\Engine\Operators\SubtractOperator;

class OperatorProvider
{
    /**
     * Provides a list of operator classes.
     *
     * @return array<class-string>
     */
    public static function provide(): array
    {
        return [
            AddOperator::class,
            AndOperator::class,
            DivideOperator::class,
            EqualOperator::class,
            GreaterThanOperator::class,
            GreaterThanOrEqualOperator::class,
            LessThanOrEqualOperator::class,
            LessThanOperator::class,
            ModulusOperator::class,
            MultiplyOperator::class,
            NotEqualOperator::class,
            NotOperator::class,
            OrOperator::class,
            PowerOperator::class,
            SubtractOperator::class,
        ];
    }
}