<?php

namespace CQL\Lexer;

use CQL\Lexer\Support\TokenPatternRegistry;
use CQL\Lexer\Token;

class Tokenizer
{
    protected string $input;
    protected array $tokens = [];

    public function __construct(string $input)
    {
        $this->input = $input;
    }

    /**
     * Tokenize the input string into an array of tokens.
     *
     * @return array<array{type: string, value: mixed, pos: int}>
     */
    public function tokenize(): array
    {
        $patterns = TokenPatternRegistry::generatePatterns();
        $regex = '/' . implode('|', $patterns) . '/i';

        preg_match_all($regex, $this->input, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            if (!is_array($match) || isset($match['WHITESPACE'])) {
                continue;
            }

            foreach ($match as $name => $group) {
                if (is_string($name) && is_array($group) && $group[1] !== -1) {
                    $value = $group[0];
                    $pos = $group[1];
                    $this->tokens[] = new Token($name, $value, $pos);

                    break;
                }
            }
        }

        return $this->tokens;
    }
}
