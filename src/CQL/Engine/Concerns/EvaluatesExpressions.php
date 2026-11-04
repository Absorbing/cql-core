<?php

namespace CQL\Engine\Concerns;

use CQL\Engine\Operators\Contracts\LogicalOperatorInterface;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Exceptions\InterpreterException;
use CQL\Parser\Nodes\ConditionNode;
use CQL\Parser\Nodes\FunctionNode;
use DateTime;
use Exception;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Parser\Nodes\LiteralNode;
use CQL\Parser\Nodes\ParameterNode;
use CQL\Exceptions\ParameterException;
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
    /** @var array<string|int, string|int|float|bool|null> */
    protected array $parameters = [];

    /**
     * Evaluate a condition tree node through the extensible operator registry.
     * @SuppressWarnings("PHPMD.StaticAccess")
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
     * Evaluate a typed operand, keeping legacy raw strings compatible.
     * @param mixed $operand Input AST operand.
     * @param array<string, mixed> $row Current row.
     * @return mixed
     */
    protected function evaluateOperand(mixed $operand, array $row): mixed
    {
        if ($operand instanceof ParameterNode) {
            return $this->parameterValue($operand);
        }
        if ($operand instanceof LiteralNode) {
            return $operand->value;
        }
        if ($operand instanceof ColumnReferenceNode) {
            return $this->resolveColumnValue($operand->name, $row);
        }
        if (is_array($operand)) {
            return array_map(fn($value) => $this->evaluateOperand($value, $row), $operand);
        }
        if ($operand instanceof ExpressionNode) {
            return $this->evaluateBinaryExpression($operand, $row);
        }
        if ($operand instanceof FunctionNode) {
            return $this->evaluateDateFunction(strtoupper($operand->name), $this->resolveColumnValue($operand->argument, $row));
        }
        return is_string($operand) ? $this->evaluateLegacyOperand($operand, $row) : $operand;
    }

    /**
     * @param ParameterNode $operand Binding reference.
     * @return string|int|float|bool|null
     */
    private function parameterValue(ParameterNode $operand): string|int|float|bool|null
    {
        if (!array_key_exists($operand->key, $this->parameters)) {
            throw new ParameterException("Missing parameter '{$operand->key}'", position: $operand->position);
        }
        return $this->parameters[$operand->key];
    }

    /**
     * Resolve arithmetic operators through the existing registry extension point.
     * @param ExpressionNode $operand Binary expression.
     * @param array<string, mixed> $row Current row.
     * @return mixed
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function evaluateBinaryExpression(ExpressionNode $operand, array $row): mixed
    {
        $left = $this->evaluateOperand($operand->left, $row);
        $right = $this->evaluateOperand($operand->right, $row);
        $operator = OperatorRegistry::resolveBinary($operand->operator);
        return $operator::evaluate($left, $right);
    }

    /**
     * Preserve raw string operands accepted by the earlier AST API.
     * SQL decoding is a pure utility on LiteralNode.
     * @param string $operand Legacy operand.
     * @param array<string, mixed> $row Current row.
     * @return mixed
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function evaluateLegacyOperand(string $operand, array $row): mixed
    {
        if (is_numeric($operand)) {
            return $operand + 0;
        }
        if (strlen($operand) >= 2 && str_starts_with($operand, "'") && str_ends_with($operand, "'")) {
            return LiteralNode::decode($operand);
        }
        if (isset($row[$operand])) {
            return $row[$operand];
        }
        return $this->resolveUnqualifiedColumn($operand, $row);
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
            $date = new DateTime($value);

            return match ($functionName) {
                'YEAR' => (int)$date->format('Y'),
                'MONTH' => (int)$date->format('m'),
                'DAY' => (int)$date->format('d'),
                'DATE' => $date->format('Y-m-d'),
                default => null
            };
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Resolve typed or raw column references, including null-valued columns.
     * @param mixed $column Column or scalar AST node.
     * @param array<string, mixed> $row Current row.
     * @return mixed
     */
    protected function resolveColumnValue(mixed $column, array $row): mixed
    {
        if ($column instanceof ParameterNode) {
            return $this->parameterValue($column);
        }
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
        return $this->resolveUnqualifiedColumn($column, $row);
    }

    /**
     * Reject ambiguous suffix matches instead of picking an arbitrary source.
     * @param string $column Raw reference.
     * @param array<string, mixed> $row Current row.
     * @return mixed
     */
    private function resolveUnqualifiedColumn(string $column, array $row): mixed
    {
        if (str_contains($column, '.')) {
            return null;
        }
        $matches = [];
        foreach ($row as $key => $value) {
            if (str_ends_with($key, ".$column")) {
                $matches[$key] = $value;
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
