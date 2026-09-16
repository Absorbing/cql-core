<?php

namespace Engine;

use CQL\CQL;
use CQL\Exceptions\InterpreterException;
use PHPUnit\Framework\TestCase;

class WriterTest extends TestCase
{
    private string $testFile;

    protected function setUp(): void
    {
        $this->testFile = sys_get_temp_dir() . '/test_' . uniqid() . '.csv';
        file_put_contents($this->testFile, "id,name,age\n1,Alice,30\n2,Bob,25\n3,Carol,17\n");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    private function define(): string
    {
        return "DEFINE '{$this->testFile}' AS users WITH HEADERS ";
    }

    public function test_insert_with_columns(): void
    {
        $cql = new CQL();

        $affected = $cql->statement(
            $this->define() . "INSERT INTO users (id, name, age) VALUES (4, 'Dave', 41)"
        );

        $this->assertSame(1, $affected);
        $this->assertSame(4, $cql->count($this->define() . "SELECT * FROM users"));
        $this->assertSame(1, $cql->count($this->define() . "SELECT * FROM users WHERE name = 'Dave'"));
    }

    public function test_insert_multiple_tuples(): void
    {
        $cql = new CQL();

        $affected = $cql->statement(
            $this->define() . "INSERT INTO users (id, name, age) VALUES (4, 'Dave', 41), (5, 'Eve', 29)"
        );

        $this->assertSame(2, $affected);
        $this->assertSame(5, $cql->count($this->define() . "SELECT * FROM users"));
    }

    public function test_insert_positional(): void
    {
        $cql = new CQL();

        $affected = $cql->statement(
            $this->define() . "INSERT INTO users VALUES (4, 'Dave', 41)"
        );

        $this->assertSame(1, $affected);
        $this->assertSame(1, $cql->count($this->define() . "SELECT * FROM users WHERE id = 4"));
    }

    public function test_insert_positional_count_mismatch_throws(): void
    {
        $cql = new CQL();

        $this->expectException(InterpreterException::class);
        $cql->statement($this->define() . "INSERT INTO users VALUES (4, 'Dave')");
    }

    public function test_update_with_string_where(): void
    {
        $cql = new CQL();

        $affected = $cql->statement(
            $this->define() . "UPDATE users SET age = 31 WHERE name = 'Alice'"
        );

        $this->assertSame(1, $affected);

        $row = $cql->first($this->define() . "SELECT age FROM users WHERE name = 'Alice'");
        $this->assertSame('31', $row['age']);
    }

    public function test_update_with_expression(): void
    {
        $cql = new CQL();

        $affected = $cql->statement(
            $this->define() . "UPDATE users SET age = age + 1 WHERE id = 1"
        );

        $this->assertSame(1, $affected);

        $row = $cql->first($this->define() . "SELECT age FROM users WHERE id = 1");
        $this->assertSame('31', $row['age']);
    }

    public function test_update_multiple_assignments(): void
    {
        $cql = new CQL();

        $cql->statement(
            $this->define() . "UPDATE users SET name = 'Robert', age = 26 WHERE id = 2"
        );

        $row = $cql->first($this->define() . "SELECT name, age FROM users WHERE id = 2");
        $this->assertSame('Robert', $row['name']);
        $this->assertSame('26', $row['age']);
    }

    public function test_update_without_where_affects_all_rows(): void
    {
        $cql = new CQL();

        $affected = $cql->statement($this->define() . "UPDATE users SET age = 0");

        $this->assertSame(3, $affected);
        $this->assertSame(3, $cql->count($this->define() . "SELECT * FROM users WHERE age = 0"));
    }

    public function test_delete_with_where(): void
    {
        $cql = new CQL();

        $affected = $cql->statement($this->define() . "DELETE FROM users WHERE age < 18");

        $this->assertSame(1, $affected);
        $this->assertSame(2, $cql->count($this->define() . "SELECT * FROM users"));
        $this->assertSame(0, $cql->count($this->define() . "SELECT * FROM users WHERE name = 'Carol'"));
    }

    public function test_delete_without_where_removes_all_rows(): void
    {
        $cql = new CQL();

        $affected = $cql->statement($this->define() . "DELETE FROM users");

        $this->assertSame(3, $affected);
        $this->assertSame(0, $cql->count($this->define() . "SELECT * FROM users"));

        // Header line must survive a full delete
        $this->assertSame("id,name,age\n", file_get_contents($this->testFile));
    }

    public function test_execute_returns_affected_rows_collection(): void
    {
        $cql = new CQL();

        $result = $cql->query(
            $this->define() . "INSERT INTO users (id, name, age) VALUES (4, 'Dave', 41)"
        );

        $this->assertSame([['affected_rows' => 1]], $result);
    }

    public function test_statement_rejects_select(): void
    {
        $cql = new CQL();

        $this->expectException(InterpreterException::class);
        $cql->statement($this->define() . "SELECT * FROM users");
    }

    public function test_update_on_undefined_alias_throws(): void
    {
        $cql = new CQL();

        $this->expectException(InterpreterException::class);
        $cql->statement($this->define() . "UPDATE missing SET age = 1");
    }

    public function test_streaming_update_matches_normal_mode(): void
    {
        $streaming = CQL::streaming();

        $affected = $streaming->statement(
            $this->define() . "UPDATE users SET age = 99 WHERE age > 20"
        );

        $this->assertSame(2, $affected);
        $this->assertSame(2, $streaming->count($this->define() . "SELECT * FROM users WHERE age = 99"));
    }

    public function test_streaming_delete_matches_normal_mode(): void
    {
        $streaming = CQL::streaming();

        $affected = $streaming->statement($this->define() . "DELETE FROM users WHERE age >= 25");

        $this->assertSame(2, $affected);
        $this->assertSame(1, $streaming->count($this->define() . "SELECT * FROM users"));
    }

    /**
     * Regression: string literals in WHERE clauses previously resolved as
     * (missing) column references and matched nothing.
     */
    public function test_string_literal_where_matches_rows(): void
    {
        $cql = new CQL();

        $this->assertSame(
            1,
            $cql->count($this->define() . "SELECT * FROM users WHERE name = 'Alice'")
        );
    }

    /**
     * Regression: the IN comparison operator previously matched inside
     * identifiers and keywords (INSERT -> IN + SERT, index -> IN + dex).
     */
    public function test_identifiers_starting_with_in_tokenize_correctly(): void
    {
        $tokenizer = new \CQL\Lexer\Tokenizer('SELECT index FROM t');
        $tokens = $tokenizer->tokenize();

        $this->assertSame('IDENTIFIER', $tokens[1]->type);
        $this->assertSame('index', $tokens[1]->value);
    }
}
