<?php

namespace CQL\Parser;

use CQL\Data\Enums\CSVHeaderMode;
use CQL\Engine\Operators\Contracts\ExpressionOperatorInterface;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Lexer\Token;
use CQL\Parser\Nodes\AssignmentNode;
use CQL\Parser\Nodes\LiteralNode;
use CQL\Parser\Nodes\ParameterNode;
use CQL\Exceptions\ParameterException;
use CQL\Parser\Nodes\ColumnReferenceNode;
use CQL\Exceptions\CQLException;
use CQL\Parser\Nodes\ConditionNode;
use CQL\Parser\Nodes\Contracts\StatementNodeInterface;
use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\DeleteNode;
use CQL\Parser\Nodes\FromNode;
use CQL\Parser\Nodes\InsertNode;
use CQL\Parser\Nodes\JoinNode;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\UnaryConditionNode;
use CQL\Parser\Nodes\UpdateNode;
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
    /** @var array<string|int, ParameterNode> */
    protected array $parameters = [];
    protected ?string $parameterStyle = null;

    /** @return array<string|int, ParameterNode> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

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
        protected array $tokens,
        protected bool $allowMissingDefines = false,
    ) {
    }

    /**
     * Parse the statement and return the corresponding node.
     *
     * @return StatementNodeInterface
     * @throws SyntaxException
     */
    public function parse(): StatementNodeInterface
    {
        try {
            return $this->parseStatement();
        } catch (CQLException $error) {
            $last = $this->tokens[count($this->tokens) - 1] ?? null;
            $error->position ??= $this->peek()->position ?? ($last ? $last->position + strlen($last->value) : 0);
            throw $error;
        }
    }

    /** @return StatementNodeInterface */
    protected function parseStatement(): StatementNodeInterface
    {
        if (!$this->allowMissingDefines && !$this->match('KEYWORD', 'DEFINE')) {
            $type = $this->tokens[$this->position]->type ?? '';
            $value = isset($this->tokens[$this->position]->value) ? "({$this->tokens[$this->position]->value})" : '';

            throw new SyntaxException(
                "Expected query to start with 'DEFINE' keyword, found '{$type}{$value}' at position {$this->position}"
            );
        }

        $defines = $this->parseDefines();

        if ($this->match('KEYWORD', 'INSERT')) {
            return $this->finalize($this->parseInsert($defines));
        }

        if ($this->match('KEYWORD', 'UPDATE')) {
            return $this->finalize($this->parseUpdate($defines));
        }

        if ($this->match('KEYWORD', 'DELETE')) {
            return $this->finalize($this->parseDelete($defines));
        }

        return $this->finalize($this->parseQuery($defines));
    }

    /**
     * Ensure every token was consumed by the statement.
     *
     * Trailing tokens indicate a malformed statement that would otherwise
     * be silently ignored (e.g. "WHERE age NOT 18" parsing as a bare
     * truthiness test on age and dropping the rest).
     *
     * @template T of StatementNodeInterface
     * @param T $statement
     * @return T
     * @throws SyntaxException
     */
    protected function finalize(StatementNodeInterface $statement): StatementNodeInterface
    {
        $token = $this->peek();

        if ($token !== null) {
            throw new SyntaxException(
                "Unexpected token {$token->type}('{$token->value}') after end of statement at position {$this->position}"
            );
        }

        return $statement;
    }

    /**
     * Parse consecutive DEFINE statements.
     *
     * @return array<DefineNode>
     */
    protected function parseDefines(): array
    {
        $defines = [];

        while ($this->match('KEYWORD', 'DEFINE')) {
            $defines[] = $this->parseDefine();
        }

        return $defines;
    }

    /**
     * Parse a SELECT query.
     *
     * @param array<DefineNode> $defines
     * @return QueryNode
     */
    protected function parseQuery(array $defines): QueryNode
    {
        $select = $this->parseSelect();
        $from = $this->parseFrom();
        $where = null;
        $groupBy = null;

        if ($this->match('KEYWORD', 'WHERE')) {
            $where = $this->parseWhere();
        }

        if ($this->match('KEYWORD', 'GROUP')) {
            $groupBy = $this->parseGroupBy();
        }

        if ($this->match('SEMICOLON')) {
            $this->advance();
        }

        return new QueryNode($defines, $select, $from, $where, $groupBy);
    }

    /**
     * Parse an INSERT statement.
     *
     * INSERT INTO alias [(col, col, ...)] VALUES (v, v, ...) [, (v, v, ...)]
     *
     * @param array<DefineNode> $defines
     * @return InsertNode
     * @throws ParserException
     */
    protected function parseInsert(array $defines): InsertNode
    {
        $this->expect('KEYWORD', 'INSERT');
        $this->expect('KEYWORD', 'INTO');

        $table = $this->expect('IDENTIFIER')->value;

        $columns = [];

        if ($this->match('LPAREN')) {
            $this->advance();

            do {
                $token = $this->peek();

                if (!$token || !in_array($token->type, ['IDENTIFIER', 'STRING'], true)) {
                    $type = $token->type ?? 'EOF';
                    throw new SyntaxException("Expected column name, got {$type} at position {$this->position}");
                }

                $columns[] = trim($this->advance()->value, "'");

                if (!$this->match('COMMA')) {
                    break;
                }

                $this->advance();
            } while (true);

            $this->expect('RPAREN');
        }

        $this->expect('KEYWORD', 'VALUES');

        $rows = [];

        do {
            $this->expect('LPAREN');

            $values = [];

            do {
                $values[] = $this->parseExpression();

                if (!$this->match('COMMA')) {
                    break;
                }

                $this->advance();
            } while (true);

            $this->expect('RPAREN');

            if ($columns !== [] && count($values) !== count($columns)) {
                throw new SyntaxException(
                    sprintf(
                        'VALUES tuple has %d value(s) but %d column(s) were specified at position %d',
                        count($values),
                        count($columns),
                        $this->position
                    )
                );
            }

            $rows[] = $values;

            if (!$this->match('COMMA')) {
                break;
            }

            $this->advance();
        } while (true);

        if ($this->match('SEMICOLON')) {
            $this->advance();
        }

        return new InsertNode($defines, $table, $columns, $rows);
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
    protected function parseUpdate(array $defines): UpdateNode
    {
        $this->expect('KEYWORD', 'UPDATE');

        $table = $this->expect('IDENTIFIER')->value;

        $this->expect('KEYWORD', 'SET');

        $assignments = [];

        do {
            $first = $this->expect('IDENTIFIER');

            if ($this->match('DOT')) {
                $this->advance();
                $second = $this->expect('IDENTIFIER');
                $column = "{$first->value}.{$second->value}";
            } else {
                $column = $first->value;
            }

            $this->expect('COMPARISON_OPERATOR', '=');

            $assignments[] = new AssignmentNode($column, $this->parseExpression());

            if (!$this->match('COMMA')) {
                break;
            }

            $this->advance();
        } while (true);

        $where = null;

        if ($this->match('KEYWORD', 'WHERE')) {
            $where = $this->parseWhere();
        }

        if ($this->match('SEMICOLON')) {
            $this->advance();
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
    protected function parseDelete(array $defines): DeleteNode
    {
        $this->expect('KEYWORD', 'DELETE');
        $this->expect('KEYWORD', 'FROM');

        $table = $this->expect('IDENTIFIER')->value;

        $where = null;

        if ($this->match('KEYWORD', 'WHERE')) {
            $where = $this->parseWhere();
        }

        if ($this->match('SEMICOLON')) {
            $this->advance();
        }

        return new DeleteNode($defines, $table, $where);
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

        $alias ??= preg_replace('/[^a-zA-Z0-9]/', '', pathinfo(LiteralNode::decode($path), PATHINFO_FILENAME));
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
            $aliasValue = LiteralNode::decode($aliasToken->value);
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

            $columns[] = $token->type === 'STRING' ? LiteralNode::decode($token->value) : $token->value;
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

            // Check for aggregate functions
            if ($this->isAggregateFunction($token)) {
                $columns[] = $this->parseFunction();
            } elseif ($this->isDateFunction($token)) {
                $columns[] = $this->parseFunction();
            } else {
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
                } elseif ($token !== null && TokenTypeRegistry::isWildcard($token)) {
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

            if ($this->match('KEYWORD', 'LEFT') || $this->match('KEYWORD', 'RIGHT') || $this->match(
                    'KEYWORD',
                    'INNER'
                )) {
                $type = strtoupper($this->advance()->value);
            }

            if (!$this->match('KEYWORD', 'JOIN')) {
                if ($type !== null) {
                    $this->expect('KEYWORD', 'JOIN');
                }
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
     * Parse the WHERE clause into a condition tree.
     *
     * Grammar (standard SQL precedence, lowest first):
     *   or_cond   := and_cond (OR and_cond)*
     *   and_cond  := not_cond (AND not_cond)*
     *   not_cond  := NOT not_cond | primary
     *   primary   := '(' or_cond ')' | EXISTS expr | predicate
     *   predicate := expr [comparison_op expr | [NOT] IN '(' value, ... ')']
     *
     * @return WhereNode
     */
    protected function parseWhere(): WhereNode
    {
        $this->expect('KEYWORD', 'WHERE');

        return new WhereNode($this->parseOrCondition());
    }

    /**
     * Parse OR-combined conditions (lowest precedence).
     *
     * @return mixed
     */
    protected function parseOrCondition(): mixed
    {
        $left = $this->parseAndCondition();

        while ($this->match('KEYWORD', 'OR')) {
            $this->advance();
            $left = new ConditionNode($left, 'OR', $this->parseAndCondition());
        }

        return $left;
    }

    /**
     * Parse AND-combined conditions.
     *
     * @return mixed
     */
    protected function parseAndCondition(): mixed
    {
        $left = $this->parseNotCondition();

        while ($this->match('KEYWORD', 'AND')) {
            $this->advance();
            $left = new ConditionNode($left, 'AND', $this->parseNotCondition());
        }

        return $left;
    }

    /**
     * Parse an optionally negated condition.
     *
     * @return mixed
     */
    protected function parseNotCondition(): mixed
    {
        if ($this->match('KEYWORD', 'NOT')) {
            $this->advance();
            return new UnaryConditionNode('NOT', $this->parseNotCondition());
        }

        return $this->parsePrimaryCondition();
    }

    /**
     * Parse a primary condition: a parenthesised condition group, an
     * EXISTS test, or a comparison predicate.
     *
     * @return mixed
     */
    protected function parsePrimaryCondition(): mixed
    {
        if ($this->match('KEYWORD', 'EXISTS')) {
            $this->advance();
            return new UnaryConditionNode('EXISTS', $this->parseExpression());
        }

        if ($this->match('LPAREN')) {
            $saved = $this->position;
            $savedParameters = $this->parameters;
            $savedStyle = $this->parameterStyle;

            try {
                $this->advance(); // consume (
                $condition = $this->parseOrCondition();
                $this->expect('RPAREN');

                // If the parenthesised group is followed by a comparison or
                // expression operator, the parentheses were grouping an
                // expression, e.g. (a + b) > 5 - rewind and parse as a
                // predicate instead.
                $next = $this->peek();

                if (
                    $next === null ||
                    ($next->type !== 'COMPARISON_OPERATOR' && !$this->isExpressionOperator($next))
                ) {
                    return $condition;
                }
            } catch (ParserException | SyntaxException) {
                // Not a condition group - fall through to predicate parsing
            }

            $this->position = $saved;
            $this->parameters = $savedParameters;
            $this->parameterStyle = $savedStyle;
        }

        return $this->parsePredicate();
    }

    /**
     * Parse a comparison predicate: expr op expr, expr [NOT] IN (...), or
     * a bare expression evaluated for truthiness.
     *
     * @return mixed
     */
    protected function parsePredicate(): mixed
    {
        $left = $this->parseExpression();

        // NOT IN split across two tokens (e.g. extra whitespace between them)
        if (
            $this->match('KEYWORD', 'NOT') &&
            strtoupper((string)($this->peek(1)->value ?? '')) === 'IN'
        ) {
            $this->advance(); // NOT
            $this->advance(); // IN
            return new ConditionNode($left, 'NOT IN', $this->parseInList());
        }

        $token = $this->peek();

        if ($token === null || $token->type !== 'COMPARISON_OPERATOR') {
            return $left; // bare truthiness condition
        }

        $operator = strtoupper((string)$this->advance()->value);

        if ($operator === 'IN' || $operator === 'NOT IN') {
            return new ConditionNode($left, $operator, $this->parseInList());
        }

        return new ConditionNode($left, $operator, $this->parseExpression());
    }

    /**
     * Parse a parenthesised value list for IN / NOT IN.
     *
     * @return array<mixed>
     */
    protected function parseInList(): array
    {
        $this->expect('LPAREN');

        $values = [];

        do {
            $values[] = $this->parseExpression();

            if (!$this->match('COMMA')) {
                break;
            }

            $this->advance();
        } while (true);

        $this->expect('RPAREN');

        return $values;
    }

    /**
     * Parse an expression with precedence climbing.
     *
     * @param int $minPrecedence
     * @return mixed
     * @throws ParserException
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

    /**
     * Parse a primary expression.
     *
     * @return mixed
     * @throws ParserException
     */
    protected function parsePrimary(): mixed
    {
        if ($this->match('PARAMETER')) {
            $token = $this->advance();
            $style = $token->value === '?' ? 'positional' : 'named';
            if ($this->parameterStyle !== null && $this->parameterStyle !== $style) {
                throw new ParameterException('Cannot mix named and positional parameters', position: $token->position);
            }
            $this->parameterStyle = $style;
            $key = $style === 'positional' ? count($this->parameters) : substr($token->value, 1);
            if (array_key_exists($key, $this->parameters)) {
                throw new ParameterException("Use a unique name for each parameter occurrence: '{$key}'", position: $token->position);
            }
            return $this->parameters[$key] = new ParameterNode($key, $token->position);
        }
        if ($this->match('LPAREN')) {
            $this->advance();
            $expression = $this->parseExpression();
            $this->expect('RPAREN');
            return $expression;
        }
        if ($this->match('MATH_OPERATOR', '-') || $this->match('MATH_OPERATOR', '+')) {
            $sign = $this->advance()->value;
            $operand = $this->parsePrimary();
            return $sign === '+' ? $operand : new ExpressionNode(new LiteralNode(0), '-', $operand);
        }
        if ($this->isDateFunction($this->peek())) {
            return $this->parseFunction();
        }
        if ($this->match('IDENTIFIER')) {
            $name = $this->advance()->value;
            if ($this->match('DOT')) {
                $this->advance();
                $name .= '.' . $this->expect('IDENTIFIER')->value;
            }
            return new ColumnReferenceNode($name);
        }
        if ($this->match('STRING')) {
            return new LiteralNode(LiteralNode::decode($this->advance()->value));
        }
        if ($this->match('NUMBER')) {
            return new LiteralNode($this->advance()->value + 0);
        }
        if ($this->match('BOOLEAN')) {
            return new LiteralNode(strtoupper($this->advance()->value) === 'TRUE');
        }
        if ($this->match('NULL')) {
            $this->advance();
            return new LiteralNode(null);
        }
        $type = $this->peek()->type ?? 'EOF';
        throw new ParserException("Unexpected token: {$type} at position {$this->position}");
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
        return OperatorRegistry::tryResolve($token->value) !== false;
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

    /**
     * Parse a function call (aggregate or date function).
     *
     * @return \CQL\Parser\Nodes\FunctionNode
     * @throws ParserException
     */
    protected function parseFunction(): \CQL\Parser\Nodes\FunctionNode
    {
        $functionToken = $this->advance(); // Consume function name
        $functionName = strtoupper($functionToken->value);

        $this->expect('LPAREN');

        // Parse argument
        $argument = null;
        if ($this->match('MATH_OPERATOR', '*')) {
            // COUNT(*)
            $this->advance();
            $argument = '*';
        } else {
            // Parse column name or expression
            $token = $this->peek();
            if ($this->match('IDENTIFIER')) {
                $first = $this->advance();
                if ($this->match('DOT')) {
                    $this->advance();
                    $second = $this->expect('IDENTIFIER');
                    $argument = "{$first->value}.{$second->value}";
                } else {
                    $argument = $first->value;
                }
            } elseif ($this->match('STRING') || $this->match('NUMBER') || $this->match('BOOLEAN') || $this->match('NULL') || $this->match('PARAMETER')) {
                $argument = $this->parsePrimary();
            } else {
                throw new ParserException("Expected column name or expression in function at position {$this->position}");
            }
        }

        $this->expect('RPAREN');

        // Check for alias
        $alias = null;
        if ($this->match('KEYWORD', 'AS')) {
            $this->advance();
            $aliasToken = $this->peek();
            
            // Allow keywords as aliases (YEAR, MONTH, DAY, etc.)
            if ($aliasToken && ($aliasToken->type === 'IDENTIFIER' || $aliasToken->type === 'KEYWORD')) {
                $alias = $this->advance()->value;
            } else {
                throw new ParserException("Expected alias name after AS at position {$this->position}");
            }
        }

        return new \CQL\Parser\Nodes\FunctionNode($functionName, $argument, $alias);
    }

    /**
     * Parse GROUP BY clause.
     *
     * @return \CQL\Parser\Nodes\GroupByNode
     * @throws ParserException
     */
    protected function parseGroupBy(): \CQL\Parser\Nodes\GroupByNode
    {
        $this->expect('KEYWORD', 'GROUP');
        $this->expect('KEYWORD', 'BY');

        $columns = [];

        do {
            // Check if it's a function call
            $token = $this->peek();
            if ($this->isDateFunction($token) || $this->isAggregateFunction($token)) {
                $func = $this->parseFunction();
                // Store as string representation for matching
                $columns[] = $func;
            } else {
                $first = $this->expect('IDENTIFIER');

                if ($this->match('DOT')) {
                    $this->advance();
                    $second = $this->expect('IDENTIFIER');
                    $columns[] = "{$first->value}.{$second->value}";
                } else {
                    $columns[] = $first->value;
                }
            }

            if (!$this->match('COMMA')) {
                break;
            }

            $this->advance(); // Consume comma
        } while (true);

        return new \CQL\Parser\Nodes\GroupByNode($columns);
    }

    /**
     * Check if token is an aggregate function.
     *
     * @param Token|null $token
     * @return bool
     */
    protected function isAggregateFunction(?Token $token): bool
    {
        if ($token === null || $token->type !== 'KEYWORD') {
            return false;
        }

        $name = strtoupper($token->value);
        return in_array($name, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'], true);
    }

    /**
     * Check if token is a date function.
     *
     * @param Token|null $token
     * @return bool
     */
    protected function isDateFunction(?Token $token): bool
    {
        if ($token === null || $token->type !== 'KEYWORD') {
            return false;
        }

        $name = strtoupper($token->value);
        return in_array($name, ['DATE', 'YEAR', 'MONTH', 'DAY'], true);
    }
}