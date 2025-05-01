<?php

namespace CQL\Lexer;

use CQL\Lexer\TokenType\Registry\TokenTypeRegistry;

class Tokenizer
{
    /**
     * @var array<Token>
     */
    protected array $tokens = [];

    public function __construct(
        protected string $input
    ) {
    }

    /**
     * Tokenize the input string into an array of tokens.
     *
     * @return array<Token> An array of Token objects.
     */
    public function tokenize(): array
    {
        $patterns = TokenTypeRegistry::generatePatterns();
        $regex = '~' . implode('|', $patterns) . '~i';

        preg_match_all($regex, $this->input, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            foreach ($match as $name => $group) {
                if (is_string($name) && $group[1] !== -1) {
                    if ($name === 'WHITESPACE') {
                        continue 2; // Skip whitespace
                    }

                    $value = $group[0];
                    $pos = $group[1];

                    $this->tokens[] = new Token($name, $value, $pos);

                    break; // First match wins
                }
            }
        }

        return $this->tokens;
    }
}
