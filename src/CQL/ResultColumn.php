<?php

namespace CQL;

/** Ordered output metadata, independent of whether any rows matched. */
readonly class ResultColumn
{
    /**
     * @param string $name Output column name.
     * @param string|null $sourceAlias Originating source, or null for computed values.
     * @param string|null $sourceColumn Originating field, or null for computed values.
     */
    public function __construct(
        public string $name,
        public ?string $sourceAlias = null,
        public ?string $sourceColumn = null,
    ) {
    }
}
