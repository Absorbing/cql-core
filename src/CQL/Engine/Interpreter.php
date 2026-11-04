<?php

namespace CQL\Engine;

use CQL\Data\Support\Collection;
use CQL\Engine\Concerns\EvaluatesExpressions;
use CQL\Data\SourceRegistry;
use CQL\Data\SourceHandle;
use CQL\ResultColumn;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\FunctionNode;

class Interpreter
{
    protected SourceHandle $source;
    private readonly ResultSchema $schema;
    private readonly RowProjector $projector;
    private readonly AggregateEvaluator $aggregates;
    /** @var array<int, SourceHandle> */
    protected array $joinSources = [];

    /** @var array<string> */
    protected array $sourceColumns = [];

    use EvaluatesExpressions;

    /**
     * @var Collection<array-key, mixed>
     */
    protected Collection $collection;

    /**
     * @var bool
     */
    protected bool $streaming = false;

    /**
     * @var int|null Threshold in bytes for automatic streaming mode (null = disabled)
     */
    protected ?int $streamThreshold = null;

    /**
     * Create a new Interpreter instance.
     * Keep the existing named threshold argument compatible.
     * @SuppressWarnings("PHPMD.LongVariable")
     *
     * @param QueryNode $query
     * @param bool|null $streaming Enable streaming mode (true/false), or null for automatic based on file size.
     * @param int $autoStreamingThreshold File size threshold in bytes for automatic streaming (default: 50MB).
     * @param array<string|int, string|int|float|bool|null> $parameters Bound values.
     */
    public function __construct(
        protected QueryNode $query,
        ?bool $streaming = null,
        int $autoStreamingThreshold = 52428800,
        array $parameters = [],
        ?SourceRegistry $sources = null,
    ) {
        $this->parameters = $parameters;
        $this->streamThreshold = $autoStreamingThreshold;

        $sources ??= new SourceRegistry();
        $source = $sources->resolve($query->from->table, $query->defines, $streaming, $autoStreamingThreshold);
        $this->streaming = $source->isStreaming();

        $source->load();
        foreach ($source->getHeaders() ?? [] as $column) {
            $this->sourceColumns[] = $query->from->table . '.' . $column;
        }
        $this->source = $source;

        foreach ($query->from->joins as $join) {
            $rightSource = $sources->resolve($join->rightAlias, $query->defines, $streaming, $autoStreamingThreshold);

            $rightSource->load();
            foreach ($rightSource->getHeaders() ?? [] as $column) {
                $this->sourceColumns[] = $join->rightAlias . '.' . $column;
            }
            $this->joinSources[] = $rightSource;
        }
        $this->schema = new ResultSchema($query, $this->sourceColumns);
        $this->projector = new RowProjector($query, $this->schema, $parameters);
        $this->aggregates = new AggregateEvaluator($parameters);
    }

    /**
     * Execute the query.
     *
     * @return Collection<array-key, mixed>
     */
    public function execute(): Collection
    {
        return new Collection(iterator_to_array($this->rows(), true));
    }

    /** @return \Generator<array-key, array<string, mixed>> */
    public function rows(): \Generator
    {
        if (!$this->requiresBuffering()) {
            yield from $this->streamProjectedRows();
            return;
        }
        $this->collection = new Collection($this->source->getRows());
        $this->applyJoins();
        if ($this->query->where !== null) {
            $this->applyWhere();
        }
        if ($this->query->groupBy !== null) {
            $this->applyGroupBy();
        }
        $this->applySelect();
        yield from $this->collection;
    }

    /** @return bool */
    private function requiresBuffering(): bool
    {
        return $this->query->from->joins !== [] || $this->query->groupBy !== null || $this->schema->hasAggregates();
    }

    /** @return \Generator<array-key, array<string, mixed>> */
    private function streamProjectedRows(): \Generator
    {
        foreach ($this->source->rows() as $key => $row) {
            if ($this->query->where === null || $this->evaluateCondition($this->query->where->condition, $row)) {
                yield $key => $this->projectRow($row);
            }
        }
    }

    /** @return void */
    private function applyJoins(): void
    {
        foreach ($this->query->from->joins as $index => $join) {
            $rightIndex = [];
            foreach ($this->joinSources[$index]->rows() as $row) {
                $key = $row[$join->rightAlias . '.' . $join->rightKey] ?? null;
                if ($key !== null) {
                    $rightIndex[$key][] = $row;
                }
            }
            $this->collection = $this->collection->flatMap(function ($leftRow) use ($join, $rightIndex) {
                $key = $leftRow[$join->leftAlias . '.' . $join->leftKey] ?? null;
                return array_map(fn($rightRow) => array_merge($leftRow, $rightRow), $rightIndex[$key] ?? []);
            });
        }
    }

    /** @return array<ResultColumn> */
    public function getColumns(): array
    {
        return $this->schema->getColumns();
    }

    /**
     * Apply the WHERE clause.
     *
     * @return void
     */
    protected function applyWhere(): void
    {
        if (!$this->query->where) {
            return;
        }

        $condition = $this->query->where->condition;

        $this->collection = $this->collection->filter(
            fn($row): bool => $this->evaluateCondition($condition, $row)
        );
    }

    /** @return void */
    protected function applySelect(): void
    {
        if ($this->query->groupBy === null && $this->schema->hasAggregates()) {
            $this->applyAggregatesWithoutGroupBy();
            return;
        }
        $this->collection = $this->collection->map(fn(array $row): array => $this->projectRow($row));
    }

    /**
     * @param array<string, mixed> $row Current row.
     * @return array<string, mixed>
     */
    protected function projectRow(array $row): array
    {
        return $this->projector->projectRow($row);
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
     * Get the auto-streaming threshold in bytes.
     *
     * @return int|null
     */
    public function getAutoStreamingThreshold(): ?int
    {
        return $this->streamThreshold;
    }

    /** @return void */
    protected function applyGroupBy(): void
    {
        if ($this->query->groupBy !== null) {
            $this->collection = (new GroupedResult($this->query, $this->parameters, $this->aggregates))->apply($this->collection);
        }
    }

    /**
     * Apply aggregates without GROUP BY (aggregate entire dataset).
     *
     * @return void
     */
    protected function applyAggregatesWithoutGroupBy(): void
    {
        $allRows = $this->collection->toArray();
        $result = [];

        foreach ($this->query->select->columns as $column) {
            if ($column instanceof FunctionNode) {
                $value = $this->evaluateAggregate($column, $allRows);
                $outputKey = $column->alias ?? strtolower($column->name) . '_' . ($column->argument === '*' ? 'all' : $column->argument);
                $result[$outputKey] = $value;
            }
        }

        $this->collection = new Collection([$result]);
    }

    /**
     * @param FunctionNode $function Group function.
     * @param array<array<string, mixed>> $rows Input group.
     * @return mixed
     */
    protected function evaluateAggregate(FunctionNode $function, array $rows): mixed
    {
        return $this->aggregates->evaluate($function, $rows);
    }

}
