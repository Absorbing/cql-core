<?php

namespace CQL\Parser\Nodes;

class FromNode
{
    /**
     * Create a new FromNode instance.
     *
     * @param string $table
     * @return void
     */
    public function __construct(
        public readonly string $table
    ) {
    }
}