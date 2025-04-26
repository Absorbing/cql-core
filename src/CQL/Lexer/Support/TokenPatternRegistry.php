<?php

namespace CQL\Lexer\Support;

class TokenPatternRegistry
{
    protected const ENUMS_NAMESPACE = "CQL\Lexer\Enum\\";
    protected const ENUMS_PATH = __DIR__ . '/../Enum/';

    /**
     * The patterns for the token groups
     *
     * @return array<string, string>
     */
    public static function generatePatterns(): array
    {
        $patterns = [];

        // Dynamic Enums
        foreach (self::discoverTokenGroups() as $tokenGroup) {
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

    /**
     * Discovers all classes in the Enums namespace
     *
     * @return array<class-string>
     */
    protected static function discoverTokenGroups(): array
    {
        $tokenGroups = [];
        $files = glob(self::ENUMS_PATH . '*.php');

        foreach ($files as $file) {
            $className = self::ENUMS_NAMESPACE . basename($file, '.php');
            if (class_exists($className)) {
                $tokenGroups[] = $className;
            }
        }

        return $tokenGroups;
    }
}
