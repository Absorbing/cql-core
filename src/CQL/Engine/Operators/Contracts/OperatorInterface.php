<?php

namespace CQL\Engine\Operators\Contracts;

interface OperatorInterface
{
    /**
     * Return the symbol of the operator.
     *
     * @return array<string>
     */
    public static function symbols(): array;
}