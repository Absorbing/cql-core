<?php

namespace CQL\Parser\Nodes;

readonly class AliasedColumnNode
{
    public function __construct(
        public string $expression,
        public string $alias
    ) {
    }
}