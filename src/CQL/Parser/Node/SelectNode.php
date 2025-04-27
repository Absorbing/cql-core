<?php

namespace CQL\Parser\Node;


class SelectNode
{
    /**
     * @var array<string>
     */
    public array $columns;

    /**
     * Create a new SelectNode instance.
     *
     * @param array<string> $columns
     * @return void
     */
    public function __construct(array $columns)
    {
        $this->columns = $columns;
    }
}