<?php

namespace CQL\Parser\Nodes;

use CQL\Parser\Nodes\Contracts\StatementNodeInterface;

readonly class InsertNode implements StatementNodeInterface
{
    /**
     * Create a new InsertNode instance.
     *
     * @param array<DefineNode> $defines
     * @param string $table Target data source alias.
     * @param array<string> $columns Explicit column list (empty = positional, matching file headers).
     * @param array<array<mixed>> $rows One entry per VALUES tuple; each entry is a list of expressions.
     */
    public function __construct(
        public readonly array $defines,
        public readonly string $table,
        public readonly array $columns,
        public readonly array $rows
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
