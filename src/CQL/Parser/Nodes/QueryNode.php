<?php

namespace CQL\Parser\Nodes;

class QueryNode
{
    /**
     * Create a new QueryNode instance.
     *
     * @param DefineNode $define
     * @param SelectNode $select
     * @param FromNode $from
     * @param WhereNode|null $where
     */
    public function __construct(
        public readonly DefineNode $define,
        public readonly SelectNode $select,
        public readonly FromNode $from,
        public readonly ?WhereNode $where = null
    ) {
    }
}