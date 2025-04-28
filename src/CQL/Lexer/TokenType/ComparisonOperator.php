<?php

namespace CQL\Lexer\TokenType;

use CQL\Lexer\Trait\TokenEnum;

/**
 * Enum class for CQL comparison operators.
 *
 * @package CQL\Lexer\Enums
 */
enum ComparisonOperator: string
{
    use TokenEnum;

    case EQUAL = '=';
    case NOT_EQUAL_EXCLAMATION = '!=';
    case NOT_EQUAL_ANGLE = '<>';
    case GREATER_THAN_OR_EQUAL = '>=';
    case GREATER_THAN = '>';
    case LESS_THAN_OR_EQUAL = '<=';
    case LESS_THAN = '<';

    /**
     * Get the group name.
     *
     * @return string
     */
    public static function groupName(): string
    {
        return 'COMPARISON_OPERATOR';
    }

    /**
     * Get the regex pattern for this token group.
     *
     * @return string
     */
    public static function pattern(): string
    {
        return '(' . implode('|', array_map('preg_quote', self::values())) . ')';
    }
}