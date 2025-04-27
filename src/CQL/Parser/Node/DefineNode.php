<?php

namespace CQL\Parser\Node;

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
     * @var array
     */
    public array $columns;

    /**
     * @var string
     */
    public bool $hasHeaders = false;

    /**
     * Create a new DefineNode instance.
     *
     * @param string $path
     * @param string $alias
     * @param array $columns
     */
    public function __construct(string $path, string $alias, array $columns, bool $hasHeaders = false)
    {
        $this->path = $path;
        $this->alias = $alias;
        $this->columns = $columns;
        $this->hasHeaders = $hasHeaders;
    }
}