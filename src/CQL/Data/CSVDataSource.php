<?php

namespace CQL\Data;

use CQL\Data\Contracts\DataSourceInterface;
use CQL\Data\Enum\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;

class CSVDataSource implements DataSourceInterface
{
    /**
     * @var string
     */
    protected string $path;

    /**
     * @var string
     */
    protected string $delimiter;

    /**
     * @var array<int, array<string, string>>
     */
    protected array $rows = [];

    /**
     * @var CSVHeaderMode
     */
    protected CSVHeaderMode $hasHeaders;

    /**
     * Create a new CSVDataSource instance.
     *
     * @param string $path
     * @param CSVHeaderMode $hasHeaders
     * @param string $delimiter
     */
    public function __construct(
        string $path,
        CSVHeaderMode $hasHeaders = CSVHeaderMode::WITHOUT_HEADERS,
        string $delimiter = ','
    ) {
        $this->path = $path;
        $this->hasHeaders = $hasHeaders;
        $this->delimiter = $delimiter;

        // strip single and double quotes from path
        $this->path = str_replace(['\'', '"'], '', $this->path);

        if (!file_exists($this->path)) {
            throw new DataSourceException("File not found: {$this->path}");
        }

        if (!is_readable($this->path)) {
            throw new DataSourceException("File not readable: {$this->path}");
        }
    }

    /**
     * Load the data source.
     *
     * @throws DataSourceException
     */
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
                $headers = array_map(fn($pos) => "column_" . ($pos + 1), array_keys($row));
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
     * Get all rows from the data source.
     *
     * @return array<int, array<string, string>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }
}
