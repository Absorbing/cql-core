<?php

namespace CQL\Lexer;

readonly class Token
{
    public function __construct(
        public string $type,
        public mixed $value,
        public int $position,
    ) {
    }

    /**
     * Check if the token is of a specific type.
     *
     * @param string $type The type to check against.
     * @return bool True if the token is of the specified type, false otherwise.
     */
    public function isType(string $type): bool
    {
        return $this->type === $type;
    }

    /**
     * Get the value of the token.
     *
     * @return string The value of the token.
     */
    public function __toString(): string
    {
        return sprintf('%s(%s) at %d', $this->type, (string)$this->value, $this->position);
    }
}
