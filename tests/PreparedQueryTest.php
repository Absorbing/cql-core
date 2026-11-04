<?php

use CQL\CQL;
use CQL\Exceptions\CQLException;
use CQL\Exceptions\ParameterException;
use PHPUnit\Framework\TestCase;

class PreparedQueryTest extends TestCase
{
    private string $path;
    private string $define;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/cql_bind_' . uniqid() . '.csv';
        file_put_contents($this->path, "id,name,age\n1,Alice,20\n2,Bob,40\n");
        $this->define = "DEFINE '{$this->path}' AS u WITH HEADERS ";
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_prepare_does_not_read_files_and_each_execution_reads_fresh_data(): void
    {
        unlink($this->path);
        $query = (new CQL())->prepare($this->define . 'SELECT name FROM u WHERE age >= :age');
        file_put_contents($this->path, "id,name,age\n1,Alice,20\n2,Bob,40\n");
        $this->assertSame([1 => ['name' => 'Bob']], $query->query(['age' => 30]));
        file_put_contents($this->path, "id,name,age\n3,Carol,50\n");
        $this->assertSame([['name' => 'Carol']], $query->query([':age' => 30]));
    }

    public function test_values_are_never_interpolated_or_resolved_as_columns(): void
    {
        $cql = new CQL();
        $name = "O'Brien' OR TRUE -- :unused ?";
        $insert = $cql->prepare($this->define . 'INSERT INTO u VALUES (?, ?, ?)');
        $this->assertSame(1, $insert->statement([3, $name, 0]));
        $query = $cql->prepare($this->define . 'SELECT name FROM u WHERE name = :name');
        $this->assertSame([2 => ['name' => $name]], $query->query(['name' => $name]));
        $this->assertSame([], $query->query(['name' => 'name']));
        $this->assertSame([], $query->query(['name' => 'age']));
    }

    public function test_bindings_are_validated_before_any_write(): void
    {
        $query = (new CQL())->prepare($this->define . 'UPDATE u SET name = :name WHERE id = :id');
        $original = file_get_contents($this->path);
        foreach ([['name' => 'X'], ['name' => 'X', 'id' => 1, 'extra' => 1], ['name' => [], 'id' => 1], ['name' => 'X', 'id' => INF], ['name' => 'X', ':name' => 'Y', 'id' => 1]] as $values) {
            try {
                $query->execute($values);
                $this->fail('Invalid binding should fail');
            } catch (ParameterException $error) {
                $this->assertSame('CQL_PARAMETER_ERROR', $error->errorCode);
                $this->assertSame($original, file_get_contents($this->path));
            }
        }
    }

    public function test_parenthesised_parameters_restore_speculative_parser_state(): void
    {
        $cql = new CQL();
        $named = $cql->prepare($this->define . 'SELECT name FROM u WHERE (age + :extra) >= :minimum');
        $this->assertSame([1 => ['name' => 'Bob']], $named->query(['extra' => 5, 'minimum' => 30]));
        $positional = $cql->prepare($this->define . 'SELECT name FROM u WHERE (age + ?) >= ?');
        $this->assertSame([1 => ['name' => 'Bob']], $positional->query([5, 30]));
    }

    public function test_parameter_styles_and_identifier_positions_are_rejected(): void
    {
        foreach (['SELECT * FROM u WHERE id = :id OR id = ?', 'SELECT * FROM u WHERE id = :id OR age = :id', 'SELECT * FROM :table', 'UPDATE u SET :column = 1'] as $sql) {
            try {
                (new CQL())->prepare($this->define . $sql);
                $this->fail('Invalid parameter template should fail');
            } catch (CQLException $error) {
                $this->assertNotNull($error->position);
            }
        }
    }

    public function test_typed_null_boolean_and_negative_parameters(): void
    {
        $cql = new CQL();
        $insert = $cql->prepare($this->define . 'INSERT INTO u VALUES (?, ?, ?)');
        $this->assertSame(1, $insert->statement([-1, false, null]));
        $this->assertSame([2 => ['id' => '-1', 'name' => 'FALSE', 'age' => '']], $cql->prepare($this->define . 'SELECT * FROM u WHERE id = ?')->query([-1]));
        $this->assertSame([], $cql->prepare($this->define . 'SELECT * FROM u WHERE ?')->query([false]));
        $this->assertCount(3, $cql->prepare($this->define . 'SELECT * FROM u WHERE ?')->query([true]));
    }

    public function test_placeholders_inside_literals_and_comments_are_ignored(): void
    {
        $query = (new CQL())->prepare($this->define . "SELECT name FROM u WHERE name = ':literal ?' /* :comment ? */");
        $this->assertSame([], $query->query());
    }

    public function test_function_parameters_and_in_lists(): void
    {
        $cql = new CQL();
        $this->assertCount(2, $cql->prepare($this->define . 'SELECT * FROM u WHERE id IN (?, ?)')->query([1, 2]));
        $this->assertSame([['yr' => 2026], ['yr' => 2026]], $cql->prepare($this->define . 'SELECT YEAR(:date) AS yr FROM u')->query(['date' => '2026-01-01']));
    }
}
