<?php

namespace CQL\Data;

use CQL\Data\Contracts\StreamingDataSourceInterface;
use CQL\Data\Contracts\LockingWritableDataSourceInterface;
use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;
use Generator;
use CQL\Data\Csv\HeaderReader;
use CQL\Data\Csv\Appender;
use CQL\Data\Csv\Rewriter;
use CQL\Data\Csv\WriteLock;
use CQL\Parser\Nodes\LiteralNode;

class CSVDataSource implements StreamingDataSourceInterface, LockingWritableDataSourceInterface
{
    private readonly WriteLock $writeLock;

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
     * Preserve the public streaming flag and use the pure SQL path decoder.
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     * @SuppressWarnings("PHPMD.StaticAccess")
     *
     * @param string $path
     * @param CSVHeaderMode $hasHeaders
     * @param string $delimiter
     * @param string $alias
     * @param bool $streaming Enable streaming mode for large files.
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
            ? LiteralNode::decode($this->path) : $this->path);

        if (!file_exists($this->path)) {
            throw new DataSourceException("File not found: {$this->path}", context: ['path' => $this->path, 'alias' => $this->alias]);
        }

        if (!is_readable($this->path)) {
            throw new DataSourceException("File not readable: {$this->path}", context: ['path' => $this->path, 'alias' => $this->alias]);
        }

        // All aliases and symlinks to this path use the same stable lock file.
        $this->path = realpath($this->path) ?: $this->path;
        $this->streaming = $streaming;
        $this->writeLock = new WriteLock($this->path);
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

    /** @return void */
    protected function loadHeaders(): void
    {
        $this->headers = (new HeaderReader($this->path, $this->hasHeaders, $this->delimiter, $this->alias))->read();
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
     * Validate the complete batch under the same lock used for file output.
     * @param array<array<string, mixed>> $rows Unqualified rows to append.
     * @return int
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
            if ($size === 0) {
                $this->headers = array_map('strval', array_keys($rows[0]));
            }
            if ($size > 0) {
                $this->loadHeaders();
            }
            $mapped = array_map(fn(array $row): array => $this->mapRowToHeaders($row), $rows);
            return (new Appender($this->path, $this->delimiter))->append($mapped, $this->headers ?? [], $size, $this->hasHeaders);
        });
    }

    /**
     * The canonical-path lock survives atomic file replacement.
     * @template T
     * @param callable(): T $operation Complete mutation including its reads.
     * @return T
     */
    public function withWriteLock(callable $operation): mixed
    {
        return $this->writeLock->run($operation);
    }



    /**
     * Replace the file atomically while preserving header mapping and permissions.
     * @param iterable<array<string, mixed>> $rows Unqualified replacement rows.
     * @return void
     */
    public function rewriteFrom(iterable $rows): void
    {
        $this->withWriteLock(function () use ($rows): void {
            $writer = new Rewriter($this->path, $this->delimiter, $this->hasHeaders, fn(array $row): array => $this->mapRowToHeaders($row));
            $writer->rewrite($rows, $this->headers);
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
