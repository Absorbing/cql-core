<?php

namespace CQL\Parser;

use CQL\Data\Enums\CSVHeaderMode;
use CQL\Engine\Operators\Contracts\ExpressionOperatorInterface;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Lexer\Token;
use CQL\Parser\Nodes\ConditionNode;
use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\FromNode;
use CQL\Parser\Nodes\JoinNode;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\SelectNode;
use CQL\Parser\Nodes\WhereNode;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Exceptions\ParserException;
use CQL\Exceptions\SyntaxException;
use CQL\Parser\Nodes\WildcardNode;
use CQL\Lexer\TokenType\Registry\TokenTypeRegistry;
use CQL\Parser\Enums\JoinType;

class Parser
{
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
    public function __construct(
        protected array $tokens
    ) {
    }

    public function parse(): QueryNode
    {
        if (!$this->match('KEYWORD', 'DEFINE')) {
            $type = $this->tokens[$this->position]->type ?? '';
            $value = isset($this->tokens[$this->position]->value) ? "({$this->tokens[$this->position]->value})" : '';

            throw new SyntaxException(
                "Expected query to start with 'DEFINE' keyword, found '{$type}{$value}' at position {$this->position}"
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
        $defines = [];

        while ($this->match('KEYWORD', 'DEFINE')) {
            $defines[] = $this->parseDefine();
        }

        $select = $this->parseSelect();
        $from = $this->parseFrom();
        $where = null;

        if ($this->match('KEYWORD', 'WHERE')) {
            $where = $this->parseWhere();
        }

        if ($this->match('SEMICOLON')) {
            $this->advance();
        }

        return new QueryNode($defines, $select, $from, $where);
    }

    /**
     * Parse the DEFINE statement.
     *
     * @return DefineNode
     */
    protected function parseDefine(): DefineNode
    {
        $this->expect('KEYWORD', 'DEFINE');

        $pathToken = $this->expect('STRING');
        $path = $pathToken->value;

        $alias = $this->parseAlias();
        $hasHeaders = $this->parseHeaders();
        $columns = $this->parseColumns();

        $alias ??= preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($path, PATHINFO_FILENAME));
        /** @var string $alias */

        return new DefineNode(
            $path,
            $alias,
            $columns,
            $hasHeaders
        );
    }

    /**
     * Parse the alias.
     *
     * @return string|null
     */
    protected function parseAlias(): string|null
    {
        if (!$this->match('KEYWORD', 'AS')) {
            return null;
        }

        $this->advance();
        $aliasToken = $this->tokens[$this->position] ?? null;

        if (!isset($this->tokens[$this->position])) {
            throw new ParserException("Unexpected end of tokens at position {$this->position}");
        }

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

    /**
     * Parse the CSV header mode.
     *
     * @return CSVHeaderMode
     */
    protected function parseHeaders(): CSVHeaderMode
    {
        if ($this->match('KEYWORD', 'WITH')) {
            $this->advance();
            $this->expect('KEYWORD', 'HEADERS');
            return CSVHeaderMode::WITH_HEADERS;
        } elseif ($this->match('KEYWORD', 'WITHOUT')) {
            $this->advance();
            $this->expect('KEYWORD', 'HEADERS');
            return CSVHeaderMode::WITHOUT_HEADERS;
        }

        return CSVHeaderMode::WITHOUT_HEADERS;
    }

    /**
     * Parse the columns.
     *
     * @return array<string>
     */
    protected function parseColumns(): array
    {
        $columns = [];

        if (!$this->match('KEYWORD', 'COLUMNS')) {
            return $columns;
        }

        $this->advance();
        $this->expect('LPAREN');

        $count = count($this->tokens);

        while ($this->position < $count) {
            $token = $this->tokens[$this->position];

            if (!in_array($token->type, ['IDENTIFIER', 'STRING'])) {
                throw new SyntaxException("Expected column name, got {$token->type} at position {$this->position}");
            }

            $columns[] = trim($token->value, "'");
            $this->advance();

            if (!$this->match('COMMA')) {
                break;
            }

            $this->advance();
        }

        $this->expect('RPAREN');
        return $columns;
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
            $token = $this->peek();

            $next = $this->peek(1);
            $after = $this->peek(2);

            if (
                $token && $next && $after &&
                TokenTypeRegistry::isStructural($next->type) &&
                TokenTypeRegistry::isWildcard($after)
            ) {
                $prefix = $token->value;
                $this->advance(); // IDENTIFIER
                $this->advance(); // DOT
                $this->advance(); // *
                $columns[] = new WildcardNode($prefix);
            } elseif (TokenTypeRegistry::isWildcard($token)) {
                $this->advance(); // *
                $columns[] = new WildcardNode();
            } else {
                $first = $this->expect('IDENTIFIER');

                if ($this->match('DOT')) {
                    $this->advance();
                    $second = $this->expect('IDENTIFIER');
                    $column = "{$first->value}.{$second->value}";
                } else {
                    $column = $first->value;
                }

                if ($this->match('KEYWORD', 'AS')) {
                    $this->advance();
                    $aliasToken = $this->expect('IDENTIFIER');
                    $columns[] = new \CQL\Parser\Nodes\AliasedColumnNode($column, $aliasToken->value);
                } else {
                    $columns[] = $column;
                }
            }

            if (!$this->match('COMMA')) {
                break;
            }

            $this->advance(); // Consume comma
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
        $base = $this->expect('IDENTIFIER')->value;

        $joins = [];

        while (true) {
            $type = null;

            if ($this->match('KEYWORD', 'LEFT') || $this->match('KEYWORD', 'RIGHT') || $this->match('KEYWORD',
                    'INNER')) {
                $type = strtoupper($this->advance()->value);
            }

            if (!$this->match('KEYWORD', 'JOIN')) {
                break;
            }

            $this->advance(); // consume JOIN

            $right = $this->expect('IDENTIFIER')->value;
            $this->expect('KEYWORD', 'ON');

            $leftToken = $this->expect('IDENTIFIER');
            $this->expect('DOT');
            $leftField = $this->expect('IDENTIFIER')->value;

            $this->expect('COMPARISON_OPERATOR', '=');

            $rightToken = $this->expect('IDENTIFIER');
            $this->expect('DOT');
            $rightField = $this->expect('IDENTIFIER')->value;

            $joins[] = new JoinNode(
                rightAlias: $right,
                leftAlias: $leftToken->value,
                leftKey: $leftField,
                rightKey: $rightField,
                type: JoinType::fromKeyword($type)
            );
        }

        return new FromNode($base, $joins);
    }

    /**
     * Parse the WHERE statement.
     *
     * @return WhereNode
     */
    protected function parseWhere(): WhereNode
    {
        $this->expect('KEYWORD', 'WHERE');

        $left = $this->parseExpression();
        $operator = $this->parseOperatorSymbol();
        $right = $this->parseExpression();

        return new WhereNode(
            new ConditionNode($left, $operator, $right)
        );
    }

    /**
     *
     */
    protected function parseExpression(int $minPrecedence = 0): mixed
    {
        $left = $this->parsePrimary();

        while ($this->isExpressionOperator($this->peek())) {
            $operatorToken = $this->peek();

            if ($operatorToken === null) {
                throw new ParserException("Unexpected end of tokens at position {$this->position}");
            }

            $operator = OperatorRegistry::resolve($operatorToken->value);

            if ($operator->precedence() < $minPrecedence) {
                break;
            }

            $this->advance();

            $right = $this->parseExpression($operator->precedence() + 1);
            $left = new ExpressionNode($left, $operatorToken->value, $right);
        }

        return $left;
    }

    protected function parsePrimary(): mixed
    {
        if ($this->match('LPAREN')) {
            $this->advance();
            $expression = $this->parseExpression();
            $this->expect('RPAREN');
            return $expression;
        }

        if ($this->match('IDENTIFIER') || $this->match('STRING') || $this->match('NUMBER')) {
            $token = $this->advance();
            return $token->value;
        }

        throw new ParserException("Unexpected token: {$this->tokens[$this->position]->type} at position {$this->position}");
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
            $actualDesc = is_object($actual) ? "{$actual->type}('{$actual->value}')" : (string)$actual;

            throw new ParserException(
                "Expected token '$expected', Actual: '$actualDesc' at position {$this->position}"
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
        return $this->tokens[$this->position++];
    }

    /**
     * Peek at the next token without advancing.
     *
     * @param int $offset
     * @return Token|null
     */
    protected function peek(int $offset = 0): ?Token
    {
        return $this->tokens[$this->position + $offset] ?? null;
    }

    /**
     * Check if the current token is an expression operator.
     *
     * @param Token|null $token
     * @return bool
     */
    protected function isExpressionOperator(?Token $token): bool
    {
        if ($token === null) {
            return false;
        }

        $operator = OperatorRegistry::tryResolve($token->value);
        return $operator instanceof ExpressionOperatorInterface;
    }

    /**
     * Check if the current token is an operator.
     *
     * @param Token $token
     * @return bool
     */
    protected function isOperator(Token $token): bool
    {
        return OperatorRegistry::tryResolve($token->value) !== null;
    }

    /**
     * Check if the current token is a wildcard.
     *
     * @param Token|null $token
     * @return bool
     */
    protected function isWildcard(?Token $token): bool
    {
        return $token?->value === '*' && $token->type === 'MATH_OPERATOR';
    }

    protected function parseOperatorSymbol(): string
    {
        $token = $this->peek();

        if (!$token) {
            throw new ParserException("Unexpected end of tokens at position {$this->position}");
        }

        if (!OperatorRegistry::has($token->value)) {
            throw new ParserException("Expected operator, got '{$token->value}' at position {$this->position}");
        }

        $this->advance();
        return $token->value;
    }
}