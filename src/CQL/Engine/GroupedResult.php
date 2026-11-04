<?php

namespace CQL\Engine;

use CQL\Data\Support\Collection;
use CQL\Engine\Concerns\EvaluatesExpressions;
use CQL\Parser\Nodes\FunctionNode;
use CQL\Parser\Nodes\QueryNode;

/** @internal Query execution component. */
final class GroupedResult
{
    use EvaluatesExpressions;

    /**
     * @param QueryNode $query Parsed grouping and projection.
     * @param array<string|int, string|int|float|bool|null> $parameters Bound values.
     * @param AggregateEvaluator $aggregates Shared aggregate evaluator.
     */
    public function __construct(private readonly QueryNode $query, array $parameters, private readonly AggregateEvaluator $aggregates)
    {
        $this->parameters = $parameters;
    }

    /**
     * @param Collection<array-key, mixed> $rows Filtered input.
     * @return Collection<array-key, mixed>
     */
    public function apply(Collection $rows): Collection
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $this->groupKey($row);
            $encoded = json_encode($key);
            $groups[$encoded] ??= ['key' => $key, 'rows' => []];
            $groups[$encoded]['rows'][] = $row;
        }
        return new Collection(array_values(array_map(fn($group) => $this->aggregateRow($group), $groups)));
    }

    /**
     * @param array<string, mixed> $row Current source row.
     * @return array<mixed>
     */
    private function groupKey(array $row): array
    {
        $key = [];
        foreach ($this->query->groupBy->columns ?? [] as $column) {
            if ($column instanceof FunctionNode) {
                $value = $this->resolveColumnValue($column->argument, $row);
                $key[] = $this->evaluateDateFunction(strtoupper($column->name), $value);
                continue;
            }
            $key[] = $this->resolveColumnValue($column, $row);
        }
        return $key;
    }

    /**
     * @param array{key: array<mixed>, rows: array<array<string, mixed>>} $group Group input and keys.
     * @return array<string, mixed>
     */
    private function aggregateRow(array $group): array
    {
        $row = [];
        foreach ($this->query->groupBy->columns ?? [] as $index => $column) {
            $name = $column instanceof FunctionNode ? ($column->alias ?? strtolower($column->name)) : $this->getShortColumnName($column);
            $row[$name] = $group['key'][$index];
        }
        foreach ($this->query->select->columns as $column) {
            if (!$column instanceof FunctionNode || $this->isGroupingFunction($column)) {
                continue;
            }
            $name = $column->alias ?? strtolower($column->name) . '_' . ($column->argument === '*' ? 'all' : $column->argument);
            $row[$name] = $this->aggregates->evaluate($column, $group['rows']);
        }
        return $row;
    }

    /**
     * @param FunctionNode $column Projection function.
     * @return bool
     */
    private function isGroupingFunction(FunctionNode $column): bool
    {
        foreach ($this->query->groupBy->columns ?? [] as $group) {
            if ($group instanceof FunctionNode && strtoupper($group->name) === strtoupper($column->name) && $group->argument === $column->argument) {
                return true;
            }
        }
        return false;
    }
}
