<?php

namespace CQL\Parser\Nodes;

readonly class FromNode
{
    /**
     * Create a new FromNode instance.
     *
     * @param string $table
     * @param array<JoinNode> $joins
     * @return void
     */
    public function __construct(
        public readonly string $table,
        public readonly array $joins = []
    ) {
    }
}