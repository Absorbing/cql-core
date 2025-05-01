<?php

namespace CQL\Parser\Nodes;

use CQL\Data\Enum\CSVHeaderMode;

class DefineNode
{
    /**
     * Create a new DefineNode instance.
     *
     * @param string $path
     * @param string $alias
     * @param array<string> $columns
     * @param CSVHeaderMode $hasHeaders
     */
    public function __construct(
        public readonly string $path,
        public readonly string $alias,
        public readonly array $columns,
        public readonly CSVHeaderMode $hasHeaders = CSVHeaderMode::WITHOUT_HEADERS
    ) {
    }
}