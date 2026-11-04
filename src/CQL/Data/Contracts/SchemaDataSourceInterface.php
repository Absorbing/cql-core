<?php

namespace CQL\Data\Contracts;

/** Optional column metadata, including when a source contains no rows. */
interface SchemaDataSourceInterface extends DataSourceInterface
{
    /** @return array<string>|null */
    public function getHeaders(): ?array;
}
