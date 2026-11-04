<?php

namespace CQL\Support\Debug;

use CQL\Parser\Nodes\AliasedColumnNode;
use CQL\Parser\Nodes\ColumnReferenceNode;
use CQL\Parser\Nodes\ConditionNode;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Parser\Nodes\FunctionNode;
use CQL\Parser\Nodes\LiteralNode;
use CQL\Parser\Nodes\ParameterNode;
use CQL\Parser\Nodes\UnaryConditionNode;
use CQL\Parser\Nodes\WildcardNode;

/** @internal Pure recursive AST formatting. */
final class NodeFormatter
{
    /**
     * @param mixed $node Structural or scalar node.
     * @return string
     */
    public static function format(mixed $node): string
    {
        if ($node instanceof AliasedColumnNode) {
            return $node->expression . ' AS ' . $node->alias;
        }
        if ($node instanceof WildcardNode) {
            return $node->prefix === null ? '*' : $node->prefix . '.*';
        }
        if ($node instanceof FunctionNode) {
            $function = $node->name . '(' . self::format($node->argument) . ')';
            return $function . ($node->alias === null ? '' : ' AS ' . $node->alias);
        }
        if ($node instanceof UnaryConditionNode) {
            return "{$node->operator} (" . self::format($node->operand) . ')';
        }
        if ($node instanceof ConditionNode || $node instanceof ExpressionNode) {
            return '(' . self::format($node->left) . " {$node->operator} " . self::format($node->right) . ')';
        }
        return self::formatValue($node);
    }

    /**
     * @param mixed $node Leaf value or value list.
     * @return string
     */
    private static function formatValue(mixed $node): string
    {
        if ($node instanceof LiteralNode) {
            return is_string($node->value) ? "'" . str_replace("'", "''", $node->value) . "'" : (string)$node;
        }
        if ($node instanceof ColumnReferenceNode) {
            return $node->name;
        }
        if ($node instanceof ParameterNode) {
            return (string)$node;
        }
        if (is_array($node)) {
            return '(' . implode(', ', array_map(self::format(...), $node)) . ')';
        }
        return is_scalar($node) ? (string)$node : get_debug_type($node);
    }
}
