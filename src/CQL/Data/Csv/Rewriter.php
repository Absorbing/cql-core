<?php

namespace CQL\Data\Csv;

use Closure;
use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;

/** @internal Checked CSV file operation. */
final class Rewriter
{
    /** @var Closure(array<string, mixed>): array<int, string> */
    private readonly Closure $mapRow;

    /**
     * @param string $path Canonical CSV path.
     * @param string $delimiter CSV delimiter.
     * @param CSVHeaderMode $mode Header mode.
     * @param callable(array<string, mixed>): array<int, string> $mapRow Existing source mapping policy.
     */
    public function __construct(private readonly string $path, private readonly string $delimiter, private readonly CSVHeaderMode $mode, callable $mapRow)
    {
        $this->mapRow = $mapRow(...);
    }

    /**
     * Replace the file atomically while the caller holds its sidecar write lock.
     * @param iterable<array<string, mixed>> $rows Replacement rows.
     * @param array<string>|null $headers Shared source headers, updated when inferred.
     * @return void
     */
    public function rewrite(iterable $rows, ?array &$headers): void
    {
        if (!is_writable($this->path) || !is_writable(dirname($this->path))) {
            throw new DataSourceException("File not writable: {$this->path}");
        }
        $temp = tempnam(dirname($this->path), '.cql-');
        if ($temp === false) {
            throw new DataSourceException('Unable to create temporary CSV');
        }
        $handle = null;
        try {
            $handle = fopen($temp, 'w');
            if ($handle === false) {
                throw new DataSourceException("Unable to open temporary file: {$temp}");
            }
            $this->writeRows($handle, $rows, $headers);
            if (!fflush($handle)) {
                throw new DataSourceException('Unable to flush replacement CSV');
            }
            fclose($handle);
            $handle = null;
            $this->replaceFile($temp);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (file_exists($temp)) {
                unlink($temp);
            }
        }
    }

    /**
     * @param resource $handle Temporary output file.
     * @param iterable<array<string, mixed>> $rows Replacement rows.
     * @param array<string>|null $headers Shared source headers.
     * @return void
     */
    private function writeRows($handle, iterable $rows, ?array &$headers): void
    {
        $headersWritten = false;
        if ($this->mode === CSVHeaderMode::WITH_HEADERS && $headers !== null) {
            $this->writeRow($handle, $headers);
            $headersWritten = true;
        }
        foreach ($rows as $row) {
            if (!$headersWritten && $this->mode === CSVHeaderMode::WITH_HEADERS) {
                $headers = array_map('strval', array_keys($row));
                $this->writeRow($handle, $headers);
                $headersWritten = true;
            }
            $this->writeRow($handle, ($this->mapRow)($row));
        }
    }

    /**
     * @param string $temp Completed temporary file.
     * @return void
     */
    private function replaceFile(string $temp): void
    {
        $permissions = fileperms($this->path);
        if ($permissions === false || !chmod($temp, $permissions & 0777)) {
            throw new DataSourceException('Unable to preserve CSV permissions');
        }
        if (!rename($temp, $this->path)) {
            throw new DataSourceException("Unable to replace file: {$this->path}");
        }
        clearstatcache(true, $this->path);
    }

    /**
     * @param resource $handle Output file.
     * @param array<string> $row Ordered values.
     * @return void
     */
    private function writeRow($handle, array $row): void
    {
        if (fputcsv($handle, $row, $this->delimiter, '"', "\\") === false) {
            throw new DataSourceException("Unable to write CSV row: {$this->path}");
        }
    }
}
