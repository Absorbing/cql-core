<?php

namespace CQL\Parser\Nodes;

use CQL\Data\Enum\CSVHeaderMode;

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
    public CSVHeaderMode $hasHeaders = CSVHeaderMode::WITHOUT_HEADERS;

    /**
     * Create a new DefineNode instance.
     *
     * @param string $path
     * @param string $alias
     * @param array<string> $columns
     * @param CSVHeaderMode $hasHeaders
     */
    public function __construct(
        string $path,
        string $alias,
        array $columns,
        CSVHeaderMode $hasHeaders = CSVHeaderMode::WITHOUT_HEADERS
    ) {
        $this->path = $path;
        $this->alias = $alias;
        $this->columns = $columns;
        $this->hasHeaders = $hasHeaders;
    }
}