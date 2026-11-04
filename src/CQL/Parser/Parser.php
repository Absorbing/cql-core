<?php

namespace CQL\Parser;

use CQL\Exceptions\CQLException;
use CQL\Exceptions\SyntaxException;
use CQL\Lexer\Token;
use CQL\Parser\Nodes\Contracts\StatementNodeInterface;
use CQL\Parser\Nodes\ParameterNode;

/** Parses complete statements, delegating each grammar to a shared cursor. */
class Parser
{
    private readonly ParseContext $context;

    /**
     * Preserve the existing optional DEFINE argument, including named calls.
     * @param array<Token> $tokens Input tokens.
     * @param bool $allowMissingDefines Allow registered sources without DEFINE.
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    public function __construct(array $tokens, protected bool $allowMissingDefines = false)
    {
        $this->context = new ParseContext($tokens);
    }

    /** @return array<string|int, ParameterNode> */
    public function getParameters(): array
    {
        return $this->context->parameters;
    }

    /** @return StatementNodeInterface */
    public function parse(): StatementNodeInterface
    {
        try {
            return $this->parseStatement();
        } catch (CQLException $error) {
            $tokens = $this->context->tokens;
            $last = $tokens[count($tokens) - 1] ?? null;
            $error->position ??= $this->context->peek()->position ?? ($last ? $last->position + strlen($last->value) : 0);
            throw $error;
        }
    }

    /** @return StatementNodeInterface */
    protected function parseStatement(): StatementNodeInterface
    {
        if (!$this->allowMissingDefines && !$this->context->match('KEYWORD', 'DEFINE')) {
            $token = $this->context->peek();
            $type = $token->type ?? '';
            $value = $token !== null ? "({$token->value})" : '';
            throw new SyntaxException(
                "Expected query to start with 'DEFINE' keyword, found '{$type}{$value}' at position {$this->context->position}"
            );
        }
        $defines = (new DefinitionParser($this->context))->parseDefines();
        $expressions = new ExpressionParser($this->context);
        $conditions = new ConditionParser($this->context, $expressions);
        $mutations = new MutationParser($this->context, $expressions, $conditions);
        $statement = match (true) {
            $this->context->match('KEYWORD', 'INSERT') => $mutations->parseInsert($defines),
            $this->context->match('KEYWORD', 'UPDATE') => $mutations->parseUpdate($defines),
            $this->context->match('KEYWORD', 'DELETE') => $mutations->parseDelete($defines),
            default => (new QueryParser($this->context, $expressions, $conditions))->parseQuery($defines),
        };
        $token = $this->context->peek();
        if ($token !== null) {
            throw new SyntaxException(
                "Unexpected token {$token->type}('{$token->value}') after end of statement at position {$this->context->position}"
            );
        }
        return $statement;
    }
}
