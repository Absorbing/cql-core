<?php

namespace CQL\Parser\Nodes;

/**
 * Represents a GROUP BY clause in the query.
 */
readonly class GroupByNode
{
    /**
     * Create a new GroupByNode instance.
     *
     * @param array<string|FunctionNode> $columns Columns or functions to group by.
     */
    public function __construct(
        public array $columns
    ) {
    }
}
