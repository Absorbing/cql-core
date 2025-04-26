<?php

namespace CQL\Lexer\Support;

class TokenPatternRegistry
{
    protected const ENUMS_NAMESPACE = "CQL\Lexer\Enums\\";
    protected const ENUMS_PATH = __DIR__ . '/../Enums/';

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

        $patterns['STRING'] = '(?<STRING>\'(.*?)\')';
        $patterns['NUMBER'] = '(?<NUMBER>\b\d+(\.\d+)?\b)';
        $patterns['IDENTIFIER'] = '(?<IDENTIFIER>[a-zA-Z_][a-zA-Z0-9_]*)';
        $patterns['COMMA'] = '(?<COMMA>,)';
        $patterns['SEMICOLON'] = '(?<SEMICOLON>;)';
        $patterns['LPAREN'] = '(?<LPAREN>\()';
        $patterns['RPAREN'] = '(?<RPAREN>\))';
        $patterns['WHITESPACE'] = '(?<WHITESPACE>\s+)';

        return $patterns;
    }

    /**
     * Discovers all classes in the Enums namespace
     *
     * @return array
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