<?php

namespace Engine;

use CQL\CQL;
use CQL\Exceptions\SyntaxException;
use PHPUnit\Framework\TestCase;

class WhereConditionTest extends TestCase
{
    private string $testFile;

    protected function setUp(): void
    {
        $this->testFile = sys_get_temp_dir() . '/test_' . uniqid() . '.csv';
        file_put_contents(
            $this->testFile,
            "id,name,age,city,email\n" .
            "1,Alice,30,Bristol,alice@example.com\n" .
            "2,Bob,25,Taunton,\n" .
            "3,Carol,17,Bristol,carol@example.com\n" .
            "4,Dave,41,Exeter,\n" .
            "5,Eve,25,Bristol,eve@example.com\n"
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    private function countWhere(string $where): int
    {
        return (new CQL())->count(
            "DEFINE '{$this->testFile}' AS u WITH HEADERS SELECT * FROM u WHERE {$where}"
        );
    }

    private function namesWhere(string $where): array
    {
        $rows = (new CQL())->query(
            "DEFINE '{$this->testFile}' AS u WITH HEADERS SELECT name FROM u WHERE {$where}"
        );

        return array_column($rows, 'name');
    }

    // --- AND / OR ---

    public function test_and_combines_conditions(): void
    {
        $this->assertSame(['Alice', 'Eve'], $this->namesWhere("city = 'Bristol' AND age >= 25"));
    }

    public function test_or_combines_conditions(): void
    {
        $this->assertSame(['Bob', 'Dave', 'Eve'], $this->namesWhere("age = 25 OR city = 'Exeter'"));
    }

    public function test_and_binds_tighter_than_or(): void
    {
        // a OR (b AND c), not (a OR b) AND c - were precedence wrong,
        // only Bob (Taunton, 25) would match
        $this->assertSame(
            ['Alice', 'Bob', 'Carol', 'Eve'],
            $this->namesWhere("city = 'Bristol' OR age = 25 AND city = 'Taunton'")
        );
    }

    public function test_parentheses_override_precedence(): void
    {
        $this->assertSame(
            ['Eve'],
            $this->namesWhere("(city = 'Bristol' OR age = 25) AND name = 'Eve'")
        );
    }

    public function test_multiple_ands_chain(): void
    {
        $this->assertSame(
            ['Eve'],
            $this->namesWhere("city = 'Bristol' AND age = 25 AND name = 'Eve'")
        );
    }

    // --- NOT ---

    public function test_not_negates_condition(): void
    {
        $this->assertSame(['Bob', 'Dave'], $this->namesWhere("NOT city = 'Bristol'"));
    }

    public function test_not_with_parenthesised_group(): void
    {
        $this->assertSame(['Dave'], $this->namesWhere("NOT (city = 'Bristol' OR age = 25)"));
    }

    public function test_double_not(): void
    {
        $this->assertSame(3, $this->countWhere("NOT NOT city = 'Bristol'"));
    }

    public function test_not_binds_tighter_than_and(): void
    {
        // (NOT a) AND b
        $this->assertSame(['Dave'], $this->namesWhere("NOT city = 'Bristol' AND age > 30"));
    }

    // --- IN / NOT IN ---

    public function test_in_with_numbers(): void
    {
        $this->assertSame(['Alice', 'Bob', 'Eve'], $this->namesWhere("age IN (25, 30)"));
    }

    public function test_in_with_strings(): void
    {
        $this->assertSame(['Alice', 'Bob', 'Carol', 'Eve'], $this->namesWhere("city IN ('Bristol', 'Taunton')"));
    }

    public function test_in_single_value(): void
    {
        $this->assertSame(['Dave'], $this->namesWhere("city IN ('Exeter')"));
    }

    public function test_not_in(): void
    {
        $this->assertSame(['Carol', 'Dave'], $this->namesWhere("age NOT IN (25, 30)"));
    }

    public function test_not_in_combined_with_and(): void
    {
        $this->assertSame(['Carol'], $this->namesWhere("age NOT IN (25, 30) AND city = 'Bristol'"));
    }

    public function test_negated_in_via_not_keyword(): void
    {
        // NOT (x IN ...) through the unary NOT path
        $this->assertSame(['Carol', 'Dave'], $this->namesWhere("NOT age IN (25, 30)"));
    }

    // --- EXISTS ---

    public function test_exists_matches_non_empty_values(): void
    {
        $this->assertSame(['Alice', 'Carol', 'Eve'], $this->namesWhere("EXISTS email"));
    }

    public function test_not_exists_matches_empty_values(): void
    {
        $this->assertSame(['Bob', 'Dave'], $this->namesWhere("NOT EXISTS email"));
    }

    public function test_exists_on_missing_column_is_false(): void
    {
        $this->assertSame(0, $this->countWhere("EXISTS nonexistent_column"));
    }

    public function test_exists_combined_with_condition(): void
    {
        $this->assertSame(['Alice', 'Eve'], $this->namesWhere("EXISTS email AND age >= 25"));
    }

    // --- Expressions inside conditions ---

    public function test_parenthesised_expression_predicate_still_works(): void
    {
        // (a + b) > c must parse as an expression, not a condition group
        $this->assertSame(['Alice', 'Dave'], $this->namesWhere("(age + 10) > 39"));
    }

    public function test_expression_predicate_in_condition_tree(): void
    {
        $this->assertSame(['Dave'], $this->namesWhere("(age + 10) > 39 AND city = 'Exeter'"));
    }

    // --- Writes honour complex WHERE ---

    public function test_update_with_complex_where(): void
    {
        $cql = new CQL();
        $define = "DEFINE '{$this->testFile}' AS u WITH HEADERS ";

        $affected = $cql->statement(
            $define . "UPDATE u SET city = 'Bath' WHERE age IN (25, 30) AND NOT name = 'Bob'"
        );

        $this->assertSame(2, $affected);
        $this->assertSame(2, $cql->count($define . "SELECT * FROM u WHERE city = 'Bath'"));
    }

    public function test_delete_with_complex_where(): void
    {
        $cql = new CQL();
        $define = "DEFINE '{$this->testFile}' AS u WITH HEADERS ";

        $affected = $cql->statement(
            $define . "DELETE FROM u WHERE NOT EXISTS email OR age < 18"
        );

        $this->assertSame(3, $affected); // Bob, Carol, Dave
        $this->assertSame(2, $cql->count($define . "SELECT * FROM u"));
    }

    // --- Strictness ---

    public function test_trailing_tokens_after_where_throw(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('after end of statement');

        $this->countWhere("age = 25 banana");
    }

    public function test_dangling_condition_no_longer_silently_dropped(): void
    {
        // Pre-0.2.0 this silently ignored everything after the first condition
        $this->assertSame(1, $this->countWhere("age = 25 AND city = 'Taunton'"));
    }
}
