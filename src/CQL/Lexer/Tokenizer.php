<?php

namespace CQL\Lexer;

use CQL\Exceptions\LexerException;
use CQL\Lexer\TokenType\Registry\TokenTypeRegistry;

class Tokenizer
{
    /**
     * @param string $input Query text.
     */
    public function __construct(protected string $input)
    {
    }

    /**
     * Consume the entire input, reporting the first invalid byte.
     * @return array<Token>
     */
    public function tokenize(): array
    {
        $patterns = TokenTypeRegistry::generatePatterns();
        $regex = '~\G(?:' . implode('|', $patterns) . ')~is';
        $tokens = [];
        $offset = 0;
        $length = strlen($this->input);

        while ($offset < $length) {
            if (substr($this->input, $offset, 2) === '/*' && strpos($this->input, '*/', $offset + 2) === false) {
                throw new LexerException('Unterminated comment', position: $offset);
            }
            $matched = preg_match($regex, $this->input, $matches, PREG_OFFSET_CAPTURE, $offset);
            if ($matched !== 1 || $matches[0][0] === '') {
                throw new LexerException("Unexpected character at byte {$offset}", position: $offset);
            }
            foreach ($matches as $name => $group) {
                if (is_string($name) && $group[1] !== -1) {
                    if ($name !== 'WHITESPACE' && $name !== 'COMMENT') {
                        $tokens[] = new Token($name, $group[0], $offset);
                    }
                    break;
                }
            }
            $offset += strlen($matches[0][0]);
        }
        return $tokens;
    }
}
