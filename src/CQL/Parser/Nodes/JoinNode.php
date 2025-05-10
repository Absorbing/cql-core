<?php

namespace CQL\Parser\Nodes;

use CQL\Parser\Enums\JoinType;

readonly class JoinNode
{
    public function __construct(
        public readonly string $rightAlias,
        public readonly string $leftAlias,
        public readonly string $leftKey,
        public readonly string $rightKey,
        public JoinType $type = JoinType::INNER
    ) {
    }
}