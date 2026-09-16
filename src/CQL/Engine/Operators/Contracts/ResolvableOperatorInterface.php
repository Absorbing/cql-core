<?php

namespace CQL\Engine\Operators\Contracts;

/**
 * Base contract for anything the OperatorRegistry can resolve by symbol.
 *
 * Every operator, regardless of arity, has symbols and a precedence.
 * The arity-specific evaluate() signatures live on BinaryOperatorInterface
 * and UnaryOperatorInterface.
 *
 * @package CQL\Engine\Operators\Contracts
 */
interface ResolvableOperatorInterface
{
    /**
     * Return the symbol(s) of the operator.
     *
     * @return array<string>
     */
    public static function symbols(): array;

    /**
     * Get the precedence of the operator.
     *
     * @return int
     */
    public static function precedence(): int;
}
