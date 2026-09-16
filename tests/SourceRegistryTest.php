<?php

use CQL\CQL;
use CQL\Data\Contracts\DataSourceInterface;
use CQL\Data\Contracts\SchemaDataSourceInterface;
use CQL\Data\Contracts\WritableDataSourceInterface;
use CQL\Exceptions\DataSourceException;
use CQL\Exceptions\InterpreterException;
use PHPUnit\Framework\TestCase;

class RegistryMemorySource implements SchemaDataSourceInterface, WritableDataSourceInterface
{
    private array $rows;
    public function __construct(array &$rows) { $this->rows =& $rows; }
    public function load(): void {}
    public function getRows(): array { return $this->rows; }
    public function getHeaders(): ?array { return ['id', 'name']; }
    public function appendRows(array $rows): int { array_push($this->rows, ...$rows); return count($rows); }
    public function rewriteFrom(iterable $rows): void { $this->rows = iterator_to_array((function () use ($rows) { yield from $rows; })(), false); }
}

class SourceRegistryTest extends TestCase
{
    private string $path;
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/cql_registry_' . uniqid() . '.csv';
        file_put_contents($this->path, "id,name\n1,Alice\n");
    }
    protected function tearDown(): void { unlink($this->path); }

    public function test_registered_csv_supports_preparation_and_mutations_without_define(): void
    {
        $cql = (new CQL())->registerCsv('users', $this->path);
        $query = $cql->prepare('SELECT name FROM users WHERE id = :id');
        $this->assertSame([['name' => 'Alice']], $query->query(['id' => 1]));
        $this->assertSame(1, $cql->statement("UPDATE users SET name = 'Bob' WHERE id = 1"));
        $this->assertSame([['name' => 'Bob']], $query->query(['id' => 1]));
    }

    public function test_inline_definitions_override_only_the_current_statement(): void
    {
        $rows = [['id' => 2, 'name' => 'Memory']];
        $cql = (new CQL())->registerSource('users', fn() => new RegistryMemorySource($rows));
        $this->assertSame([['name' => 'Alice']], $cql->query("DEFINE '{$this->path}' AS users WITH HEADERS SELECT name FROM users"));
        $this->assertSame([['name' => 'Memory']], $cql->query('SELECT name FROM users'));
    }

    public function test_registrations_do_not_leak_between_instances_and_can_be_removed(): void
    {
        $one = (new CQL())->registerCsv('users', $this->path);
        $this->assertSame(1, $one->count('SELECT * FROM users'));
        foreach ([new CQL(), $one->unregisterSource('users')] as $cql) {
            try {
                $cql->query('SELECT * FROM users');
                $this->fail('Unknown source should fail');
            } catch (InterpreterException $error) {
                $this->assertSame('users', $error->context['alias']);
            }
        }
    }

    public function test_custom_source_interfaces_are_used_for_reads_joins_and_writes(): void
    {
        $rows = [['id' => '1', 'name' => 'Memory']];
        $factory = function () use (&$rows) { return new RegistryMemorySource($rows); };
        $cql = (new CQL())->registerSource('memory', $factory)->registerCsv('users', $this->path);
        $this->assertSame([['csv' => 'Alice', 'other' => 'Memory']], $cql->query('SELECT users.name AS csv, memory.name AS other FROM users JOIN memory ON users.id = memory.id'));
        $this->assertSame(1, $cql->statement("INSERT INTO memory VALUES (2, 'Added')"));
        $this->assertSame(1, $cql->statement("UPDATE memory SET name = 'Updated' WHERE id = 1"));
        $this->assertSame('Updated', $rows[0]['name']);
        $this->assertSame(1, $cql->statement('DELETE FROM memory WHERE id = 2'));
        $this->assertCount(1, $rows);
    }

    public function test_read_only_sources_reject_mutations(): void
    {
        $cql = (new CQL())->registerSource('items', fn() => new class implements DataSourceInterface {
            public function load(): void {}
            public function getRows(): array { return [['id' => '1']]; }
        });
        $this->assertSame([['id' => '1']], $cql->query('SELECT * FROM items'));
        $this->expectException(DataSourceException::class);
        $this->expectExceptionMessage('read-only');
        $cql->statement('DELETE FROM items');
    }

    public function test_registration_does_not_open_files_and_supports_delimiters(): void
    {
        $cql = (new CQL())->registerCsv('missing', $this->path . '.missing');
        $this->assertInstanceOf(CQL::class, $cql);
        file_put_contents($this->path, "id;name\n1;Alice\n");
        $cql->registerCsv('users', $this->path, delimiter: ';');
        $this->assertSame([['name' => 'Alice']], $cql->query('SELECT name FROM users'));
    }

    public function test_invalid_and_duplicate_aliases_fail_explicitly(): void
    {
        try {
            (new CQL())->registerCsv('bad-alias', $this->path);
            $this->fail('Invalid alias should fail');
        } catch (DataSourceException $error) {
            $this->assertStringContainsString('Invalid source alias', $error->getMessage());
        }
        $this->expectException(DataSourceException::class);
        $this->expectExceptionMessage('Duplicate DEFINE');
        (new CQL())->query("DEFINE '{$this->path}' AS u WITH HEADERS DEFINE '{$this->path}' AS u WITH HEADERS SELECT * FROM u");
    }
}
