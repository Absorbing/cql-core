<?php

namespace CQL\Lexer\TokenType\Registry;

use CQL\Lexer\TokenType\Traits\TokenEnum;
use CQL\Providers\TokenTypeProvider;
use CQL\Exceptions\LexerException;

class TokenTypeRegistry
{
    /**
     * The patterns for the token groups
     *
     * @return array<string, string>
     */
    public static function generatePatterns(): array
    {
        $patterns = [];

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
                'STRING' => "'(.*?)'",
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
}