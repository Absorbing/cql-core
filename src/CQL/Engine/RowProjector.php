<?php

namespace CQL\Engine;

use CQL\Engine\Concerns\EvaluatesExpressions;
use CQL\Parser\Nodes\AliasedColumnNode;
use CQL\Parser\Nodes\FunctionNode;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\WildcardNode;

/** @internal Query execution component. */
final class RowProjector
{
    use EvaluatesExpressions;

    /**
     * @param QueryNode $query Parsed projection.
     * @param ResultSchema $schema Shared output naming rules.
     * @param array<string|int, string|int|float|bool|null> $parameters Bound values.
     */
    public function __construct(private readonly QueryNode $query, private readonly ResultSchema $schema, array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @param array<string, mixed> $row Current source or grouped row.
     * @return array<string, mixed>
     */
    public function projectRow(array $row): array
    {
        $result = [];
        foreach ($this->query->select->columns as $column) {
            if ($column instanceof FunctionNode) {
                $name = $this->query->groupBy !== null ? $this->schema->functionName($column) : ($column->alias ?? strtolower($column->name));
                $result[$name] = $this->functionValue($column, $name, $row);
                continue;
            }
            if ($column instanceof WildcardNode) {
                foreach ($this->wildcardValues($column, $row) as $name => $value) {
                    $result[$name] = $value;
                }
                continue;
            }
            $reference = $column instanceof AliasedColumnNode ? $column->expression : $column;
            $name = $column instanceof AliasedColumnNode ? $column->alias : $reference;
            $lookup = $this->query->groupBy !== null ? $this->getShortColumnName($reference) : $reference;
            $result[$name] = $this->resolveColumnValue($lookup, $row);
        }
        return $result;
    }

    /**
     * @param FunctionNode $column Projected function.
     * @param string $name Output name.
     * @param array<string, mixed> $row Current row.
     * @return mixed
     */
    private function functionValue(FunctionNode $column, string $name, array $row): mixed
    {
        if ($this->query->groupBy !== null) {
            return $row[$name] ?? null;
        }
        return $this->evaluateDateFunction(strtoupper($column->name), $this->resolveColumnValue($column->argument, $row));
    }

    /**
     * @param WildcardNode $column Wildcard projection.
     * @param array<string, mixed> $row Current row.
     * @return array<string, mixed>
     */
    private function wildcardValues(WildcardNode $column, array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            if ($column->prefix !== null && !str_starts_with($key, $column->prefix . '.')) {
                continue;
            }
            $result[$this->schema->wildcardName($key, $column->prefix)] = $value;
        }
        return $result;
    }
}
