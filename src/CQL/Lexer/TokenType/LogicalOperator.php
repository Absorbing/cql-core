<?php

namespace CQL\Lexer\TokenType;

use CQL\Lexer\TokenType\Traits\TokenEnum;

/**
 * Enum class for CQL comparison operators.
 *
 * @package CQL\Lexer\Enum
 */
enum LogicalOperator: string
{
    use TokenEnum;

    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';

    /**
     * {@inheritDoc}
     */
    public static function groupName(): string
    {
        return 'LOGICAL_OPERATOR';
    }
}