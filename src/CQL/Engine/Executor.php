<?php

namespace CQL\Engine;

use CQL\CQL;
use CQL\Data\SourceRegistry;
use CQL\Data\Support\Collection;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\Contracts\StatementNodeInterface;
use CQL\Parser\Nodes\QueryNode;
use CQL\QueryResult;

/** @internal One execution's configuration, shared by prepared and direct calls. */
final class Executor
{
    /**
     * @param bool|null $streaming Requested streaming mode.
     * @param int $threshold Automatic streaming threshold.
     * @param SourceRegistry $sources Connection source registry.
     */
    public function __construct(private readonly ?bool $streaming, private readonly int $threshold, private readonly SourceRegistry $sources)
    {
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
        $interpreter = new Interpreter($ast, $this->streaming, $this->threshold, $parameters, $this->sources);
        $columns = $interpreter->getColumns();
        return new QueryResult($columns, fn(): iterable => $interpreter->rows());
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
            autoStreamingThreshold: $this->threshold,
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
            autoStreamingThreshold: $this->threshold,
            parameters: $parameters,
            sources: $this->sources,
        ))->execute();
    }
}
