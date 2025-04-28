<?php

namespace CQL\Parser\Nodes;

class DefineNode
{
    /**
     * @var string
     */
    public string $path;

    /**
     * @var string
     */
    public string $alias;

    /**
     * @var array<string>
     */
    public array $columns;

    /**
     * @var bool
     */
    public bool $hasHeaders = false;

    /**
     * Create a new DefineNode instance.
     *
     * @param string $path
     * @param string $alias
     * @param array<string> $columns
     * @param bool $hasHeaders
     */
    public function __construct(string $path, string $alias, array $columns, bool $hasHeaders = false)
    {
        $this->path = $path;
        $this->alias = $alias;
        $this->columns = $columns;
        $this->hasHeaders = $hasHeaders;
    }
}