<?php

namespace CQL\Parser\Nodes;

/** A value placeholder, never an identifier or query fragment. */
readonly class ParameterNode
{
    /**
     * @param string|int $key Name or zero-based positional index.
     * @param int $position Query byte offset.
     */
    public function __construct(public string|int $key, public int $position)
    {
    }

    /** @return string */
    public function __toString(): string
    {
        return is_int($this->key) ? '?' . $this->key : ':' . $this->key;
    }
}
