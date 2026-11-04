<?php

namespace CQL\Parser\Nodes;

/** A column used as an expression operand. */
readonly class ColumnReferenceNode
{
    /**
     * @param string $name Qualified or unqualified column name.
     */
    public function __construct(public string $name)
    {
    }
}
