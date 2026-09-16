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
