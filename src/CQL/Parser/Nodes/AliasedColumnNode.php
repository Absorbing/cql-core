<?php

namespace CQL\Parser\Nodes;

readonly class AliasedColumnNode
{
    /**
     * Create a new AliasedColumnNode instance.
     *
     * @param string $expression
     * @param string $alias
     */
    public function __construct(
        public string $expression,
        public string $alias
    ) {
    }
}