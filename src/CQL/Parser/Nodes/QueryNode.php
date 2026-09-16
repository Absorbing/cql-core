<?php

namespace CQL\Parser\Nodes;

use CQL\Parser\Nodes\Contracts\StatementNodeInterface;

readonly class QueryNode implements StatementNodeInterface
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

    /**
     * @inheritDoc
     *
     * @return array<DefineNode>
     */
    public function getDefines(): array
    {
        return $this->defines;
    }
}
