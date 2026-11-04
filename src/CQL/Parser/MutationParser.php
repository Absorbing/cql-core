<?php

namespace CQL\Parser;

use CQL\Parser\Nodes\AssignmentNode;
use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\DeleteNode;
use CQL\Parser\Nodes\InsertNode;
use CQL\Parser\Nodes\UpdateNode;
use CQL\Exceptions\ParserException;
use CQL\Exceptions\SyntaxException;

/** @internal Grammar implementation shared by Parser. */
final class MutationParser
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
     * @param array<DefineNode> $defines Inline sources.
     * @return InsertNode
     */
    public function parseInsert(array $defines): InsertNode
    {
        $this->context->expect('KEYWORD', 'INSERT');
        $this->context->expect('KEYWORD', 'INTO');
        $table = $this->context->expect('IDENTIFIER')->value;
        $columns = $this->parseInsertColumns();
        $this->context->expect('KEYWORD', 'VALUES');
        $rows = [];
        do {
            $values = $this->parseTuple();
            if ($columns !== [] && count($values) !== count($columns)) {
                throw new SyntaxException(sprintf(
                    'VALUES tuple has %d value(s) but %d column(s) were specified at position %d',
                    count($values), count($columns), $this->context->position
                ));
            }
            $rows[] = $values;
        } while ($this->context->consume('COMMA'));
        $this->context->consume('SEMICOLON');
        return new InsertNode($defines, $table, $columns, $rows);
    }

    /** @return array<string> */
    private function parseInsertColumns(): array
    {
        if (!$this->context->consume('LPAREN')) {
            return [];
        }
        $columns = [];
        do {
            $token = $this->context->peek();
            if ($token === null || !in_array($token->type, ['IDENTIFIER', 'STRING'], true)) {
                $type = $token->type ?? 'EOF';
                throw new SyntaxException("Expected column name, got {$type} at position {$this->context->position}");
            }
            $columns[] = trim($this->context->advance()->value, "'");
        } while ($this->context->consume('COMMA'));
        $this->context->expect('RPAREN');
        return $columns;
    }

    /** @return array<mixed> */
    private function parseTuple(): array
    {
        $this->context->expect('LPAREN');
        $values = [];
        do {
            $values[] = $this->expressions->parseExpression();
        } while ($this->context->consume('COMMA'));
        $this->context->expect('RPAREN');
        return $values;
    }

    /**
     * Parse an UPDATE statement.
     *
     * UPDATE alias SET col = expr [, col = expr] [WHERE condition]
     *
     * @param array<DefineNode> $defines
     * @return UpdateNode
     * @throws ParserException
     */
    public function parseUpdate(array $defines): UpdateNode
    {
        $this->context->expect('KEYWORD', 'UPDATE');

        $table = $this->context->expect('IDENTIFIER')->value;

        $this->context->expect('KEYWORD', 'SET');

        $assignments = [];

        do {
            $column = $this->expressions->parseReference();

            $this->context->expect('COMPARISON_OPERATOR', '=');

            $assignments[] = new AssignmentNode($column, $this->expressions->parseExpression());

            if (!$this->context->match('COMMA')) {
                break;
            }

            $this->context->advance();
        } while (true);

        $where = null;

        if ($this->context->match('KEYWORD', 'WHERE')) {
            $where = $this->conditions->parseWhere();
        }

        if ($this->context->match('SEMICOLON')) {
            $this->context->advance();
        }

        return new UpdateNode($defines, $table, $assignments, $where);
    }
    /**
     * Parse a DELETE statement.
     *
     * DELETE FROM alias [WHERE condition]
     *
     * @param array<DefineNode> $defines
     * @return DeleteNode
     * @throws ParserException
     */
    public function parseDelete(array $defines): DeleteNode
    {
        $this->context->expect('KEYWORD', 'DELETE');
        $this->context->expect('KEYWORD', 'FROM');

        $table = $this->context->expect('IDENTIFIER')->value;

        $where = null;

        if ($this->context->match('KEYWORD', 'WHERE')) {
            $where = $this->conditions->parseWhere();
        }

        if ($this->context->match('SEMICOLON')) {
            $this->context->advance();
        }

        return new DeleteNode($defines, $table, $where);
    }
}
