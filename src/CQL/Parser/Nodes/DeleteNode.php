<?php

namespace CQL\Parser\Nodes;

use CQL\Parser\Nodes\Contracts\StatementNodeInterface;

readonly class DeleteNode implements StatementNodeInterface
{
    /**
     * Create a new DeleteNode instance.
     *
     * @param array<DefineNode> $defines
     * @param string $table Target data source alias
     * @param WhereNode|null $where Null deletes ALL rows
     */
    public function __construct(
        public readonly array $defines,
        public readonly string $table,
        public readonly ?WhereNode $where = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDefines(): array
    {
        return $this->defines;
    }
}
