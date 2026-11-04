<?php

namespace CQL\Data\Csv;

use CQL\Exceptions\DataSourceException;

/** @internal Checked CSV file operation. */
final class WriteLock
{
    private int $depth = 0;

    /**
     * @param string $path Canonical CSV path shared across symlink aliases.
     */
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Keep nested writes inside the same complete read/modify/write lock.
     * @template T
     * @param callable(): T $operation Entire mutation.
     * @return T
     */
    public function run(callable $operation): mixed
    {
        if ($this->depth > 0) {
            return $operation();
        }
        $lock = fopen($this->path . '.cql.lock', 'c');
        if ($lock === false) {
            throw new DataSourceException("Unable to open write lock: {$this->path}", context: ['path' => $this->path]);
        }
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new DataSourceException("Unable to acquire write lock: {$this->path}", context: ['path' => $this->path]);
        }
        $this->depth++;
        try {
            clearstatcache(true, $this->path);
            return $operation();
        } finally {
            $this->depth--;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
