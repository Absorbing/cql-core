<?php

namespace CQL\Data;

use CQL\Data\Contracts\DataSourceInterface;
use CQL\Data\Contracts\SchemaDataSourceInterface;
use CQL\Data\Contracts\StreamingDataSourceInterface;
use CQL\Data\Contracts\WritableDataSourceInterface;
use CQL\Data\Contracts\LockingWritableDataSourceInterface;
use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;
use Generator;

class CSVDataSource implements StreamingDataSourceInterface, LockingWritableDataSourceInterface
{
    private int $writeLockDepth = 0;

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
        $this->path = ((str_starts_with($this->path, "'") || str_starts_with($this->path, '"'))
            ? \CQL\Parser\Nodes\LiteralNode::decode($this->path) : $this->path);

        if (!file_exists($this->path)) {
            throw new DataSourceException("File not found: {$this->path}", context: ['path' => $this->path, 'alias' => $this->alias]);
        }

        if (!is_readable($this->path)) {
            throw new DataSourceException("File not readable: {$this->path}", context: ['path' => $this->path, 'alias' => $this->alias]);
        }

        // All aliases and symlinks to this path use the same stable lock file.
        $this->path = realpath($this->path) ?: $this->path;
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
        $this->rows = [];
        $this->headers = null;
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

        try {
            if ($this->hasHeaders === CSVHeaderMode::WITH_HEADERS) {
                $headers = fgetcsv($handle, 0, $this->delimiter, '"', "\\");
                if ($headers === false) {
                    throw new DataSourceException("Unable to read headers from file: {$this->path}", context: ['path' => $this->path, 'alias' => $this->alias]);
                }
                $this->headers = array_map(fn($value) => (string)($value ?? ''), $headers);
            } else {
                // Read first row to determine column count.
                $firstRow = fgetcsv($handle, 0, $this->delimiter, '"', "\\");
                if ($firstRow !== false) {
                    $this->headers = array_map(fn($pos) => "column_" . ($pos + 1), array_keys($firstRow));
                }
            }
        } finally {
            fclose($handle);
        }
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
            $headers = fgetcsv($handle, 0, $this->delimiter, '"', "\\");

            if ($headers === false) {
                fclose($handle);
                throw new DataSourceException("Unable to read headers from file: {$this->path}", context: ['path' => $this->path, 'alias' => $this->alias]);
            }

