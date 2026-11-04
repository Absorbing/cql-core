<?php

namespace CQL\Data\Contracts;

/** Optional row streaming; load() should initialize schema without materializing rows. */
interface StreamingDataSourceInterface extends SchemaDataSourceInterface
{
    /** @return iterable<array-key, array<string, mixed>> */
    public function streamRows(): iterable;
}
