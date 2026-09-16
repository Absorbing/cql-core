<?php

namespace CQL\Parser\Nodes\Contracts;

use CQL\Parser\Nodes\DefineNode;

/**
 * Common contract for all top-level CQL statements
 * (SELECT queries and INSERT/UPDATE/DELETE mutations).
 *
 * @package CQL\Parser\Nodes\Contracts
 */
interface StatementNodeInterface
{
    /**
     * Get the DEFINE statements attached to this statement.
     *
     * @return array<DefineNode>
     */
    public function getDefines(): array;
}
