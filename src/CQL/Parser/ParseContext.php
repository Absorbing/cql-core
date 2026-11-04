<?php

namespace CQL\Parser;

use CQL\Lexer\Token;
use CQL\Parser\Nodes\ParameterNode;
use CQL\Exceptions\ParameterException;
use CQL\Exceptions\ParserException;

/** @internal Grammar implementation shared by Parser. */
final class ParseContext
{
    public int $position = 0;
    /** @var array<string|int, ParameterNode> */
    public array $parameters = [];
    public ?string $parameterStyle = null;

    /**
     * @param array<Token> $tokens Input tokens, including source positions.
     */
    public function __construct(public readonly array $tokens)
    {
    }
    /**
     * Expect a token of a specific type and value.
     *
     * @param string $type
     * @param string|null $value
     * @return bool
     */
    public function match(string $type, ?string $value = null): bool
    {
        $token = $this->tokens[$this->position] ?? null;

        if (!$token || $token->type !== $type || ($value !== null && strtoupper($token->value) !== strtoupper(
                    $value
                ))) {
            return false;
        }

        return true;
    }
    /**
     * Expect a token of a specific type and value.
     *
     * @param string $type
     * @param string|null $value
     * @return Token
     */
    public function expect(string $type, ?string $value = null): Token
    {
        if (!$this->match($type, $value)) {
            $expected = $value ? "$type('$value')" : $type;
            $actual = $this->tokens[$this->position] ?? 'EOF';
            $actualDesc = is_object($actual) ? "{$actual->type}('{$actual->value}')" : (string)$actual;

            throw new ParserException(
                "Expected token '$expected', Actual: '$actualDesc' at position {$this->position}"
            );
        }

        return $this->advance();
    }
    /**
     * Advance to the next token.
     *
     * @return Token
     */
    public function advance(): Token
    {
        return $this->tokens[$this->position++];
    }
    /**
     * Peek at the next token without advancing.
     *
     * @param int $offset
     * @return Token|null
     */
    public function peek(int $offset = 0): ?Token
    {
        return $this->tokens[$this->position + $offset] ?? null;
    }

    /**
     * Consume an optional separator or keyword.
     * @param string $type Token type.
     * @param string|null $value Optional token value.
     * @return bool
     */
    public function consume(string $type, ?string $value = null): bool
    {
        if (!$this->match($type, $value)) {
            return false;
        }
        $this->advance();
        return true;
    }

    /**
     * Register a parameter while preserving its original source position.
     * @return ParameterNode
     */
    public function parseParameter(): ParameterNode
    {
        $token = $this->advance();
        $style = $token->value === '?' ? 'positional' : 'named';
        if ($this->parameterStyle !== null && $this->parameterStyle !== $style) {
            throw new ParameterException('Cannot mix named and positional parameters', position: $token->position);
        }
        $this->parameterStyle = $style;
        $key = $style === 'positional' ? count($this->parameters) : substr($token->value, 1);
        if (array_key_exists($key, $this->parameters)) {
            throw new ParameterException("Use a unique name for each parameter occurrence: '{$key}'", position: $token->position);
        }
        return $this->parameters[$key] = new ParameterNode($key, $token->position);
    }
}
