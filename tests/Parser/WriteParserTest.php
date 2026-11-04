<?php

namespace Parser;

use CQL\Lexer\Tokenizer;
use CQL\Parser\Nodes\AssignmentNode;
use CQL\Parser\Nodes\DeleteNode;
use CQL\Parser\Nodes\ExpressionNode;
use CQL\Parser\Nodes\InsertNode;
use CQL\Parser\Nodes\QueryNode;
use CQL\Parser\Nodes\UpdateNode;
use CQL\Parser\Parser;
use CQL\Exceptions\SyntaxException;
use PHPUnit\Framework\TestCase;

class WriteParserTest extends TestCase
{
    /**
     * @param string $query
     * @return mixed
     */
    private function parse(string $query): mixed
    {
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());

        return $parser->parse();
    }

    public function test_select_still_returns_query_node(): void
    {
        $ast = $this->parse("DEFINE 'users.csv' AS users WITH HEADERS SELECT * FROM users");

        $this->assertInstanceOf(QueryNode::class, $ast);
    }

    public function test_parse_insert_with_columns(): void
    {
        $ast = $this->parse(
            "DEFINE 'users.csv' AS users WITH HEADERS INSERT INTO users (name, age) VALUES ('Alice', 30)"
        );

        $this->assertInstanceOf(InsertNode::class, $ast);
        $this->assertSame('users', $ast->table);
        $this->assertSame(['name', 'age'], $ast->columns);
        $this->assertCount(1, $ast->rows);
        $this->assertSame(['Alice', 30], array_map(fn($node) => $node->value, $ast->rows[0]));
        $this->assertCount(1, $ast->getDefines());
    }

    public function test_parse_insert_multiple_value_tuples(): void
    {
        $ast = $this->parse(
            "DEFINE 'users.csv' AS users WITH HEADERS INSERT INTO users (name, age) VALUES ('Alice', 30), ('Bob', 25);"
        );

        $this->assertInstanceOf(InsertNode::class, $ast);
        $this->assertCount(2, $ast->rows);
        $this->assertSame(['Bob', 25], array_map(fn($node) => $node->value, $ast->rows[1]));
    }

    public function test_parse_insert_positional(): void
    {
        $ast = $this->parse(
            "DEFINE 'users.csv' AS users WITH HEADERS INSERT INTO users VALUES (1, 'Alice', 30)"
        );

        $this->assertInstanceOf(InsertNode::class, $ast);
        $this->assertSame([], $ast->columns);
        $this->assertCount(1, $ast->rows);
    }

    public function test_parse_insert_value_count_mismatch_throws(): void
    {
        $this->expectException(SyntaxException::class);

        $this->parse(
            "DEFINE 'users.csv' AS users WITH HEADERS INSERT INTO users (name, age) VALUES ('Alice')"
        );
    }

    public function test_parse_update_with_where(): void
    {
        $ast = $this->parse(
            "DEFINE 'users.csv' AS users WITH HEADERS UPDATE users SET age = 31 WHERE name = 'Alice'"
        );

        $this->assertInstanceOf(UpdateNode::class, $ast);
        $this->assertSame('users', $ast->table);
        $this->assertCount(1, $ast->assignments);
        $this->assertInstanceOf(AssignmentNode::class, $ast->assignments[0]);
        $this->assertSame('age', $ast->assignments[0]->column);
        $this->assertSame(31, $ast->assignments[0]->expression->value);
        $this->assertNotNull($ast->where);
    }

    public function test_parse_update_multiple_assignments(): void
    {
        $ast = $this->parse(
            "DEFINE 'users.csv' AS users WITH HEADERS UPDATE users SET name = 'Bob', age = 26"
        );

        $this->assertInstanceOf(UpdateNode::class, $ast);
        $this->assertCount(2, $ast->assignments);
        $this->assertNull($ast->where);
    }

    public function test_parse_update_with_expression_assignment(): void
    {
        $ast = $this->parse(
            "DEFINE 'users.csv' AS users WITH HEADERS UPDATE users SET age = age + 1"
        );

        $this->assertInstanceOf(UpdateNode::class, $ast);
        $this->assertInstanceOf(ExpressionNode::class, $ast->assignments[0]->expression);
    }

    public function test_parse_delete_with_where(): void
    {
        $ast = $this->parse(
            "DEFINE 'users.csv' AS users WITH HEADERS DELETE FROM users WHERE age < 18"
        );

        $this->assertInstanceOf(DeleteNode::class, $ast);
        $this->assertSame('users', $ast->table);
        $this->assertNotNull($ast->where);
    }

    public function test_parse_delete_without_where(): void
    {
        $ast = $this->parse(
            "DEFINE 'users.csv' AS users WITH HEADERS DELETE FROM users"
        );

        $this->assertInstanceOf(DeleteNode::class, $ast);
        $this->assertNull($ast->where);
    }
}
