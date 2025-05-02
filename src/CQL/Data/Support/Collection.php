<?php

namespace CQL\Data\Support;

use ArrayIterator;
use IteratorAggregate;

/**
 * @template TKey of array-key
 * @template TValue
 * @implements IteratorAggregate<TKey, TValue>
 */
class Collection implements IteratorAggregate
{
    /**
     * Create a new Collection instance
     *
     * @param array<TKey, TValue> $items
     */
    public function __construct(
        protected array $items
    ) {
    }

    /**
     * Filter the collection using a callback function
     *
     * @param callable(TValue, TKey): bool $callback
     * @return Collection<TKey, TValue>
     */
    public function filter(callable $callback): Collection
    {
        $filtered = array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH);
        return new self($filtered); // Preserve keys
    }

    /**
     * Map over the Collection and apply a transformation.
     *
     * @param callable(TValue): mixed $callback
     * @return Collection<TKey, mixed>
     */
    public function map(callable $callback): Collection
    {
        $mapped = array_map($callback, $this->items);
        return new self($mapped); // Create a new collection with transformed values
    }

    /**
     * Get an iterator for the Collection
     *
     * @return ArrayIterator<TKey, TValue>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Get the items as an array.
     *
     * @return array<TKey, TValue>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Get a count of the items in the Collection
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }
}