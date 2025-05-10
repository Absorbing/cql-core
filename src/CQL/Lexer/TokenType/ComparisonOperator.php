<?php

namespace CQL\Lexer\TokenType;

use CQL\Lexer\TokenType\Traits\TokenEnum;

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
    case IN = 'IN';
    case NOT_IN = 'NOT IN';

    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function groupName(): string
    {
        return 'COMPARISON_OPERATOR';
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public static function pattern(): string
    {
        return '(' . implode('|', array_map(fn($value) => preg_quote((string)$value), self::values())) . ')';
    }
}