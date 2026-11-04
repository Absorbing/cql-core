<?php

namespace CQL\Engine;

use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\AliasedColumnNode;
use CQL\Parser\Nodes\FunctionNode;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\WildcardNode;
use CQL\ResultColumn;

/** @internal Query execution component. */
final class ResultSchema
{
    private readonly bool $aggregateProjection;

    /**
     * @param QueryNode $query Parsed query.
     * @param array<string> $sourceColumns Qualified source column names.
     */
    public function __construct(private readonly QueryNode $query, private readonly array $sourceColumns)
    {
        $this->aggregateProjection = $this->detectAggregates();
    }

    /** @return bool */
    public function hasAggregates(): bool
    {
        return $this->aggregateProjection;
    }

    /** @return bool */
    private function detectAggregates(): bool
    {
        foreach ($this->query->select->columns as $column) {
            if ($column instanceof FunctionNode && in_array(strtoupper($column->name), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'], true)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<ResultColumn> */
    public function getColumns(): array
    {
        $columns = [];
        foreach ($this->query->select->columns as $column) {
            if ($column instanceof FunctionNode) {
                $columns[] = new ResultColumn($this->functionName($column));
                continue;
            }
            if ($this->hasAggregates() && $this->query->groupBy === null) {
                continue;
            }
            if ($column instanceof WildcardNode) {
                array_push($columns, ...$this->wildcardColumns($column));
                continue;
            }
            $columns[] = $this->referenceColumn($column);
        }
        $names = array_column($columns, 'name');
        if (count(array_unique($names)) !== count($names)) {
            throw new InterpreterException('Duplicate result column names; use unique AS aliases');
        }
        return $columns;
    }

    /**
     * Preserve output names for grouping functions and aggregate functions.
     * @param FunctionNode $column Projection function.
     * @return string
     */
    public function functionName(FunctionNode $column): string
    {
        if ($column->alias !== null) {
            return $column->alias;
        }
        $default = strtolower($column->name);
        foreach ($this->query->groupBy->columns ?? [] as $group) {
            if ($group instanceof FunctionNode && $group->name === $column->name && $group->argument == $column->argument) {
                return $default;
            }
        }
        if ($this->hasAggregates() || $this->query->groupBy !== null) {
            return $default . '_' . ($column->argument === '*' ? 'all' : $column->argument);
        }
        return $default;
    }

    /**
     * @param WildcardNode $column Wildcard projection.
     * @return array<ResultColumn>
     */
    private function wildcardColumns(WildcardNode $column): array
    {
        $columns = [];
        foreach ($this->sourceColumns as $key) {
            if ($column->prefix !== null && !str_starts_with($key, $column->prefix . '.')) {
                continue;
            }
            [$alias, $field] = explode('.', $key, 2);
            $columns[] = new ResultColumn($this->wildcardName($key, $column->prefix), $alias, $field);
        }
        return $columns;
    }

    /**
     * Keep duplicate short names qualified, including in wildcard row output.
     * @param string $key Qualified source key.
     * @param string|null $prefix Explicit wildcard prefix.
     * @return string
     */
    public function wildcardName(string $key, ?string $prefix): string
    {
        $short = str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : $key;
        $matches = array_filter($this->sourceColumns, fn($candidate) => str_ends_with($candidate, '.' . $short));
        return $prefix !== null || count($matches) > 1 ? $key : $short;
    }

    /**
     * @param string|AliasedColumnNode $column Named projection.
     * @return ResultColumn
     */
    private function referenceColumn(string|AliasedColumnNode $column): ResultColumn
    {
        $reference = $column instanceof AliasedColumnNode ? $column->expression : $column;
        $name = $column instanceof AliasedColumnNode ? $column->alias : $reference;
        $matches = $this->matchingColumns($reference);
        if (count($matches) > 1) {
            throw new InterpreterException("Ambiguous result column '{$reference}'");
        }
        if ($matches === [] && $this->sourceColumns !== []) {
            throw new InterpreterException("Unknown result column '{$reference}'");
        }
        [$alias, $field] = $matches === [] ? [null, null] : explode('.', $matches[0], 2);
        return new ResultColumn($name, $alias, $field);
    }

    /**
     * @param string $reference Column name.
     * @return list<string>
     */
    private function matchingColumns(string $reference): array
    {
        return array_values(array_filter($this->sourceColumns, fn($key) => $key === $reference
            || (!str_contains($reference, '.') && str_ends_with($key, '.' . $reference))));
    }
}
