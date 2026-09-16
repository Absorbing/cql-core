<?php

namespace CQL\Parser\Nodes;

use CQL\Parser\Nodes\Contracts\StatementNodeInterface;

readonly class UpdateNode implements StatementNodeInterface
{
    /**
     * Create a new UpdateNode instance.
     *
     * @param array<DefineNode> $defines
     * @param string $table Target data source alias.
     * @param array<AssignmentNode> $assignments
     * @param WhereNode|null $where
     */
    public function __construct(
        public readonly array $defines,
        public readonly string $table,
        public readonly array $assignments,
        public readonly ?WhereNode $where = null
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
