<?php

namespace Parser;

use PHPUnit\Framework\TestCase;
use CQL\Lexer\Tokenizer;
use CQL\Parser\Parser;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\DefineNode;
use CQL\Parser\Nodes\SelectNode;
use CQL\Parser\Nodes\FromNode;
use CQL\Parser\Nodes\WhereNode;
use CQL\Parser\Nodes\ConditionNode;

class ParserTest extends TestCase
{

    public function test_parse_define_and_select_query(): void
    {
        $query = "DEFINE 'users.csv' AS data WITH HEADERS COLUMNS (id, name, age) SELECT id FROM data WHERE age > 18;";
        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();

        $parser = new Parser($tokens);
        $ast = $parser->parse();

        $this->assertInstanceOf(QueryNode::class, $ast);

        $this->assertInstanceOf(DefineNode::class, $ast->define);
        $this->assertSame("'users.csv'", $ast->define->path);
        $this->assertSame('data', $ast->define->alias);
        $this->assertSame(['id', 'name', 'age'], $ast->define->columns);
        $this->assertTrue($ast->define->hasHeaders);

        $this->assertInstanceOf(SelectNode::class, $ast->select);
        $this->assertSame(['id'], $ast->select->columns);

        $this->assertInstanceOf(FromNode::class, $ast->from);
        $this->assertSame('data', $ast->from->table);

        $this->assertInstanceOf(WhereNode::class, $ast->where);
        $condition = $ast->where->condition;
        $this->assertInstanceOf(ConditionNode::class, $condition);
        $this->assertSame('age', $condition->left);
        $this->assertSame('>', $condition->operator);
        $this->assertSame('18', $condition->right);
    }


    public function test_parse_fails_without_define(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/Expected query to start with \'DEFINE\' keyword, found \'KEYWORD\(SELECT\)\' at position [0-9]+/'
        );

        $query = "SELECT id FROM users WHERE age > 18;";
        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();

        $parser = new Parser($tokens);
        $parser->parse(); // Should throw!
    }

    public function test_define_no_headers(): void
    {
        $query = "DEFINE 'users.csv' AS data WITHOUT HEADERS COLUMNS (id, name, age) SELECT id FROM data WHERE age > 18;";
        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();
        $parser = new Parser($tokens);

        $ast = $parser->parse();

        $this->assertFalse($ast->define->hasHeaders); // no headers!
    }


    public function test_expect_throws_exception_on_malformed_define(): void
    {
        $query = "DEFINE users.csv SELECT id FROM users;";
        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();
        $parser = new Parser($tokens);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected token KEYWORD\(AS\)|STRING/'); // adjust regex for your message

        $parser->parse();
    }

    public function test_define_without_alias(): void
    {
        $query = "DEFINE 'users.csv' WITH HEADERS COLUMNS (id, name, age) SELECT id FROM users WHERE age > 18;";
        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();
        $parser = new Parser($tokens);

        $ast = $parser->parse();

        $this->assertSame('users', $ast->define->alias); // fallback alias!
    }

    public function testDefineWithInvalidAliasThrowsException(): void
    {
        $query = "DEFINE 'users.csv' AS 'bad-alias' SELECT id FROM bad-alias WHERE age > 18;";
        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();
        $parser = new Parser($tokens);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid alias name/');

        $parser->parse();
    }

    public function testInvalidAliasValueThrowsException(): void
    {
        $query = "DEFINE 'users.csv' AS 'bad-alias' SELECT id FROM 'bad-alias' WHERE age > 18;";
        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();
        $parser = new Parser($tokens);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid alias name/');

        $parser->parse();
    }

    public function testAliasTokenTypeInvalidThrowsException(): void
    {
        $query = "DEFINE 'users.csv' AS 123 SELECT id FROM 123 WHERE age > 18;";
        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();
        $parser = new Parser($tokens);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected alias name after AS/');

        $parser->parse();
    }

    public function testInvalidColumnNameThrowsException(): void
    {
        $query = "DEFINE 'users.csv' AS data WITH HEADERS COLUMNS (123, id) SELECT id FROM data WHERE age > 18;";
        $tokenizer = new Tokenizer($query);
        $tokens = $tokenizer->tokenize();
        $parser = new Parser($tokens);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected column name/');

        $parser->parse();
    }

}