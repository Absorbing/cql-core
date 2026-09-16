<?php

namespace CQL\Engine\Operators\Contracts;

/**
 * Marker interface for operators usable inside precedence-climbing
 * expressions (currently the math operators).
 */
interface ExpressionOperatorInterface extends BinaryOperatorInterface
{
}
