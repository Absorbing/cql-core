<?php

namespace CQL\Engine;

use CQL\Data\CSVDataSource;
use CQL\Engine\Concerns\EvaluatesExpressions;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\Contracts\StatementNodeInterface;
use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\DeleteNode;
use CQL\Parser\Nodes\InsertNode;
use CQL\Parser\Nodes\UpdateNode;
use CQL\Parser\Nodes\WhereNode;
use Generator;

/**
 * Executes write statements (INSERT, UPDATE, DELETE) against CSV data sources.
 *
 * UPDATE and DELETE rewrite the file atomically via a temporary file, and
 * stream row-by-row for large files, mirroring the Interpreter's
 * streaming behaviour.
 *
 * @package CQL\Engine
 */
class Writer
{
    use EvaluatesExpressions;

    /**
     * @var bool
     */
    protected bool $streaming = false;

    /**
     * @var array<string, DefineNode>
     */
    protected array $defineMap = [];

    /**
     * Create a new Writer instance.
     *
     * @param StatementNodeInterface $statement An InsertNode, UpdateNode, or DeleteNode
     * @param bool|null $streamingMode Enable streaming mode (true/false), or null for automatic based on file size
     * @param array<string|int, string|int|float|bool|null> $parameters Bound values.
     * @param int $autoStreamingThreshold File size threshold in bytes for automatic streaming (default: 50MB)
     */
    public function __construct(
        protected StatementNodeInterface $statement,
        protected ?bool $streamingMode = null,
        protected int $autoStreamingThreshold = 52428800,
        array $parameters = []
    ) {
        $this->parameters = $parameters;
        foreach ($statement->getDefines() as $define) {
            $this->defineMap[$define->alias] = $define;
        }
    }

    /**
     * Execute the write statement.
     *
     * @return int Number of affected rows
     * @throws InterpreterException
     */
    public function execute(): int
    {
        return match (true) {
            $this->statement instanceof InsertNode => $this->executeInsert($this->statement),
            $this->statement instanceof UpdateNode => $this->executeUpdate($this->statement),
            $this->statement instanceof DeleteNode => $this->executeDelete($this->statement),
            default => throw new InterpreterException(
                'Writer cannot execute statement of type ' . get_class($this->statement)
            ),
        };
    }

    /**
     * Execute an INSERT statement.
     *
     * @param InsertNode $insert
     * @return int
     * @throws InterpreterException
     */
    protected function executeInsert(InsertNode $insert): int
    {
        $source = $this->resolveSource($insert->table, streaming: true);

        if (($source->getFileSize() ?: 0) > 0) {
            $source->load();
        }

        $headers = $source->getHeaders();
        $rows = [];

        foreach ($insert->rows as $tuple) {
            $values = array_map(
                fn($expression) => $this->normalizeValue($this->evaluateOperand($expression, [])),
                $tuple
            );

            if ($insert->columns !== []) {
                $rows[] = array_combine($insert->columns, $values);
                continue;
            }

            // Positional insert: values must match the file's column count
            if ($headers !== null && count($values) !== count($headers)) {
                throw new InterpreterException(
                    sprintf(
                        'INSERT expects %d value(s) to match columns (%s), got %d',
                        count($headers),
                        implode(', ', $headers),
                        count($values)
                    )
                );
            }

            if ($headers === null) {
                throw new InterpreterException(
                    'Positional INSERT into an empty file requires an explicit column list'
                );
            }

            $rows[] = array_combine($headers, $values);
        }

        return $source->appendRows($rows);
    }

    /**
     * Execute an UPDATE statement.
     *
     * @param UpdateNode $update
     * @return int
     * @throws InterpreterException
     */
    protected function executeUpdate(UpdateNode $update): int
    {
        $source = $this->resolveSource($update->table);
        $source->load();

        $affected = 0;

        $transform = function () use ($source, $update, &$affected): Generator {
            foreach ($this->readRows($source) as $row) {
                if ($this->matchesWhere($update->where, $row)) {
                    foreach ($update->assignments as $assignment) {
                        $key = $this->resolveRowKey($assignment->column, $row, $update->table);
                        $row[$key] = $this->normalizeValue(
                            $this->evaluateOperand($assignment->expression, $row)
                        );
                    }

                    $affected++;
                }

                yield $this->stripNamespace($row, $update->table);
            }
        };

        $source->rewriteFrom($transform());

        return $affected;
    }

