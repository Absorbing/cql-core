<?php

use CQL\CQL;
use CQL\QueryResult;
use CQL\ResultColumn;
use CQL\Exceptions\InterpreterException;
use PHPUnit\Framework\TestCase;

class QueryResultTest extends TestCase
{
    private string $path;
    private CQL $cql;
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/cql_result_' . uniqid() . '.csv';
        file_put_contents($this->path, "id,name,birthday\n1,Alice,2000-01-01\n2,Bob,1990-01-01\n");
        $this->cql = (new CQL())->registerCsv('users', $this->path);
    }
    protected function tearDown(): void { unlink($this->path); }

    public function test_schema_is_available_for_empty_results_and_empty_headered_sources(): void
    {
        foreach ([false, true] as $emptyFile) {
            if ($emptyFile) { file_put_contents($this->path, "id,name,birthday\n"); }
            $result = $this->cql->run('SELECT name AS person, id FROM users WHERE id = :id', ['id' => 99]);
            $this->assertSame(['person', 'id'], array_column($result->columns(), 'name'));
            $this->assertSame('users', $result->columns()[0]->sourceAlias);
            $this->assertSame('name', $result->columns()[0]->sourceColumn);
            $this->assertSame([], $result->fetchAll());
            $this->assertNull($result->affectedRows());
        }
    }

    public function test_mixed_projection_matches_the_described_column_order(): void
    {
        $result = $this->cql->run('SELECT name AS person, YEAR(birthday) AS born, users.id FROM users');
        $this->assertSame(['person', 'born', 'users.id'], array_column($result->columns(), 'name'));
        $this->assertSame(['person' => 'Alice', 'born' => 2000, 'users.id' => '1'], $result->fetch());
        $this->assertSame([['person' => 'Bob', 'born' => 1990, 'users.id' => '2']], $result->fetchAll());
        $this->assertNull($result->fetch());
    }

    public function test_wildcard_metadata_and_grouped_results(): void
    {
        $result = $this->cql->run('SELECT * FROM users WHERE id = 99');
        $this->assertSame(['id', 'name', 'birthday'], array_column($result->columns(), 'name'));
        $grouped = $this->cql->run('SELECT users.name AS person, COUNT(*) AS total FROM users GROUP BY users.name');
        $this->assertSame(['person', 'total'], array_column($grouped->columns(), 'name'));
        $this->assertSame([['person' => 'Alice', 'total' => 1], ['person' => 'Bob', 'total' => 1]], $grouped->fetchAll());
    }

    public function test_mutations_report_counts_without_synthetic_rows(): void
    {
        $result = $this->cql->run('DELETE FROM users WHERE id = ?', [1]);
        $this->assertFalse($result->isQuery());
        $this->assertSame(1, $result->affectedRows());
        $this->assertSame([], $result->columns());
        $this->assertSame([], $result->fetchAll());
        $this->assertSame(0, $this->cql->run('DELETE FROM users WHERE id = ?', [99])->affectedRows());
        $this->assertSame([['affected_rows' => 0]], $this->cql->execute('DELETE FROM users WHERE id = 99')->toArray());
    }

    public function test_close_releases_a_started_generator_without_prefetching(): void
    {
        $read = 0; $released = false;
        $result = new QueryResult([new ResultColumn('id')], function () use (&$read, &$released) {
            try {
                for ($i = 0; $i < 3; $i++) {
                    $read++;
                    yield ['id' => $i];
                }
            } finally { $released = true; }
        });
        $this->assertSame(0, $read);
        $this->assertSame(['id' => 0], $result->fetch());
        $this->assertSame(1, $read);
        $result->close();
        $result->close();
        $this->assertTrue($released);
        $this->assertNull($result->fetch());
        $this->assertSame(1, $read);
    }

    public function test_duplicate_and_ambiguous_columns_are_rejected(): void
    {
        $this->expectException(InterpreterException::class);
        $this->expectExceptionMessage('Duplicate result column');
        $this->cql->run('SELECT name AS value, id AS value FROM users');
    }

    public function test_prepared_results_have_independent_cursors(): void
    {
        $prepared = $this->cql->prepare('SELECT name FROM users WHERE id = ?');
        $one = $prepared->run([1]);
        $two = $prepared->run([2]);
        $this->assertSame(['name' => 'Bob'], $two->fetch());
        $this->assertSame(['name' => 'Alice'], $one->fetch());
        $this->assertNull($one->fetch());
    }
}
