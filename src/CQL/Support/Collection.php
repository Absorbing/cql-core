<?php

namespace CQL\Support;

class Collection
{
    /**
     * @var array<int, array<string, string>>
     */
    protected array $items = [];

    /**
     * Create a new Collection instance.
     *
     * @param array<int, array<string, string>> $items
     * @return void
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get the items in the collection.
     *
     * @return array<int, array<string, string>>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Map over the items and transform them.
     *
     * @param callable(array<string, string>): mixed $callback
     * @return Collection
     */
    public function map(callable $callback): Collection
    {
        $mapped = array_map($callback, $this->items);
        return new self($mapped);
    }

    /**
     * Filter the items in the collection.
     *
     * @param callable(array<string, string>): bool $callback
     * @return Collection
     */
    public function filter(callable $callback): Collection
    {
        $filtered = array_filter($this->items, $callback);
        return new self(array_values($filtered));
    }

    /**
     * Reduce the items in the collection to a single value.
     *
     * @param callable(mixed, array<string, string>): mixed $callback
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Get the first item in the collection.
     *
     * @return array<string, string>|null
     */
    public function first(): ?array
    {
        return $this->items[0] ?? null;
    }

    /**
     * Get the last item in the collection.
     *
     * @return array<string, string>|null
     */
    public function last(): ?array
    {
        return $this->items[count($this->items) - 1] ?? null;
    }

    /**
     * Get the count of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Check if the collection is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Get the items as an array.
     *
     * @return array<int, array<string, string>>
     */
    public function toArray(): array
    {
        return $this->items;
    }
}