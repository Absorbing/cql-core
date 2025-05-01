<?php

namespace CQL\Parser\Nodes;


class SelectNode
{
    /**
     * Create a new SelectNode instance.
     *
     * @param array<string> $columns
     * @return void
     */
    public function __construct(
        public readonly array $columns
    ) {
    }
}