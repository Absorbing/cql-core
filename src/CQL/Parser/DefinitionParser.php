<?php

namespace CQL\Parser;

use CQL\Data\Enums\CSVHeaderMode;
use CQL\Parser\Nodes\LiteralNode;
use CQL\Parser\Nodes\DefineNode;
use CQL\Exceptions\ParserException;
use CQL\Exceptions\SyntaxException;

/** @internal Grammar implementation shared by Parser. */
final class DefinitionParser
{
    /**
     * @param ParseContext $context Shared cursor and binding state.
     */
    public function __construct(private readonly ParseContext $context)
    {
    }

    /**
     * Parse consecutive DEFINE statements.
     *
     * @return array<DefineNode>
     */
    public function parseDefines(): array
    {
        $defines = [];

        while ($this->context->match('KEYWORD', 'DEFINE')) {
            $defines[] = $this->parseDefine();
        }

        return $defines;
    }

    /**
     * Parse the DEFINE statement.
     *
     * SQL literal decoding is a stateless AST utility.
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @return DefineNode
     */
    protected function parseDefine(): DefineNode
    {
        $this->context->expect('KEYWORD', 'DEFINE');

        $pathToken = $this->context->expect('STRING');
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
     * SQL literal decoding is a stateless AST utility.
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @return string|null
     */
    protected function parseAlias(): string|null
    {
        if (!$this->context->match('KEYWORD', 'AS')) {
            return null;
        }

        $this->context->advance();
        $aliasToken = $this->context->tokens[$this->context->position] ?? null;

        if (!isset($this->context->tokens[$this->context->position])) {
            throw new ParserException("Unexpected end of tokens at position {$this->context->position}");
        }

        if (!$aliasToken || !in_array($aliasToken->type, ['IDENTIFIER', 'STRING', 'KEYWORD'], true)) {
            $type = $aliasToken->type ?? 'null';
            throw new SyntaxException(
                "Expected alias name after AS, got {$type} at position {$this->context->position}"
            );
        }

        $aliasValue = $aliasToken->value;

        if ($aliasToken->type === 'STRING') {
            $aliasValue = LiteralNode::decode($aliasToken->value);
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $aliasValue)) {
            throw new SyntaxException(
                "Invalid alias name '{$aliasValue}' at position {$this->context->position}"
            );
        }

        $this->context->advance();
        return $aliasValue;
    }

    /**
     * Parse the CSV header mode.
     *
     * @return CSVHeaderMode
     */
    protected function parseHeaders(): CSVHeaderMode
    {
        if ($this->context->match('KEYWORD', 'WITH')) {
            $this->context->advance();
            $this->context->expect('KEYWORD', 'HEADERS');
            return CSVHeaderMode::WITH_HEADERS;
        }

        if ($this->context->match('KEYWORD', 'WITHOUT')) {
            $this->context->advance();
            $this->context->expect('KEYWORD', 'HEADERS');
            return CSVHeaderMode::WITHOUT_HEADERS;
        }

        return CSVHeaderMode::WITHOUT_HEADERS;
    }

    /**
     * Parse the columns.
     *
     * SQL literal decoding is a stateless AST utility.
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @return array<string>
     */
    protected function parseColumns(): array
    {
        $columns = [];

        if (!$this->context->match('KEYWORD', 'COLUMNS')) {
            return $columns;
        }

        $this->context->advance();
        $this->context->expect('LPAREN');

        $count = count($this->context->tokens);

        while ($this->context->position < $count) {
            $token = $this->context->tokens[$this->context->position];

            if (!in_array($token->type, ['IDENTIFIER', 'STRING'])) {
                throw new SyntaxException("Expected column name, got {$token->type} at position {$this->context->position}");
            }

            $columns[] = $token->type === 'STRING' ? LiteralNode::decode($token->value) : $token->value;
            $this->context->advance();

            if (!$this->context->match('COMMA')) {
                break;
            }

            $this->context->advance();
        }

        $this->context->expect('RPAREN');
        return $columns;
    }
}
