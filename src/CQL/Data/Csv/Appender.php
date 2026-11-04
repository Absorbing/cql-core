<?php

namespace CQL\Data\Csv;

use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;
use Throwable;

/** @internal Checked CSV file operation. */
final class Appender
{
    /**
     * @param string $path Canonical CSV path.
     * @param string $delimiter CSV delimiter.
     */
    public function __construct(private readonly string $path, private readonly string $delimiter)
    {
    }

    /**
     * Append a fully validated batch and restore the original length on failure.
     * The caller holds the canonical sidecar write lock for this entire call.
     * @param array<array<int, string>> $rows Ordered, validated field values.
     * @param array<string> $headers Column names.
     * @param non-negative-int $size Original file size.
     * @param CSVHeaderMode $mode Header mode.
     * @return int
     */
    public function append(array $rows, array $headers, int $size, CSVHeaderMode $mode): int
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new DataSourceException("Unable to open file for writing: {$this->path}");
        }
        try {
            $this->prepareOutput($handle, $headers, $size, $mode);
            foreach ($rows as $row) {
                $this->writeRow($handle, $row);
            }
            if (!fflush($handle)) {
                throw new DataSourceException('Unable to flush appended CSV rows');
            }
            return count($rows);
        } catch (Throwable $error) {
            if (!ftruncate($handle, $size) || !fflush($handle)) {
                throw new DataSourceException('Append failed and restoring the original file length also failed', previous: $error, context: ['path' => $this->path]);
            }
            throw $error;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle Output file.
     * @param array<string> $headers Column names.
     * @param non-negative-int $size Original file size.
     * @param CSVHeaderMode $mode Header mode.
     * @return void
     */
    private function prepareOutput($handle, array $headers, int $size, CSVHeaderMode $mode): void
    {
        if (fseek($handle, 0, SEEK_END) !== 0) {
            throw new DataSourceException('Unable to seek to end of CSV');
        }
        if ($size > 0) {
            $this->ensureNewline($handle);
            return;
        }
        if ($mode === CSVHeaderMode::WITH_HEADERS) {
            $this->writeRow($handle, $headers);
        }
    }

    /**
     * @param resource $handle Non-empty output file.
     * @return void
     */
    private function ensureNewline($handle): void
    {
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
