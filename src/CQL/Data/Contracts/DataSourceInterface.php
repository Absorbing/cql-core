<?php

namespace CQL\Data\Contracts;

interface DataSourceInterface
{
    /**
     * Load the data source.
     *
     * @return void
     */
    public function load(): void;

    /**
     * Get all rows from the data source.
     *
     * @return array<array<string, mixed>>
     */
    public function getRows(): array;
}