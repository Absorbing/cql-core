<?php

namespace CQL\Engine;

use CQL\Engine\Concerns\EvaluatesExpressions;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\FunctionNode;

/** @internal Query execution component. */
final class AggregateEvaluator
{
    use EvaluatesExpressions;

    /**
     * @param array<string|int, string|int|float|bool|null> $parameters Bound values.
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @param FunctionNode $function Aggregate or grouped date function.
     * @param array<array<string, mixed>> $rows Group input.
     * @return mixed
     */
    public function evaluate(FunctionNode $function, array $rows): mixed
    {
        $name = strtoupper($function->name);
        return match ($name) {
            'COUNT' => $function->argument === '*' ? count($rows) : $this->countValues($function, $rows),
            'SUM' => $this->numericStats($function, $rows)['sum'],
            'AVG' => $this->average($function, $rows),
            'MIN', 'MAX' => $this->extremeValue($function, $rows, $name),
            'YEAR', 'MONTH', 'DAY' => $rows === [] ? null : $this->evaluateDateFunction($name, $this->resolveColumnValue($function->argument, $rows[0])),
            default => throw new InterpreterException("Unknown aggregate function: {$name}"),
        };
    }

    /**
     * @param FunctionNode $function COUNT expression.
     * @param array<array<string, mixed>> $rows Group input.
     * @return int
     */
    private function countValues(FunctionNode $function, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if ($this->resolveColumnValue($function->argument, $row) !== null) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param FunctionNode $function SUM or AVG expression.
     * @param array<array<string, mixed>> $rows Group input.
     * @return array{sum: int|float, count: int}
     */
    private function numericStats(FunctionNode $function, array $rows): array
    {
        $sum = 0;
        $count = 0;
        foreach ($rows as $row) {
            $value = $this->resolveColumnValue($function->argument, $row);
            if (is_numeric($value)) {
                $sum += (float)$value;
                $count++;
            }
        }
        return ['sum' => $sum, 'count' => $count];
    }

    /**
     * @param FunctionNode $function AVG expression.
     * @param array<array<string, mixed>> $rows Group input.
     * @return float|null
     */
    private function average(FunctionNode $function, array $rows): ?float
    {
        $stats = $this->numericStats($function, $rows);
        return $stats['count'] > 0 ? $stats['sum'] / $stats['count'] : null;
    }

    /**
     * @param FunctionNode $function MIN or MAX expression.
     * @param array<array<string, mixed>> $rows Group input.
     * @param string $direction MIN or MAX comparison.
     * @return mixed
     */
    private function extremeValue(FunctionNode $function, array $rows, string $direction): mixed
    {
        $best = null;
        foreach ($rows as $row) {
            $value = $this->resolveColumnValue($function->argument, $row);
            if (!is_numeric($value) && !is_string($value)) {
                continue;
            }
            if ($best === null || ($direction === 'MIN' ? $value < $best : $value > $best)) {
                $best = $value;
            }
        }
        return $best;
    }
}
