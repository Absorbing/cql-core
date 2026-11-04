<?php

namespace CQL\Support\Debug;

use CQL\Data\Enums\CSVHeaderMode;
use CQL\Parser\Nodes\Contracts\StatementNodeInterface;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\SelectNode;
use CQL\Parser\Nodes\FromNode;
use CQL\Parser\Nodes\WhereNode;
use CQL\Parser\Nodes\ConditionNode;
use CQL\Parser\Nodes\AliasedColumnNode;
use CQL\Parser\Nodes\ColumnReferenceNode;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Parser\Nodes\FunctionNode;
use CQL\Parser\Nodes\LiteralNode;
use CQL\Parser\Nodes\ParameterNode;
use CQL\Parser\Nodes\WildcardNode;

class PrettyPrinter
{
    /**
     * Print the query node in a human-readable format.
     *
     * @param StatementNodeInterface $queryNode
     * @return string
     */
    public static function print(StatementNodeInterface $queryNode): string
    {
        $output = [];

        if (!$queryNode instanceof QueryNode) {
            // Write statements: fall back to a structural dump for now
            return print_r($queryNode, true);
        }

        $output[] = "QUERY:";

        foreach ($queryNode->defines as $define) {
            $output[] = self::printDefine($define);
        }

        if (isset($queryNode->select)) {
            $output[] = self::printSelect($queryNode->select);
        }

        if (isset($queryNode->from)) {
            $output[] = self::printFrom($queryNode->from);
        }

        if ($queryNode->where) {
            $output[] = self::printWhere($queryNode->where);
        }

        return implode(PHP_EOL, $output);
    }

    /**
     * Print the DEFINE node in a human-readable format.
     *
     * @param DefineNode $defineNode
     * @return string
     */
    protected static function printDefine(DefineNode $defineNode): string
    {
        $columns = implode(', ', $defineNode->columns);

        $hasHeaders = 'WITHOUT HEADERS';

        if ($defineNode->hasHeaders == CSVHeaderMode::WITH_HEADERS) {
            $hasHeaders = 'WITH HEADERS';
        }

        return "DEFINE: {$defineNode->path} AS {$defineNode->alias} {$hasHeaders} COLUMNS ({$columns})";
    }

    /**
     * Print the SELECT node in a human-readable format.
     *
     * @param SelectNode $selectNode
     * @return string
     */
    protected static function printSelect(SelectNode $selectNode): string
    {
        return "SELECT: " . implode(', ', array_map(self::formatCondition(...), $selectNode->columns));
    }

    /**
     * Print the FROM node in a human-readable format.
     *
     * @param FromNode $fromNode
     * @return string
     */
    protected static function printFrom(FromNode $fromNode): string
    {
        return "FROM: {$fromNode->table}";
    }

    /**
     * Print the WHERE node in a human-readable format.
     *
     * @param WhereNode $whereNode
     * @return string
     */
    protected static function printWhere(WhereNode $whereNode): string
    {
        return 'WHERE: ' . self::formatCondition($whereNode->condition);
    }

    /**
     * Recursively format a projection, condition, or expression node.
     *
     * @param mixed $node
     * @return string
     */
    protected static function formatCondition(mixed $node): string
    {
        if ($node instanceof AliasedColumnNode) {
            return $node->expression . ' AS ' . $node->alias;
        }

        if ($node instanceof WildcardNode) {
            return $node->prefix === null ? '*' : $node->prefix . '.*';
        }

        if ($node instanceof FunctionNode) {
            $function = $node->name . '(' . self::formatCondition($node->argument) . ')';
            return $function . ($node->alias === null ? '' : ' AS ' . $node->alias);
        }

        if ($node instanceof LiteralNode) {
            return is_string($node->value)
                ? "'" . str_replace("'", "''", $node->value) . "'"
                : (string)$node;
        }

        if ($node instanceof ColumnReferenceNode) {
            return $node->name;
        }

        if ($node instanceof ParameterNode) {
            return (string)$node;
        }

        if ($node instanceof \CQL\Parser\Nodes\UnaryConditionNode) {
            return "{$node->operator} (" . self::formatCondition($node->operand) . ')';
        }

        if ($node instanceof ConditionNode || $node instanceof ExpressionNode) {
            return '(' . self::formatCondition($node->left)
                . " {$node->operator} "
                . self::formatCondition($node->right) . ')';
        }

        if (is_array($node)) {
            return '(' . implode(', ', array_map(self::formatCondition(...), $node)) . ')';
        }

        if (is_scalar($node)) {
            return (string)$node;
        }

        return get_debug_type($node);
    }
}
