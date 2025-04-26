<?php

namespace CQL\Lexer\Enum;

use CQL\Lexer\Trait\TokenEnum;

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
     * Get the group name.
     *
     * @return string
     */
    public static function groupName(): string
    {
        return 'LOGICAL_OPERATOR';
    }
}