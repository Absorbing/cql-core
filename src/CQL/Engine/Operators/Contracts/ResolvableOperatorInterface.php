<?php

namespace CQL\Engine\Operators\Contracts;


interface ResolvableOperatorInterface
{
    /**
     * Return the symbol of the operator.
     *
     * @return array<string>
     */
    public static function symbols(): array;
}