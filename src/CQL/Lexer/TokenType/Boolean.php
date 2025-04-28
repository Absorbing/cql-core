<?php

namespace CQL\Lexer\TokenType;

use CQL\Lexer\TokenType\Traits\TokenEnum;

/**
 * Enum class for CQL comparison operators.
 *
 * @package CQL\Lexer\Enums
 */
enum Boolean: string
{
    use TokenEnum;

    case TRUE = 'TRUE';
    case FALSE = 'FALSE';
}