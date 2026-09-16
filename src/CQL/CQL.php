<?php

namespace CQL;

use CQL\Data\Support\Collection;
use CQL\Data\Contracts\DataSourceInterface;
use CQL\Data\SourceRegistry;
use CQL\Engine\Interpreter;
use CQL\Engine\Writer;
use CQL\Exceptions\LexerException;
use CQL\Exceptions\ParserException;
use CQL\Exceptions\SyntaxException;
use CQL\Exceptions\InterpreterException;
use CQL\Exceptions\DataSourceException;
use CQL\Lexer\Tokenizer;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\Contracts\StatementNodeInterface;
use CQL\Parser\Parser;

/**
 * CQL - CSV Query Language
 * 
 * A simple facade for executing CQL queries against CSV files.
 * 
 * @package CQL
 */
class CQL
{
    protected SourceRegistry $sources;

    /**
     * Streaming mode setting
     * - null: Automatic (default, based on file size)
     * - true: Always stream
     * - false: Never stream (load into memory)
     */
    protected ?bool $streaming = null;

    /**
     * File size threshold in bytes for automatic streaming mode
     */
    protected int $autoStreamingThreshold = 52428800; // 50 MB

    /**
     * Create a new CQL instance.
     *
     * @param array{streaming?: bool|null, autoStreamingThreshold?: int} $options Configuration options.
     */
    public function __construct(array $options = [])
    {
        $this->sources = new SourceRegistry();
        $this->streaming = $options['streaming'] ?? null;
        $this->autoStreamingThreshold = $options['autoStreamingThreshold'] ?? 52428800;
    }

    /**
     * @param string $alias Query source name.
     * @param string $path Literal filesystem path.
     * @param bool $headers Whether the first row contains column names.
     * @param string $delimiter CSV delimiter.
     * @return self
     */
    public function registerCsv(string $alias, string $path, bool $headers = true, string $delimiter = ','): self
    {
        $this->sources->registerCsv($alias, $path, $headers, $delimiter);
        return $this;
    }

    /**
     * @param string $alias Query source name.
     * @param callable(): DataSourceInterface $factory Fresh source factory using unqualified row keys.
     * @return self
     */
    public function registerSource(string $alias, callable $factory): self
    {
        $this->sources->register($alias, $factory);
        return $this;
    }

    /**
     * @param string $alias Query source name.
     * @return self
     */
    public function unregisterSource(string $alias): self
    {
        $this->sources->unregister($alias);
        return $this;
    }

    /**
     * Execute a CQL query and return results.
     *
     * @param string $query The CQL query to execute.
     * @return Collection<array-key, mixed> Query results
     * @throws LexerException
     * @throws ParserException
     * @throws SyntaxException
     * @throws InterpreterException
     * @throws DataSourceException
     */
    public function execute(string $query): Collection
    {
        return $this->prepare($query)->execute();
    }

    /**
     * Parse without reading CSV rows or executing writes.
     * @param string $query Statement template.
     * @return PreparedQuery
     */
    public function prepare(string $query): PreparedQuery
    {
        $parser = new Parser((new Tokenizer($query))->tokenize(), allowMissingDefines: true);
        $statement = $parser->parse();
        return new PreparedQuery($this, $statement, $parser->getParameters());
    }

    /**
     * Execute with metadata and a forward-only result cursor.
     * @param string $query Statement template.
     * @param array<string|int, mixed> $parameters Bound values.
     * @return QueryResult
     */
    public function run(string $query, array $parameters = []): QueryResult
    {
        return $this->prepare($query)->run($parameters);
    }

    /**
     * @internal
     * @param StatementNodeInterface $ast Parsed statement.
     * @param array<string|int, string|int|float|bool|null> $parameters Bound values.
     * @return QueryResult
     */
    public function runParsed(StatementNodeInterface $ast, array $parameters = []): QueryResult
    {
        if (!$ast instanceof QueryNode) {
            return new QueryResult([], affectedRows: $this->writeParsed($ast, $parameters));
        }
        $interpreter = new Interpreter($ast, $this->streaming, $this->autoStreamingThreshold, $parameters, $this->sources);
        $columns = $interpreter->getColumns();
        return new QueryResult($columns, $interpreter->execute());
    }

