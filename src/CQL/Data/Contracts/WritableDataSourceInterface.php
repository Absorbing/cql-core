<?php

namespace CQL\Data\Contracts;

interface WritableDataSourceInterface
{
    /**
     * Append rows to the end of the data source.
     *
     * Each row is an associative array of un-namespaced column => value pairs.
     *
     * @param array<array<string, mixed>> $rows
     * @return int Number of rows appended
     */
    public function appendRows(array $rows): int;

    /**
     * Atomically replace the entire contents of the data source.
     *
     * Rows are written to a temporary file which is renamed over the
     * original, so a failure part-way through never corrupts the source.
     * Accepts any iterable (including generators) so large files can be
     * rewritten without loading every row into memory.
     *
     * @param iterable<array<string, mixed>> $rows Un-namespaced rows.
     * @return void
     */
    public function rewriteFrom(iterable $rows): void;

    /**
     * Get the column headers for the data source.
     *
     * @return array<string>|null
     */
    public function getHeaders(): ?array;
}
