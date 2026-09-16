<?php

namespace CQL\Data;

use CQL\Data\Contracts\DataSourceInterface;
use CQL\Data\Contracts\SchemaDataSourceInterface;
use CQL\Data\Contracts\StreamingDataSourceInterface;
use CQL\Data\Contracts\WritableDataSourceInterface;
use CQL\Data\Contracts\LockingWritableDataSourceInterface;
use CQL\Exceptions\DataSourceException;

/** Resolve source capabilities and qualify row keys for the query engine. */
final class SourceHandle
{
    /**
     * @param DataSourceInterface $source Underlying source.
     * @param string $alias Query alias.
     * @param bool $qualified Whether rows already use this alias prefix.
     */
    public function __construct(
        public readonly DataSourceInterface $source,
        public readonly string $alias,
        private readonly bool $qualified = false,
    ) {
    }

    /** @return void */
    public function load(): void
    {
        $this->source->load();
    }

    /** @return void */
    public function loadForInsert(): void
    {
        if (!$this->source instanceof CSVDataSource || ($this->source->getFileSize() ?: 0) > 0) {
            $this->load();
        }
    }

    /** @return array<array-key, array<string, mixed>> */
    public function getRows(): array
    {
        return iterator_to_array($this->rows());
    }

    /** @return iterable<array-key, array<string, mixed>> */
    public function rows(): iterable
    {
        $rows = $this->source instanceof StreamingDataSourceInterface && $this->isStreaming() ? $this->source->streamRows() : $this->source->getRows();
        foreach ($rows as $key => $row) {
            if ($this->qualified) {
                yield $key => $row;
                continue;
            }
            $qualified = [];
            foreach ($row as $column => $value) {
                $qualified[$this->alias . '.' . $column] = $value;
            }
            yield $key => $qualified;
        }
    }

    /** @return array<string>|null */
    public function getHeaders(): ?array
    {
        if ($this->source instanceof SchemaDataSourceInterface || $this->source instanceof WritableDataSourceInterface) {
            return $this->source->getHeaders();
        }
        $rows = $this->source->getRows();
        return $rows === [] ? null : array_keys(reset($rows));
    }

    /** @return bool */
    public function isStreaming(): bool
    {
        return $this->source instanceof CSVDataSource ? $this->source->isStreaming() : $this->source instanceof StreamingDataSourceInterface;
    }

    /**
     * @template T
     * @param callable(): T $operation Complete mutation, including its reads.
     * @return T
     */
    public function withWriteLock(callable $operation): mixed
    {
        $source = $this->writable();
        return $source instanceof LockingWritableDataSourceInterface ? $source->withWriteLock($operation) : $operation();
    }

    /** @return WritableDataSourceInterface */
    private function writable(): WritableDataSourceInterface
    {
        if (!$this->source instanceof WritableDataSourceInterface) {
            throw new DataSourceException("Source '{$this->alias}' is read-only", context: ['alias' => $this->alias]);
        }
        return $this->source;
    }

    /**
     * @param array<array<string, mixed>> $rows Rows to append.
     * @return int Affected rows.
     */
    public function appendRows(array $rows): int
    {
        return $this->writable()->appendRows($rows);
    }

    /**
     * @param iterable<array<string, mixed>> $rows Replacement rows.
     * @return void
     */
    public function rewriteFrom(iterable $rows): void
    {
        $this->writable()->rewriteFrom($rows);
    }
}
