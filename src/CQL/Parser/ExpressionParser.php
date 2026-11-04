<?php

namespace CQL\Parser;

use CQL\Engine\Operators\Contracts\ExpressionOperatorInterface;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Lexer\Token;
use CQL\Parser\Nodes\LiteralNode;
use CQL\Parser\Nodes\ColumnReferenceNode;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Exceptions\ParserException;
use CQL\Parser\Nodes\FunctionNode;

/** @internal Grammar implementation shared by Parser. */
final class ExpressionParser
{
    /**
     * @param ParseContext $context Shared cursor and binding state.
     */
    public function __construct(private readonly ParseContext $context)
    {
    }
    /**
     * Parse an expression with precedence climbing.
     *
     * Operator lookup uses the existing extensible registry.
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @param int $minPrecedence
     * @return mixed
     * @throws ParserException
     */
    public function parseExpression(int $minPrecedence = 0): mixed
    {
        $left = $this->parsePrimary();

        while ($this->isExpressionOperator($this->context->peek())) {
            $operatorToken = $this->context->peek();

            if ($operatorToken === null) {
                throw new ParserException("Unexpected end of tokens at position {$this->context->position}");
            }

            $operator = OperatorRegistry::resolve($operatorToken->value);

            if ($operator->precedence() < $minPrecedence) {
                break;
            }

            $this->context->advance();

            $right = $this->parseExpression($operator->precedence() + 1);
            $left = new ExpressionNode($left, $operatorToken->value, $right);
        }

        return $left;
    }

    /**
     * Parse one operand without consuming a following binary operator.
     * @return mixed
     */
    public function parsePrimary(): mixed
    {
        if ($this->context->match('PARAMETER')) {
            return $this->context->parseParameter();
        }
        if ($this->context->consume('LPAREN')) {
            $value = $this->parseExpression();
            $this->context->expect('RPAREN');
            return $value;
        }
        if ($this->context->match('MATH_OPERATOR', '-') || $this->context->match('MATH_OPERATOR', '+')) {
            $sign = $this->context->advance()->value;
            $operand = $this->parsePrimary();
            return $sign === '+' ? $operand : new ExpressionNode(new LiteralNode(0), '-', $operand);
        }
        if ($this->isDateFunction($this->context->peek())) {
            return $this->parseFunction();
        }
        if ($this->context->match('IDENTIFIER')) {
            return new ColumnReferenceNode($this->parseReference());
        }
        return $this->parseLiteral();
    }

    /**
     * Parse a qualified or unqualified column identifier.
     * @return string
     */
    public function parseReference(): string
    {
        $name = $this->context->expect('IDENTIFIER')->value;
        if ($this->context->consume('DOT')) {
            $name .= '.' . $this->context->expect('IDENTIFIER')->value;
        }
        return $name;
    }

    /**
     * Decode a literal using the AST's pure SQL decoding utility.
     * @return LiteralNode
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function parseLiteral(): LiteralNode
    {
        $token = $this->context->peek();
        if ($token === null || !in_array($token->type, ['STRING', 'NUMBER', 'BOOLEAN', 'NULL'], true)) {
            $type = $token->type ?? 'EOF';
            throw new ParserException("Unexpected token: {$type} at position {$this->context->position}");
        }
        $this->context->advance();
        return new LiteralNode(match ($token->type) {
            'STRING' => LiteralNode::decode($token->value),
            'NUMBER' => $token->value + 0,
            'BOOLEAN' => strtoupper($token->value) === 'TRUE',
            default => null,
        });
    }

    /**
     * Parse a scalar date or aggregate function call.
     * @return FunctionNode
     */
    public function parseFunction(): FunctionNode
    {
        $name = strtoupper($this->context->advance()->value);
        $this->context->expect('LPAREN');
        $argument = $this->parseFunctionArgument();
        $this->context->expect('RPAREN');
        return new FunctionNode($name, $argument, $this->parseFunctionAlias());
    }

    /** @return mixed */
    private function parseFunctionArgument(): mixed
    {
        if ($this->context->consume('MATH_OPERATOR', '*')) {
            return '*';
        }
        if ($this->context->match('IDENTIFIER')) {
            return $this->parseReference();
        }
        if (in_array($this->context->peek()?->type, ['STRING', 'NUMBER', 'BOOLEAN', 'NULL', 'PARAMETER'], true)) {
            return $this->parsePrimary();
        }
        throw new ParserException("Expected column name or expression in function at position {$this->context->position}");
    }

    /** @return string|null */
    private function parseFunctionAlias(): ?string
    {
        if (!$this->context->consume('KEYWORD', 'AS')) {
            return null;
        }
        $token = $this->context->peek();
        if ($token !== null && in_array($token->type, ['IDENTIFIER', 'KEYWORD'], true)) {
            return $this->context->advance()->value;
        }
        throw new ParserException("Expected alias name after AS at position {$this->context->position}");
    }

    /**
     * Check if the current token is an expression operator.
     *
     * @param Token|null $token
     * Resolve through the existing extensible operator registry.
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @return bool
     */
    public function isExpressionOperator(?Token $token): bool
    {
        if ($token === null) {
            return false;
        }

        $operator = OperatorRegistry::tryResolve($token->value);
        return $operator instanceof ExpressionOperatorInterface;
    }

    /**
     * Check if token is an aggregate function.
     *
     * @param Token|null $token
     * @return bool
     */
    public function isAggregateFunction(?Token $token): bool
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
    public function isDateFunction(?Token $token): bool
    {
        if ($token === null || $token->type !== 'KEYWORD') {
            return false;
        }

        $name = strtoupper($token->value);
        return in_array($name, ['DATE', 'YEAR', 'MONTH', 'DAY'], true);
    }
}
