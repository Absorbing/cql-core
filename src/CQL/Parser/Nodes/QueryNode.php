<?php

namespace CQL\Parser\Nodes;

readonly class QueryNode
{
    /**
     * Create a new QueryNode instance.
     *
     * @param array<DefineNode> $defines
     * @param SelectNode $select
     * @param FromNode $from
     * @param WhereNode|null $where
     * @param GroupByNode|null $groupBy
     */
    public function __construct(
        public readonly array $defines,
        public readonly SelectNode $select,
        public readonly FromNode $from,
        public readonly ?WhereNode $where = null,
        public readonly ?GroupByNode $groupBy = null
    ) {
    }
}