<?php

namespace CQL\Data;

use CQL\Data\Contracts\DataSourceInterface;
use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;
use Generator;

class CSVDataSource implements DataSourceInterface
{
    /**
     * @var array<int, array<string, string>>
     */
    protected array $rows = [];

    /**
     * @var bool
     */
    protected bool $streaming = false;

    /**
     * @var resource|null
     */
    protected $fileHandle = null;

    /**
     * @var array<string>|null
     */
    protected ?array $headers = null;

    /**
     * Create a new CSVDataSource instance.
     *
     * @param string $path
     * @param CSVHeaderMode $hasHeaders
     * @param string $delimiter
     * @param string $alias
     * @param bool $streaming Enable streaming mode for large files
     * @throws DataSourceException
     */
    public function __construct(
        protected string $path,
        protected CSVHeaderMode $hasHeaders = CSVHeaderMode::WITHOUT_HEADERS,
        protected string $delimiter = ',',
        protected string $alias = '',
        bool $streaming = false
    ) {
        $this->path = str_replace(['\'', '"'], '', $this->path);

        if (!file_exists($this->path)) {
            throw new DataSourceException("File not found: {$this->path}");
        }

        if (!is_readable($this->path)) {
            throw new DataSourceException("File not readable: {$this->path}");
        }

        $this->streaming = $streaming;
    }

    /**
     * Load the data source.
     *
     * @return void
     * @throws DataSourceException
     */
    public function load(): void
    {
        if ($this->streaming) {
            $this->loadHeaders();
            return;
        }

        $this->loadIntoMemory();
    }

    /**
     * Load headers only (for streaming mode).
     *
     * @return void
     * @throws DataSourceException
     */
    protected function loadHeaders(): void
    {
        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            throw new DataSourceException("Unable to open file: {$this->path}");
        }

        if ($this->hasHeaders === CSVHeaderMode::WITH_HEADERS) {
            $this->headers = fgetcsv($handle, 0, $this->delimiter);

            if ($this->headers === false) {
                fclose($handle);
                throw new DataSourceException("Unable to read headers from file: {$this->path}");
            }
        } else {
            // Read first row to determine column count
            $firstRow = fgetcsv($handle, 0, $this->delimiter);
            if ($firstRow !== false) {
                $this->headers = array_map(fn($pos) => "column_" . ($pos + 1), array_keys($firstRow));
            }
        }

        fclose($handle);
    }

    /**
     * Load all data into memory (default mode).
     *
     * @return void
     * @throws DataSourceException
     */
    protected function loadIntoMemory(): void
    {
        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            throw new DataSourceException("Unable to open file: {$this->path}");
        }

        $headers = [];

        if ($this->hasHeaders === CSVHeaderMode::WITH_HEADERS) {
            $headers = fgetcsv($handle, 0, $this->delimiter);

            if ($headers === false) {
                throw new DataSourceException("Unable to read headers from file: {$this->path}");
            }
        }

        $index = 0;

        while (($row = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            if ($this->hasHeaders === CSVHeaderMode::WITHOUT_HEADERS && $index === 0) {
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

            $namespacedRow = [];
            foreach ($combined as $key => $value) {
                $namespacedRow["{$this->alias}.{$key}"] = $value;
            }

            $this->rows[] = $namespacedRow;

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
        if ($this->streaming) {
            // Convert generator to array for compatibility
            return iterator_to_array($this->streamRows());
        }

        return $this->rows;
    }

    /**
     * Stream rows one at a time (memory efficient for large files).
     *
     * @return Generator<int, array<string, string>>
     * @throws DataSourceException
     */
    public function streamRows(): Generator
    {
        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            throw new DataSourceException("Unable to open file: {$this->path}");
        }

        // Skip headers if present
        if ($this->hasHeaders === CSVHeaderMode::WITH_HEADERS) {
            fgetcsv($handle, 0, $this->delimiter);
        }

        $index = 0;

        while (($row = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            if ($this->headers === null) {
                throw new DataSourceException("Headers not loaded. Call load() first.");
            }

            if (count($this->headers) !== count($row)) {
                fclose($handle);
                throw new DataSourceException("Row column count mismatch at row {$index}");
            }

            $row = array_map(fn($value) => (string)($value ?? ''), $row);
            $headers = array_map(fn($value) => (string)($value ?? ''), $this->headers);
            $combined = array_combine($headers, $row);

            if (!$combined) {
                fclose($handle);
                throw new DataSourceException("Failed to combine headers and row at index {$index}");
            }

            $namespacedRow = [];
            foreach ($combined as $key => $value) {
                $namespacedRow["{$this->alias}.{$key}"] = $value;
            }

            yield $namespacedRow;
            $index++;
        }

        fclose($handle);
    }

    /**
     * Check if streaming mode is enabled.
     *
     * @return bool
     */
    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * Enable or disable streaming mode.
     * Note: Must be called before load().
     *
     * @param bool $streaming
     * @return void
     */
    public function setStreaming(bool $streaming): void
    {
        $this->streaming = $streaming;
    }

    /**
     * Get the file size in bytes.
     *
     * @return int|false
     */
    public function getFileSize(): int|false
    {
        return filesize($this->path);
    }

    /**
     * Get the file size in a human-readable format.
     *
     * @return string
     */
    public function getFileSizeFormatted(): string
    {
        $bytes = $this->getFileSize();

        if ($bytes === false) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
