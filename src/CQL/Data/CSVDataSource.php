<?php

namespace CQL\Data;

use CQL\Data\Contracts\DataSourceInterface;
use CQL\Exceptions\DataSourceException;

class CSVDataSource implements DataSourceInterface
{
    protected string $path;
    protected string $delimiter;

    /**
     * @var array<int, array<string, string>>
     */
    protected array $rows = [];

    protected bool $hasHeaders;

    public function __construct(string $path, bool $hasHeaders = false, string $delimiter = ',')
    {
        $this->path = $path;
        $this->hasHeaders = $hasHeaders;
        $this->delimiter = $delimiter;

        if (!file_exists($this->path)) {
            throw new DataSourceException("File not found: {$this->path}");
        }

        if (!is_readable($this->path)) {
            throw new DataSourceException("File not readable: {$this->path}");
        }
    }

    public function load(): void
    {
        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            throw new DataSourceException("Unable to open file: {$this->path}");
        }

        $headers = [];

        if ($this->hasHeaders) {
            $headers = fgetcsv($handle, 0, $this->delimiter);

            if ($headers === false) {
                throw new DataSourceException("Unable to read headers from file: {$this->path}");
            }
        }

        $index = 0;

        while (($row = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            if (!$this->hasHeaders && $index === 0) {
                $headers = array_map(fn($i) => "column_" . ($i + 1), array_keys($row));
            }

            if (count($headers) !== count($row)) {
                throw new DataSourceException("Row column count mismatch at row {$index}");
            }

            $row = array_map(fn($value) => (string)($value ?? ''), $row);
            $headers = array_map(fn($value) => (string)($value ?? ''), $headers);
            $combined = array_combine($headers, $row);

            if (!$combined) {
                throw new DataSourceException("Failed to combine headers and row at index {$index}");
            }

            $this->rows[] = $combined;
            $index++;
        }

        fclose($handle);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }
}
