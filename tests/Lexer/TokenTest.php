<?php

namespace Lexer;

use PHPUnit\Framework\TestCase;
use CQL\Lexer\Token;

class TokenTest extends TestCase
{
    public function test_token_can_be_created_and_properties_are_readable()
    {
        $token = new Token('IDENTIFIER', 'name', 7);

        $this->assertSame('IDENTIFIER', $token->type);
        $this->assertSame('name', $token->value);
        $this->assertSame(7, $token->position);
    }

    public function test_isType_returns_correct_boolean()
    {
        $token = new Token('KEYWORD', 'SELECT', 0);

        $this->assertTrue($token->isType('KEYWORD'));
        $this->assertFalse($token->isType('IDENTIFIER'));
    }

    public function test_toString_returns_formatted_string()
    {
        $token = new Token('KEYWORD', 'SELECT', 0);

        $this->assertSame('KEYWORD(SELECT) at 0', (string)$token);
    }
}
