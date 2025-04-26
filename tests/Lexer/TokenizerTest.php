<?php

namespace Lexer;

use CQL\Lexer\Token;
use CQL\Lexer\Tokenizer;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{
    public function test_tokenizer_simple_select_query()
    {
        $query = "SELECT name, age FROM users;";

        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();

        $expected = [
            ['type' => 'KEYWORD', 'value' => 'SELECT'],
            ['type' => 'IDENTIFIER', 'value' => 'name'],
            ['type' => 'COMMA', 'value' => ','],
            ['type' => 'IDENTIFIER', 'value' => 'age'],
            ['type' => 'KEYWORD', 'value' => 'FROM'],
            ['type' => 'IDENTIFIER', 'value' => 'users'],
            ['type' => 'SEMICOLON', 'value' => ';'],
        ];

        $this->assertCount(count($expected), $tokens, 'Token count does not match expected');

        foreach ($expected as $i => $expectedToken) {
            $token = $tokens[$i];
            $this->assertInstanceOf(Token::class, $token, "Item at index $i is not a Token");

            $this->assertSame(
                $expectedToken['type'],
                $token->type,
                "Token type mismatch at index $i"
            );

            $expectedValue = $expectedToken['value'];
            $actualValue = is_object($token->value) ? $token->value->value : $token->value;

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "Token value mismatch at index $i"
            );
        }
    }

    public function test_tokenizer_with_more_tokens()
    {
        $query = "SELECT * FROM users WHERE age >= 18 AND status != 'inactive';";

        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();

        $expected = [
            ['type' => 'KEYWORD', 'value' => 'SELECT'],
            ['type' => 'MATH_OPERATOR', 'value' => '*'],
            ['type' => 'KEYWORD', 'value' => 'FROM'],
            ['type' => 'IDENTIFIER', 'value' => 'users'],
            ['type' => 'KEYWORD', 'value' => 'WHERE'],
            ['type' => 'IDENTIFIER', 'value' => 'age'],
            ['type' => 'COMPARISON_OPERATOR', 'value' => '>='],
            ['type' => 'NUMBER', 'value' => '18'],
            ['type' => 'KEYWORD', 'value' => 'AND'],
            ['type' => 'IDENTIFIER', 'value' => 'status'],
            ['type' => 'COMPARISON_OPERATOR', 'value' => '!='],
            ['type' => 'STRING', 'value' => "'inactive'"],
            ['type' => 'SEMICOLON', 'value' => ';'],
        ];

        $this->assertCount(count($expected), $tokens, 'Token count does not match expected');

        foreach ($expected as $i => $expectedToken) {
            $token = $tokens[$i];
            $this->assertInstanceOf(Token::class, $token, "Item at index $i is not a Token");

            $this->assertSame(
                $expectedToken['type'],
                $token->type,
                "Token type mismatch at index $i"
            );

            $expectedValue = $expectedToken['value'];
            $actualValue = is_object($token->value) ? $token->value->value : $token->value;

            $this->assertSame(
                $expectedValue,
                $actualValue,
                "Token value mismatch at index $i"
            );
        }
    }
}
