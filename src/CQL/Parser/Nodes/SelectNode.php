<?php

namespace CQL\Parser\Nodes;


readonly class SelectNode
{
    /**
     * Create a new SelectNode instance.
     *
     * @param list<string|WildcardNode|FunctionNode|AliasedColumnNode> $columns
     * @return void
     */
    public function __construct(
        public readonly array $columns
    ) {
    }
}