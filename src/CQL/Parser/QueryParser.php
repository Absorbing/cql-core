<?php

namespace CQL\Parser;

use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\SelectNode;
use CQL\Parser\Nodes\WildcardNode;
use CQL\Lexer\TokenType\Registry\TokenTypeRegistry;
use CQL\Parser\Nodes\FunctionNode;
use CQL\Parser\Nodes\GroupByNode;
use CQL\Parser\Nodes\AliasedColumnNode;

/** @internal Grammar implementation shared by Parser. */
final class QueryParser
{
    /**
     * @param ParseContext $context Shared cursor and binding state.
     * @param ExpressionParser $expressions Scalar expressions and functions.
     * @param ConditionParser $conditions WHERE predicates.
     */
    public function __construct(
        private readonly ParseContext $context,
        private readonly ExpressionParser $expressions,
        private readonly ConditionParser $conditions,
    ) {
    }
    /**
     * Parse a SELECT query.
     *
     * @param array<DefineNode> $defines
     * @return QueryNode
     */
    public function parseQuery(array $defines): QueryNode
    {
        $select = $this->parseSelect();
        $from = (new JoinParser($this->context))->parseFrom();
        $where = null;
        $groupBy = null;

        if ($this->context->match('KEYWORD', 'WHERE')) {
            $where = $this->conditions->parseWhere();
        }

        if ($this->context->match('KEYWORD', 'GROUP')) {
            $groupBy = $this->parseGroupBy();
        }

        if ($this->context->match('SEMICOLON')) {
            $this->context->advance();
        }

        return new QueryNode($defines, $select, $from, $where, $groupBy);
    }

    /** @return SelectNode */
    private function parseSelect(): SelectNode
    {
        $this->context->expect('KEYWORD', 'SELECT');
        $columns = [];
        do {
            $columns[] = $this->parseProjection();
        } while ($this->context->consume('COMMA'));
        return new SelectNode($columns);
    }

    /**
     * Use the lexer registry's structural and wildcard predicates.
     * @return string|AliasedColumnNode|WildcardNode|FunctionNode
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function parseProjection(): string|AliasedColumnNode|WildcardNode|FunctionNode
    {
        $token = $this->context->peek();
        if ($this->expressions->isAggregateFunction($token) || $this->expressions->isDateFunction($token)) {
            return $this->expressions->parseFunction();
        }
        if ($this->isQualifiedWildcard()) {
            $prefix = $this->context->advance()->value;
            $this->context->advance();
            $this->context->advance();
            return new WildcardNode($prefix);
        }
        if ($token !== null && TokenTypeRegistry::isWildcard($token)) {
            $this->context->advance();
            return new WildcardNode();
        }
        $column = $this->expressions->parseReference();
        if ($this->context->consume('KEYWORD', 'AS')) {
            return new AliasedColumnNode($column, $this->context->expect('IDENTIFIER')->value);
        }
        return $column;
    }

    /**
     * Use the lexer registry's structural and wildcard predicates.
     * @return bool
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function isQualifiedWildcard(): bool
    {
        $token = $this->context->peek();
        $next = $this->context->peek(1);
        $after = $this->context->peek(2);
        return $token !== null && $next !== null && $after !== null
            && TokenTypeRegistry::isStructural($next->type) && TokenTypeRegistry::isWildcard($after);
    }

    /** @return GroupByNode */
    private function parseGroupBy(): GroupByNode
    {
        $this->context->expect('KEYWORD', 'GROUP');
        $this->context->expect('KEYWORD', 'BY');
        $columns = [];
        do {
            $token = $this->context->peek();
            $columns[] = $this->expressions->isDateFunction($token) || $this->expressions->isAggregateFunction($token)
                ? $this->expressions->parseFunction() : $this->expressions->parseReference();
        } while ($this->context->consume('COMMA'));
        return new GroupByNode($columns);
    }
}
