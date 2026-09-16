<?php

namespace CQL\Data\Contracts;

/** Optional serialization of an entire read-modify-write operation. */
interface LockingWritableDataSourceInterface extends WritableDataSourceInterface
{
    /**
     * @template T
     * @param callable(): T $operation Operation performed while holding the write lock.
     * @return T
     */
    public function withWriteLock(callable $operation): mixed;
}
