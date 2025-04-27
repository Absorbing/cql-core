<?php

namespace CQL\Parser\Node;

class FromNode
{
    /**
     * The table name.
     *
     * @var string
     */
    public string $table;

    /**
     * Create a new FromNode instance.
     *
     * @param string $table
     * @return void
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }
}