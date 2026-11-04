<?php

namespace CQL\Data\Csv;

use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;

/** @internal Checked CSV file operation. */
final class HeaderReader
{
    /**
     * @param string $path Canonical CSV path.
     * @param CSVHeaderMode $mode Header mode.
     * @param string $delimiter CSV delimiter.
     * @param string $alias Query source name for diagnostics.
     */
    public function __construct(private readonly string $path, private readonly CSVHeaderMode $mode, private readonly string $delimiter, private readonly string $alias)
    {
    }

    /** @return array<string>|null */
    public function read(): ?array
    {
        $handle = fopen($this->path, 'r');
        if ($handle === false) {
            throw new DataSourceException("Unable to open file: {$this->path}");
        }
        try {
            $first = fgetcsv($handle, 0, $this->delimiter, '"', "\\");
            if ($this->mode === CSVHeaderMode::WITH_HEADERS) {
                if ($first === false) {
                    throw new DataSourceException("Unable to read headers from file: {$this->path}", context: ['path' => $this->path, 'alias' => $this->alias]);
                }
                return array_map(fn($value) => (string)($value ?? ''), $first);
            }
            return $first === false ? null : array_map(fn($pos) => 'column_' . ($pos + 1), array_keys($first));
        } finally {
            fclose($handle);
        }
    }
}
