<?php

namespace CQL\Providers;


use CQL\Lexer\TokenType\Boolean;
use CQL\Lexer\TokenType\ComparisonOperator;
use CQL\Lexer\TokenType\Keyword;
use CQL\Lexer\TokenType\LogicalOperator;
use CQL\Lexer\TokenType\MathOperator;

class TokenTypeProvider
{
    /**
     * Provide a list of token type classes.
     *
     * @return array<class-string>
     */
    public static function provide(): array
    {
        return [
            Boolean::class,
            ComparisonOperator::class,
            Keyword::class,
            LogicalOperator::class,
            MathOperator::class,
        ];
    }
}