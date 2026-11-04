<?php

namespace CQL\Parser;

use CQL\Parser\Nodes\ConditionNode;
use CQL\Parser\Nodes\UnaryConditionNode;
use CQL\Parser\Nodes\WhereNode;
use CQL\Exceptions\ParserException;
use CQL\Exceptions\SyntaxException;

/** @internal Grammar implementation shared by Parser. */
final class ConditionParser
{
    /**
     * @param ParseContext $context Shared cursor and binding state.
     * @param ExpressionParser $expressions Arithmetic and scalar expressions.
     */
    public function __construct(private readonly ParseContext $context, private readonly ExpressionParser $expressions)
    {
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
    public function parseWhere(): WhereNode
    {
        $this->context->expect('KEYWORD', 'WHERE');

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

        while ($this->context->match('KEYWORD', 'OR')) {
            $this->context->advance();
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

        while ($this->context->match('KEYWORD', 'AND')) {
            $this->context->advance();
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
        if ($this->context->match('KEYWORD', 'NOT')) {
            $this->context->advance();
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
        if ($this->context->match('KEYWORD', 'EXISTS')) {
            $this->context->advance();
            return new UnaryConditionNode('EXISTS', $this->expressions->parseExpression());
        }

        if ($this->context->match('LPAREN')) {
            $saved = $this->context->position;
            $savedParameters = $this->context->parameters;
            $savedStyle = $this->context->parameterStyle;

            try {
                $this->context->advance(); // consume (
                $condition = $this->parseOrCondition();
                $this->context->expect('RPAREN');

                // If the parenthesised group is followed by a comparison or
                // expression operator, the parentheses were grouping an
                // expression, e.g. (a + b) > 5 - rewind and parse as a
                // predicate instead.
                $next = $this->context->peek();

                if (
                    $next === null ||
                    ($next->type !== 'COMPARISON_OPERATOR' && !$this->expressions->isExpressionOperator($next))
                ) {
                    return $condition;
                }
            } catch (ParserException | SyntaxException) {
                // Not a condition group - fall through to predicate parsing
            }

            $this->context->position = $saved;
            $this->context->parameters = $savedParameters;
            $this->context->parameterStyle = $savedStyle;
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
        $left = $this->expressions->parseExpression();

        // NOT IN split across two tokens (e.g. extra whitespace between them)
        if (
            $this->context->match('KEYWORD', 'NOT') &&
            strtoupper((string)($this->context->peek(1)->value ?? '')) === 'IN'
        ) {
            $this->context->advance(); // NOT
            $this->context->advance(); // IN
            return new ConditionNode($left, 'NOT IN', $this->parseInList());
        }

        $token = $this->context->peek();

        if ($token === null || $token->type !== 'COMPARISON_OPERATOR') {
            return $left; // bare truthiness condition
        }

        $operator = strtoupper((string)$this->context->advance()->value);

        if ($operator === 'IN' || $operator === 'NOT IN') {
            return new ConditionNode($left, $operator, $this->parseInList());
        }

        return new ConditionNode($left, $operator, $this->expressions->parseExpression());
    }

    /**
     * Parse a parenthesised value list for IN / NOT IN.
     *
     * @return array<mixed>
     */
    protected function parseInList(): array
    {
        $this->context->expect('LPAREN');

        $values = [];

        do {
            $values[] = $this->expressions->parseExpression();

            if (!$this->context->match('COMMA')) {
                break;
            }

            $this->context->advance();
        } while (true);

        $this->context->expect('RPAREN');

        return $values;
    }
}
