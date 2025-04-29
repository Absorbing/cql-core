<?php

namespace CQL\Parser;

use CQL\Lexer\Token;
use CQL\Parser\Nodes\ConditionNode;
use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\FromNode;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\SelectNode;
use CQL\Parser\Nodes\WhereNode;
use CQL\Exceptions\ParserException;
use CQL\Exceptions\SyntaxException;

class Parser
{
    /**
     * @var array<Token>
     */
    protected array $tokens;

    /**
     * @var int
     */
    protected int $position = 0;

    /**
     * Create a new Parser instance.
     *
     * @param array<Token> $tokens
     * @return void
     */
    public function __construct(array $tokens)
    {
        if (empty($tokens)) {
            throw new ParserException("The provided tokens must be an array.");
        }

        $this->tokens = $tokens;
    }


    public function parse(): QueryNode
    {
        if (!$this->match('KEYWORD', 'DEFINE')) {
            throw new SyntaxException(
                "Expected query to start with 'DEFINE' keyword, found '{$this->tokens[$this->position]->type}({$this->tokens[$this->position]->value})' at position {$this->position}"
            );
        }

        return $this->parseQuery();
    }

    /**
     * Parse the query.
     *
     * @return QueryNode
     */
    protected function parseQuery(): QueryNode
    {
        $define = $this->parseDefine();
        $select = $this->parseSelect();
        $from = $this->parseFrom();
        $where = null;

        if ($this->match('KEYWORD', 'WHERE')) {
            $where = $this->parseWhere();
        }

        if ($this->match('SEMICOLON')) {
            $this->advance();
        }

        return new QueryNode($define, $select, $from, $where);
    }

    /**
     * Parse the SELECT statement.
     *
     * @return SelectNode
     */
    protected function parseSelect(): SelectNode
    {
        $this->expect('KEYWORD', 'SELECT');

        $columns = [];

        do {
            $token = $this->expect('IDENTIFIER');

            $columns[] = $token->value;

            if (!$this->match('COMMA')) {
                break;
            }

            $this->advance();
        } while (true);

        return new SelectNode($columns);
    }

    /**
     * Parse the FROM statement.
     *
     * @return FromNode
     */
    protected function parseFrom(): FromNode
    {
        $this->expect('KEYWORD', 'FROM');

        $token = $this->expect('IDENTIFIER');

        return new FromNode($token->value);
    }

    /**
     * Parse the WHERE statement.
     *
     * @return WhereNode
     */
    protected function parseWhere(): WhereNode
    {
        $this->expect('KEYWORD', 'WHERE');

        $left = $this->expect('IDENTIFIER');
        $operator = $this->expect('COMPARISON_OPERATOR');
        $right = $this->advance();

        return new WhereNode(
            new ConditionNode($left->value, $operator->value, $right->value)
        );
    }

    protected function parseDefine(): DefineNode
    {
        $this->expect('KEYWORD', 'DEFINE');

        $pathToken = $this->expect('STRING');
        $path = $pathToken->value;

        $alias = $this->parseAlias();
        $columns = $this->parseColumns();
        $hasHeaders = $this->parseHeaders();

        if ($alias === null) {
            $alias = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($path, PATHINFO_FILENAME));
        }

        return new DefineNode(
            $path,
            $alias,
            $columns,
            $hasHeaders
        );
    }

    public function parseAlias(): string|null
    {
        if (!$this->match('KEYWORD', 'AS')) {
            return null;
        }

        $this->advance();
        $aliasToken = $this->tokens[$this->position] ?? null;

        if (!$aliasToken || ($aliasToken->type !== 'IDENTIFIER' && $aliasToken->type !== 'STRING' && $aliasToken->type !== 'KEYWORD')) {
            $type = $aliasToken->type ?? 'null';
            throw new SyntaxException(
                "Expected alias name after AS, got {$type} at position {$this->position}"
            );
        }

        $aliasValue = $aliasToken->value;

        if ($aliasToken->type === 'STRING') {
            $aliasValue = trim($aliasToken->value, "'");
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $aliasValue)) {
            throw new SyntaxException(
                "Invalid alias name '{$aliasValue}' at position {$this->position}"
            );
        }

        $this->advance();
        return $aliasValue;
    }

    public function parseHeaders(): bool
    {
        if ($this->match('KEYWORD', 'WITH')) {
            $this->advance();
            $this->expect('KEYWORD', 'HEADERS');
            return true;
        }

        if ($this->match('KEYWORD', 'WITH')) {
            $this->advance();
            $this->expect('KEYWORD', 'HEADERS');
            return false;
        }

        return false;
    }

    protected function parseColumns(): array
    {
        $this->expect('LPAREN'); // COLUMNS should start with a '('
        $this->advance();

        $columns = [];

        while (!$this->match('RPAREN')) {
            if (!isset($this->tokens[$this->position])) {
                throw new ParserException("Unexpected end of tokens while parsing columns");
            }

            $token = $this->tokens[$this->position];

            if (!in_array($token->type, ['IDENTIFIER', 'STRING'])) {
                throw new ParserException(
                    "Expected column name, got {$token->type} at position {$this->position}"
                );
            }

            $columns[] = trim($token->value, "'");
            $this->advance();

            if ($this->match('COMMA')) {
                $this->advance();
            } else {
                break;
            }
        }

        $this->expect('RPAREN');
        $this->advance();

        return $columns;
    }

    /**
     * Expect a token of a specific type and value.
     *
     * @param string $type
     * @param string|null $value
     * @return bool
     */
    protected function match(string $type, ?string $value = null): bool
    {
        $token = $this->tokens[$this->position] ?? null;

        if (!$token || $token->type !== $type || ($value !== null && strtoupper($token->value) !== strtoupper(
                    $value
                ))) {
            return false;
        }

        return true;
    }

    /**
     * Expect a token of a specific type and value.
     *
     * @param string $type
     * @param string|null $value
     * @return Token
     */
    protected function expect(string $type, ?string $value = null): Token
    {
        if (!$this->match($type, $value)) {
            $expected = $value ? "$type('$value')" : $type;
            $actual = $this->tokens[$this->position] ?? 'EOF';
            throw new ParserException(
                "Expected token '$expected', Actual: '$actual' at position {$this->position}"
            );
        }

        return $this->advance();
    }

    /**
     * Advance to the next token.
     *
     * @return Token
     */
    protected function advance(): Token
    {
        if (!isset($this->tokens[$this->position])) {
            throw new ParserException("Unexpected end of tokens or invalid token array at position {$this->position}");
        }

        if (!isset($this->tokens[$this->position + 1]) && !$this->match('SEMICOLON')) {
            throw new ParserException("Unexpected end of tokens at position {$this->position}");
        }

        return $this->tokens[$this->position++];
    }

}