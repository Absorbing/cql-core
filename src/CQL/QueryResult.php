<?php

namespace CQL;

use Closure;
use Generator;
use Iterator;

/** A forward-only result cursor with schema and mutation information. */
final class QueryResult
{
    private ?Iterator $iterator = null;
    private ?Closure $factory;
    private bool $advance = false;
    private bool $closed = false;

    /**
     * @param array<ResultColumn> $columns Ordered output metadata.
     * @param iterable<array<string, mixed>>|Closure(): iterable<array<string, mixed>> $rows Rows or a lazy row factory.
     * @param int|null $affectedRows Mutation count; null for a SELECT.
     */
    public function __construct(
        private readonly array $columns,
        iterable|Closure $rows = [],
        private readonly ?int $affectedRows = null,
    ) {
        $this->factory = $rows instanceof Closure ? $rows : static fn(): iterable => $rows;
    }

    /** @return array<ResultColumn> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return int|null */
    public function affectedRows(): ?int
    {
        return $this->affectedRows;
    }

    /** @return bool */
    public function isQuery(): bool
    {
        return $this->affectedRows === null;
    }

    /** @return array<string, mixed>|null */
    public function fetch(): ?array
    {
        if ($this->closed) {
            return null;
        }
        try {
            if ($this->iterator === null) {
                $factory = $this->factory ?? throw new \LogicException('Result row factory is unavailable');
                $rows = $factory();
                $this->factory = null;
                $this->iterator = (static function () use ($rows): Generator { yield from $rows; })();
            }
            if ($this->advance) {
                $this->iterator->next();
            }
            if (!$this->iterator->valid()) {
                $this->close();
                return null;
            }
            $this->advance = true;
            return $this->iterator->current();
        } catch (\Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    public function rows(): Generator
    {
        while (($row = $this->fetch()) !== null) {
            yield $row;
        }
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(): array
    {
        return iterator_to_array($this->rows(), false);
    }

    /** @return void */
    public function close(): void
    {
        $this->closed = true;
        $this->iterator = null;
        $this->factory = null;
    }

    /** @return void */
    public function __destruct()
    {
        $this->close();
    }
}
