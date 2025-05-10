<?php

namespace CQL\Parser\Nodes;

readonly class WildcardNode
{
    /**
     * Create a new WildcardNode instance.
     *
     * @param string|null $prefix
     */
    public function __construct(
        public readonly ?string $prefix = null,
    ) {
    }
}