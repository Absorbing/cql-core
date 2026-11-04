<?php

namespace CQL\Engine\Concerns;

use CQL\Engine\Operators\Contracts\LogicalOperatorInterface;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\ConditionNode;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Parser\Nodes\LiteralNode;
use CQL\Parser\Nodes\ColumnReferenceNode;
use CQL\Parser\Nodes\UnaryConditionNode;

/**
 * Shared expression, operand, and column resolution logic used by both
 * the read Interpreter and the write Writer so that WHERE clauses and
 * expressions behave identically in queries and mutations.
 *
 * @package CQL\Engine\Concerns
 */
trait EvaluatesExpressions
{
    /**
     * Evaluate a condition tree node to a boolean.
     *
     * Handles logical combinations (AND/OR), unary conditions (NOT/EXISTS),
     * comparison predicates, and bare expressions (truthiness).
     *
     * @param mixed $node
     * @param array<string, mixed> $row
     * @return bool
     */
    protected function evaluateCondition(mixed $node, array $row): bool
    {
        if ($node instanceof UnaryConditionNode) {
            $operator = OperatorRegistry::resolveUnary($node->operator);

            // NOT negates a nested condition; EXISTS tests an expression's
            // raw value for presence (so empty string means "not exists").
            $value = $node->operator === 'EXISTS'
                ? $this->evaluateOperand($node->operand, $row)
                : $this->evaluateCondition($node->operand, $row);

            return $operator::evaluate($value);
        }

        if ($node instanceof ConditionNode) {
            $operator = OperatorRegistry::resolveBinary($node->operator);

            // AND/OR combine nested conditions; everything else compares
            // evaluated operand values.
            if ($operator instanceof LogicalOperatorInterface) {
                return (bool)$operator::evaluate(
                    $this->evaluateCondition($node->left, $row),
                    $this->evaluateCondition($node->right, $row)
                );
            }

            return (bool)$operator::evaluate(
                $this->evaluateOperand($node->left, $row),
                $this->evaluateOperand($node->right, $row)
            );
        }

        // Bare expression: evaluate for truthiness
        return (bool)$this->evaluateOperand($node, $row);
    }

    /**
     * Evaluate an operand.
     *
     * @param mixed $operand
     * @param array<string, mixed> $row
     * @return mixed
     */
    protected function evaluateOperand(mixed $operand, array $row): mixed
    {
        if ($operand instanceof LiteralNode) {
            return $operand->value;
        }
        if ($operand instanceof ColumnReferenceNode) {
            return $this->resolveColumnValue($operand->name, $row);
        }
        // Value lists (IN / NOT IN): evaluate each element
        if (is_array($operand)) {
            return array_map(fn($value) => $this->evaluateOperand($value, $row), $operand);
        }

        if ($operand instanceof ExpressionNode) {
            $left = $this->evaluateOperand($operand->left, $row);
            $right = $this->evaluateOperand($operand->right, $row);
            $operator = OperatorRegistry::resolveBinary($operand->operator);

            return $operator::evaluate($left, $right);
        }

        if ($operand instanceof \CQL\Parser\Nodes\FunctionNode) {
            // Evaluate date function on single row
            $value = $this->resolveColumnValue($operand->argument, $row);
            return $this->evaluateDateFunction(strtoupper($operand->name), $value);
        }

        if (is_string($operand)) {
            if (is_numeric($operand)) {
                return $operand + 0;
            }

            // Quoted string literal (quotes are preserved by the tokenizer)
            if (strlen($operand) >= 2 && str_starts_with($operand, "'") && str_ends_with($operand, "'")) {
                return LiteralNode::decode($operand);
            }

            if (isset($row[$operand])) {
                return $row[$operand];
            }

            if (!str_contains($operand, '.')) {
                $matches = [];

                foreach ($row as $key => $value) {
                    if (str_ends_with($key, ".$operand")) {
                        $matches[$key] = $value;
                    }
                }

                if (count($matches) === 1) {
                    return reset($matches); // Unambiguous match
                }

                if (count($matches) > 1) {
                    $options = implode(', ', array_keys($matches));
                    throw new InterpreterException("Ambiguous column reference '$operand'. Matches: $options");
                }

                return null;
            }

            return null;
        }

        return $operand;
    }

    /**
     * Evaluate a date function.
     *
     * @param string $functionName
     * @param mixed $value
     * @return mixed
     */
    protected function evaluateDateFunction(string $functionName, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            $date = new \DateTime($value);

            return match ($functionName) {
                'YEAR' => (int)$date->format('Y'),
                'MONTH' => (int)$date->format('m'),
                'DAY' => (int)$date->format('d'),
                'DATE' => $date->format('Y-m-d'),
                default => null
            };
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resolve a column value from a row.
     *
     * @param mixed $column
     * @param array<string, mixed> $row
     * @return mixed
     */
    protected function resolveColumnValue(mixed $column, array $row): mixed
    {
        if ($column instanceof LiteralNode) {
            return $column->value;
        }
        if ($column instanceof ColumnReferenceNode) {
            $column = $column->name;
        }
        if (!is_string($column)) {
            return null;
        }
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }
        $matches = [];
        if (!str_contains($column, '.')) {
            foreach ($row as $key => $value) {
                if (str_ends_with($key, ".$column")) {
                    $matches[$key] = $value;
                }
            }
        }
        if (count($matches) > 1) {
            throw new InterpreterException("Ambiguous column reference '$column'. Matches: " . implode(', ', array_keys($matches)));
        }
        return $matches === [] ? null : reset($matches);
    }

    /**
     * Get short column name (without table prefix).
     *
     * @param string $column
     * @return string
     */
    protected function getShortColumnName(string $column): string
    {
        if (str_contains($column, '.')) {
            return substr($column, strrpos($column, '.') + 1);
        }
        return $column;
    }
}
