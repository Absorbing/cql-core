<?php

namespace CQL\Lexer\TokenType\Registry;

use CQL\Lexer\TokenType\Traits\TokenEnum;
use CQL\Providers\TokenTypeProvider;
use CQL\Exceptions\LexerException;
use CQL\Lexer\Token;

class TokenTypeRegistry
{
    private static $structuralTokens = [
        'DOT',
    ];

    /**
     * The patterns for the token groups
     *
     * @return array<string, string>
     */
    public static function generatePatterns(): array
    {
        $patterns = [
            'PARAMETER' => '(?<PARAMETER>:[a-zA-Z_][a-zA-Z0-9_]*|\?)',
            'COMMENT' => '(?<COMMENT>--[^\r\n]*|/\*.*?\*/)',
            'NULL' => '(?<NULL>\bNULL\b)',
        ];

        foreach (TokenTypeProvider::provide() as $tokenGroup) {
            if (!in_array(TokenEnum::class, class_uses($tokenGroup))) {
                throw new LexerException(
                    sprintf('Token group %s must use TokenEnum trait', $tokenGroup)
                );
            }

            $name = $tokenGroup::groupName();
            $patterns[$name] = '(?<' . $name . '>' . $tokenGroup::pattern() . ')';
        }

        $patternGroups = [
            'literals' => [
                'STRING' => "'(?:[^']|'')*'|\"(?:[^\"]|\"\")*\"",
                'NUMBER' => '\b\d+(\.\d+)?\b',
            ],
            'identifiers' => [
                'IDENTIFIER' => '[a-zA-Z_][a-zA-Z0-9_]*',
            ],
            'punctuation' => [
                'COMMA' => ',',
                'SEMICOLON' => ';',
                'LPAREN' => '\(',
                'RPAREN' => '\)',
                'DOT' => '\.',
            ],
            'whitespace' => [
                'WHITESPACE' => '\s+',
            ],
        ];

        foreach ($patternGroups as $group) {
            foreach ($group as $name => $regex) {
                $patterns[$name] = '(?<' . $name . '>' . $regex . ')';
            }
        }

        return $patterns;
    }

    /**
     * Check if the token type is structural.
     *
     * @param string $type
     * @return bool
     */
    public static function isStructural(string $type): bool
    {
        return in_array($type, self::$structuralTokens);
    }

    /**
     * Check if the token is a wildcard.
     *
     * @param Token $token
     * @return bool
     */
    public static function isWildcard(Token $token): bool
    {
        return $token->type === 'MATH_OPERATOR' && $token->value === '*';
    }
}