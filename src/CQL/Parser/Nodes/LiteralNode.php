<?php

namespace CQL\Parser\Nodes;

/** A value that can never be interpreted as a column reference. */
readonly class LiteralNode
{
    /**
     * @param string|int|float|bool|null $value Literal value.
     */
    public function __construct(public string|int|float|bool|null $value)
    {
    }

    /** @return string */
    public function __toString(): string
    {
        return match (true) {
            $this->value === null => 'NULL',
            is_bool($this->value) => $this->value ? 'TRUE' : 'FALSE',
            default => (string)$this->value,
        };
    }

    /**
     * Decode SQL quote doubling while preserving literal backslashes.
     * @param string $quoted Quoted SQL string.
     * @return string
     */
    public static function decode(string $quoted): string
    {
        $quote = $quoted[0];
        return str_replace($quote . $quote, $quote, substr($quoted, 1, -1));
    }
}
