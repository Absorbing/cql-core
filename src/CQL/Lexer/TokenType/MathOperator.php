<?php

namespace CQL\Lexer\TokenType;

use CQL\Lexer\TokenType\Traits\TokenEnum;

/**
 * Enum class for mathematical operators.
 *
 * @package CQL\Lexer\Enum
 */
enum MathOperator: string
{
    use TokenEnum;

    case ADD = '+';
    case SUBTRACT = '-';
    case MULTIPLY = '*';
    case DIVIDE = '/';
    case MODULUS = '%';
    case POWER = '^';

    /**
     * {@inheritDoc}
     */
    public static function groupName(): string
    {
        return 'MATH_OPERATOR';
    }

    /**
     * {@inheritDoc}
     */
    public static function pattern(): string
    {
        return '(' . implode('|', array_map(fn($value) => preg_quote((string)$value), self::values())) . ')';
    }
}