            $this->headers = array_map(fn($value) => (string)($value ?? ''), $headers);
        }

        $index = 0;

        while (($row = fgetcsv($handle, 0, $this->delimiter, '"', "\\")) !== false) {
            if ($this->hasHeaders === CSVHeaderMode::WITHOUT_HEADERS && $index === 0) {
                $headers = array_map(fn($pos) => "column_" . ($pos + 1), array_keys($row));
                $this->headers = $headers;
            }

            if (count($headers) !== count($row)) {
                throw new DataSourceException("Row column count mismatch at row {$index}", context: ['path' => $this->path, 'alias' => $this->alias, 'row' => $index]);
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
            throw new DataSourceException("Unable to open file: {$this->path}", context: ['path' => $this->path, 'alias' => $this->alias]);
        }
        try {
            if ($this->hasHeaders === CSVHeaderMode::WITH_HEADERS) {
                fgetcsv($handle, 0, $this->delimiter, '"', "\\");
            }
            $index = 0;
            while (($row = fgetcsv($handle, 0, $this->delimiter, '"', "\\")) !== false) {
                if ($this->headers === null) {
                    throw new DataSourceException('Headers not loaded. Call load() first.');
                }
                if (count($this->headers) !== count($row)) {
                    throw new DataSourceException("Row column count mismatch at row {$index}", context: ['path' => $this->path, 'alias' => $this->alias, 'row' => $index]);
                }
                $namespaced = [];
                foreach ($this->headers as $column => $name) {
                    $namespaced[$this->alias . '.' . $name] = (string)($row[$column] ?? '');
                }
                yield $index => $namespaced;
                $index++;
            }
        } finally {
            fclose($handle);
        }
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
        clearstatcache(true, $this->path);
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

    /**
     * Get the column headers for the data source.
     *
     * Populated by load(). May be null if the file is empty and
     * load() has not been (or could not be) called.
     *
     * @return array<string>|null
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    /**
     * Append rows to the end of the CSV file.
     *
     * Rows are associative arrays of un-namespaced column => value pairs.
     * Columns are mapped to the file's header order; missing columns are
     * written as empty strings, unknown columns throw.
     *
     * If the file is empty, headers are derived from the first row's keys
     * (and written as a header line when the source is WITH HEADERS).
     *
     * @param array<array<string, mixed>> $rows
     * @return int Number of rows appended
     * @throws DataSourceException
     */
    public function appendRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        return $this->withWriteLock(function () use ($rows): int {
            if (!is_writable($this->path)) {
                throw new DataSourceException("File not writable: {$this->path}");
            }
            $size = $this->getFileSize();
            if ($size === false || $size < 0) {
                throw new DataSourceException("Cannot inspect file: {$this->path}");
            }
            if ($size > 0) {
                $this->loadHeaders();
            } else {
                $this->headers = array_map('strval', array_keys($rows[0]));
            }
            // Validate the whole batch before writing any bytes.
            $mapped = array_map(fn(array $row): array => $this->mapRowToHeaders($row), $rows);
            $handle = fopen($this->path, 'c+');
            if ($handle === false) {
                throw new DataSourceException("Unable to open file for writing: {$this->path}");
            }
            try {
                if (fseek($handle, 0, SEEK_END) !== 0) {
                    throw new DataSourceException('Unable to seek to end of CSV');
                }
                if ($size > 0) {
                    if (fseek($handle, -1, SEEK_END) !== 0) {
                        throw new DataSourceException('Unable to inspect trailing newline');
                    }
                    $last = fread($handle, 1);
                    if ($last === false) {
                        throw new DataSourceException('Unable to read trailing byte');
                    }
                    if ($last !== "\n" && fwrite($handle, "\n") !== 1) {
                        throw new DataSourceException('Unable to write trailing newline');
                    }
                } elseif ($this->hasHeaders === CSVHeaderMode::WITH_HEADERS) {
                    $this->writeCsvRow($handle, $this->headers);
                }
                foreach ($mapped as $row) {
                    $this->writeCsvRow($handle, $row);
                }
                if (!fflush($handle)) {
                    throw new DataSourceException('Unable to flush appended CSV rows');
                }
                return count($mapped);
            } catch (\Throwable $error) {
                if (!ftruncate($handle, $size) || !fflush($handle)) {
                    throw new DataSourceException('Append failed and restoring the original file length also failed', previous: $error, context: ['path' => $this->path]);
                }
                throw $error;
            } finally {
                fclose($handle);
            }
        });
    }

    /**
     * The lock lives beside the canonical path and survives file replacement.
     * Do not delete a lock file while another process might be using the CSV.
     * @template T
     * @param callable(): T $operation Complete operation, including reads.
     * @return T
     */
    public function withWriteLock(callable $operation): mixed
    {
        if ($this->writeLockDepth > 0) {
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
        $this->writeLockDepth++;
        try {
            clearstatcache(true, $this->path);
            return $operation();
        } finally {
            $this->writeLockDepth--;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param resource $handle CSV output handle.
     * @param array<string|int|float|bool|null> $row Ordered field values.
     * @return void
     */
    private function writeCsvRow($handle, array $row): void
    {
        if (fputcsv($handle, $row, $this->delimiter, '"', "\\") === false) {
            throw new DataSourceException("Unable to write CSV row: {$this->path}");
        }
    }

    /**
     * Atomically replace the entire contents of the CSV file.
     *
     * Rows are written to a temporary file in the same directory, which is
     * then renamed over the original so readers never see a half-written
     * file and a failure part-way through leaves the original untouched.
     *
     * @param iterable<array<string, mixed>> $rows Un-namespaced rows
     * @return void
     * @throws DataSourceException
     */
    public function rewriteFrom(iterable $rows): void
    {
        $this->withWriteLock(function () use ($rows): void {
            if (!is_writable($this->path) || !is_writable(dirname($this->path))) {
                throw new DataSourceException("File not writable: {$this->path}");
            }
            $tempPath = tempnam(dirname($this->path), '.cql-');
            if ($tempPath === false) {
                throw new DataSourceException('Unable to create temporary CSV');
            }
            $handle = null;
            try {
                $handle = fopen($tempPath, 'w');
                if ($handle === false) {
                    throw new DataSourceException("Unable to open temporary file: {$tempPath}");
                }
                $headersWritten = false;
                if ($this->hasHeaders === CSVHeaderMode::WITH_HEADERS && $this->headers !== null) {
                    $this->writeCsvRow($handle, $this->headers);
                    $headersWritten = true;
                }
                foreach ($rows as $row) {
                    if (!$headersWritten && $this->hasHeaders === CSVHeaderMode::WITH_HEADERS) {
                        $this->headers = array_map('strval', array_keys($row));
                        $this->writeCsvRow($handle, $this->headers);
                        $headersWritten = true;
                    }
                    $this->writeCsvRow($handle, $this->mapRowToHeaders($row));
                }
                if (!fflush($handle)) {
                    throw new DataSourceException('Unable to flush replacement CSV');
                }
                fclose($handle);
                $handle = null;
                $permissions = fileperms($this->path);
                if ($permissions === false || !chmod($tempPath, $permissions & 0777)) {
                    throw new DataSourceException('Unable to preserve CSV permissions');
                }
                if (!rename($tempPath, $this->path)) {
                    throw new DataSourceException("Unable to replace file: {$this->path}");
                }
                clearstatcache(true, $this->path);
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        });
    }

    /**
     * Map an associative row onto the file's header order.
     *
     * @param array<string, mixed> $row
     * @return array<int, string>
     * @throws DataSourceException
     */
    protected function mapRowToHeaders(array $row): array
    {
        $headers = $this->headers ?? array_map('strval', array_keys($row));

        $unknown = array_diff(array_keys($row), $headers);

        if ($unknown !== []) {
            throw new DataSourceException(
                "Unknown column(s) '" . implode("', '", $unknown) . "' for file: {$this->path}"
            );
        }

        $mapped = [];

        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            $mapped[] = is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : (string)$value;
        }

        return $mapped;
    }
}
