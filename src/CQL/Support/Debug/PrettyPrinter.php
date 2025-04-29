<?php

namespace CQL\Support\Debug;

use CQL\Data\Enum\CSVHeaderMode;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\SelectNode;
use CQL\Parser\Nodes\FromNode;
use CQL\Parser\Nodes\WhereNode;
use CQL\Parser\Nodes\ConditionNode;

class PrettyPrinter
{
    /**
     * Print the query node in a human-readable format.
     *
     * @param QueryNode $queryNode
     * @return string
     */
    public static function print(QueryNode $queryNode): string
    {
        $output = [];

        $output[] = "QUERY:";

        if (isset($queryNode->define)) {
            $output[] = self::printDefine($queryNode->define);
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
        return "SELECT: " . implode(', ', $selectNode->columns);
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
        $condition = $whereNode->condition;
        return "WHERE: {$condition->left} {$condition->operator} {$condition->right}";
    }
}