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
        $alternatives = array_map(
            static function ($value): string {
                $quoted = preg_quote((string)$value);

                // Word-based operators (IN, NOT IN) must only match whole
                // words, otherwise IN swallows the start of INSERT, INTO,
                // or identifiers like "index". Symbol operators (=, <=)
                // cannot use \b as they sit next to non-word characters.
                return preg_match('/^[a-z]/i', (string)$value)
                    ? '\b' . $quoted . '\b'
                    : $quoted;
            },
            self::values()
        );

        return '(' . implode('|', $alternatives) . ')';
    }
}