    /**
     * Execute a DELETE statement.
     *
     * @param DeleteNode $delete
     * @return int
     * @throws InterpreterException
     */
    protected function executeDelete(DeleteNode $delete): int
    {
        $source = $this->resolveSource($delete->table);
        $source->load();

        $affected = 0;

        $transform = function () use ($source, $delete, &$affected): Generator {
            foreach ($this->readRows($source) as $row) {
                if ($this->matchesWhere($delete->where, $row)) {
                    $affected++;
                    continue;
                }

                yield $this->stripNamespace($row, $delete->table);
            }
        };

        $source->rewriteFrom($transform());

        return $affected;
    }

    /**
     * Resolve the target alias to a data source.
     *
     * @param string $alias
     * @param bool|null $streaming Force a streaming mode, or null to decide from settings/file size
     * @return CSVDataSource
     * @throws InterpreterException
     */
    protected function resolveSource(string $alias, ?bool $streaming = null): CSVDataSource
    {
        if (!isset($this->defineMap[$alias])) {
            throw new InterpreterException("Undefined data source alias '{$alias}'");
        }

        $define = $this->defineMap[$alias];

        if ($streaming === null) {
            if ($this->streamingMode === null) {
                $cleanPath = \CQL\Parser\Nodes\LiteralNode::decode($define->path);
                $fileSize = file_exists($cleanPath) ? filesize($cleanPath) : 0;
                $streaming = $fileSize !== false && $fileSize > $this->autoStreamingThreshold;
            } else {
                $streaming = $this->streamingMode;
            }
        }

        $this->streaming = $streaming;

        return new CSVDataSource(
            $define->path,
            $define->hasHeaders,
            $define->delimiter ?? ',',
            $define->alias,
            $streaming
        );
    }

    /**
     * Read rows from a source, streaming when enabled.
     *
     * @param CSVDataSource $source
     * @return iterable<array<string, string>>
     */
    protected function readRows(CSVDataSource $source): iterable
    {
        return $source->isStreaming() ? $source->streamRows() : $source->getRows();
    }

    /**
     * Check whether a row matches a WHERE clause.
     *
     * @param WhereNode|null $where
     * @param array<string, mixed> $row
     * @return bool
     */
    protected function matchesWhere(?WhereNode $where, array $row): bool
    {
        if ($where === null) {
            return true;
        }

        return $this->evaluateCondition($where->condition, $row);
    }

    /**
     * Resolve an assignment target to the row's namespaced key.
     *
     * @param string $column
     * @param array<string, mixed> $row
     * @param string $alias
     * @return string
     * @throws InterpreterException
     */
    protected function resolveRowKey(string $column, array $row, string $alias): string
    {
        if (array_key_exists($column, $row)) {
            return $column;
        }

        $namespaced = "{$alias}.{$column}";

        if (array_key_exists($namespaced, $row)) {
            return $namespaced;
        }

        $matches = [];

        foreach (array_keys($row) as $key) {
            if (str_ends_with($key, ".{$column}")) {
                $matches[] = $key;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            throw new InterpreterException(
                "Ambiguous column reference '{$column}'. Matches: " . implode(', ', $matches)
            );
        }

        throw new InterpreterException("Unknown column '{$column}' in SET clause");
    }

    /**
     * Strip the alias namespace from a row's keys for writing.
     *
     * @param array<string, mixed> $row
     * @param string $alias
     * @return array<string, mixed>
     */
    protected function stripNamespace(array $row, string $alias): array
    {
        $prefix = "{$alias}.";
        $stripped = [];

        foreach ($row as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $key = substr($key, strlen($prefix));
            }

            $stripped[$key] = $value;
        }

        return $stripped;
    }

    /**
     * Normalize an evaluated value for CSV storage.
     *
     * @param mixed $value
     * @return string
     */
    protected function normalizeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        return (string)$value;
    }

    /**
     * Check if streaming mode was used.
     *
     * @return bool
     */
    public function isStreaming(): bool
    {
        return $this->streaming;
    }
}