    /**
     * Execute an already parsed statement. Used by PreparedQuery.
     * @internal
     * @param StatementNodeInterface $ast Parsed statement.
     * @param array<string|int, string|int|float|bool|null> $parameters Bound values.
     * @return Collection<array-key, mixed>
     */
    public function executeParsed(StatementNodeInterface $ast, array $parameters = []): Collection
    {
        if (!$ast instanceof QueryNode) {
            return new Collection([['affected_rows' => $this->writeParsed($ast, $parameters)]]);
        }
        return (new Interpreter(
            $ast,
            streaming: $this->streaming,
            autoStreamingThreshold: $this->autoStreamingThreshold,
            parameters: $parameters,
            sources: $this->sources,
        ))->execute();
    }

    /**
     * Execute an already parsed mutation. Used by PreparedQuery.
     * @internal
     * @param StatementNodeInterface $ast Parsed mutation.
     * @param array<string|int, string|int|float|bool|null> $parameters Bound values.
     * @return int Number of affected rows.
     */
    public function writeParsed(StatementNodeInterface $ast, array $parameters = []): int
    {
        if ($ast instanceof QueryNode) {
            throw new InterpreterException('statement() expects a write statement (INSERT, UPDATE, DELETE). Use execute() for queries.');
        }
        return (new Writer(
            $ast,
            streamingMode: $this->streaming,
            autoStreamingThreshold: $this->autoStreamingThreshold,
            parameters: $parameters,
            sources: $this->sources,
        ))->execute();
    }

    /**
     * Execute a write statement (INSERT, UPDATE, DELETE) and return
     * the number of affected rows.
     *
     * @param string $query The CQL statement to execute.
     * @return int Number of affected rows
     * @throws InterpreterException If the statement is a SELECT query.
     */
    public function statement(string $query): int
    {
        return $this->prepare($query)->statement();
    }

    /**
     * Execute a query and return results as an array.
     *
     * @param string $query The CQL query to execute.
     * @return array<array-key, mixed>
     */
    public function query(string $query): array
    {
        return $this->execute($query)->toArray();
    }

    /**
     * Execute a query and return the first result.
     *
     * @param string $query The CQL query to execute.
     * @return mixed|null
     */
    public function first(string $query): mixed
    {
        return $this->execute($query)->first();
    }

    /**
     * Execute a query and return the count of results.
     *
     * @param string $query The CQL query to execute.
     * @return int
     */
    public function count(string $query): int
    {
        return $this->execute($query)->count();
    }

    /**
     * Set streaming mode.
     *
     * @param bool|null $streaming
     * @return self
     */
    public function setStreaming(?bool $streaming): self
    {
        $this->streaming = $streaming;
        return $this;
    }

    /**
     * Set auto-streaming threshold.
     *
     * @param int $bytes File size threshold in bytes.
     * @return self
     */
    public function setAutoStreamingThreshold(int $bytes): self
    {
        $this->autoStreamingThreshold = $bytes;
        return $this;
    }

    /**
     * Get current streaming mode setting.
     *
     * @return bool|null
     */
    public function getStreaming(): ?bool
    {
        return $this->streaming;
    }

    /**
     * Get auto-streaming threshold.
     *
     * @return int
     */
    public function getAutoStreamingThreshold(): int
    {
        return $this->autoStreamingThreshold;
    }

    /**
     * Create a CQL instance with streaming enabled.
     *
     * @return self
     */
    public static function streaming(): self
    {
        return new self(['streaming' => true]);
    }

    /**
     * Create a CQL instance with streaming disabled.
     *
     * @return self
     */
    public static function normal(): self
    {
        return new self(['streaming' => false]);
    }

    /**
     * Create a CQL instance with automatic mode (default).
     *
     * @param int|null $threshold Optional custom threshold in bytes.
     * @return self
     */
    public static function auto(?int $threshold = null): self
    {
        $options = ['streaming' => null];
        if ($threshold !== null) {
            $options['autoStreamingThreshold'] = $threshold;
        }
        return new self($options);
    }
}
