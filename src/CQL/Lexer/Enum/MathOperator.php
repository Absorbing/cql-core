<?php

namespace CQL\Lexer\Enum;

use CQL\Lexer\Trait\TokenEnum;

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
     * Get the group name.
     *
     * @return string
     */
    public static function groupName(): string
    {
        return 'MATH_OPERATOR';
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
