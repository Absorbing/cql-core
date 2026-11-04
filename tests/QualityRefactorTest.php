<?php

use CQL\CQL;
use CQL\Data\CSVDataSource;
use CQL\Data\Contracts\SchemaDataSourceInterface;
use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\ParameterException;
use CQL\Parser\Nodes\Contracts\StatementNodeInterface;
use PHPUnit\Framework\TestCase;

final class QualityRefactorTest extends TestCase
{
    private string $directory;
    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cql_quality_' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->path = $this->directory . '/users.csv';
        file_put_contents($this->path, "id,name,value\n1,Alice,20\n2,Bob,40\n");
    }

    protected function tearDown(): void
    {
        foreach (array_merge(glob($this->directory . '/*'), glob($this->directory . '/.cql-*')) as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function test_grouped_wildcards_preserve_unqualified_output_names(): void
    {
        $rows = (new CQL())->registerCsv('users', $this->path)
            ->query('SELECT *, COUNT(*) AS total FROM users GROUP BY name');

        $this->assertSame([
            ['name' => 'Alice', 'total' => 1],
            ['name' => 'Bob', 'total' => 1],
        ], $rows);
    }

    public function test_all_prepared_mutation_apis_preserve_the_connections_write_override(): void
    {
        $connection = new class extends CQL {
            public array $bindings = [];

            public function writeParsed(StatementNodeInterface $ast, array $parameters = []): int
            {
                $this->bindings[] = $parameters;
                return 7;
            }
        };
        $prepared = $connection->prepare('DELETE FROM intentionally_unregistered WHERE id = :id');

        $this->assertSame([['affected_rows' => 7]], $prepared->query(['id' => 1]));
        $this->assertSame(7, $prepared->run(['id' => 2])->affectedRows());
        $this->assertSame(7, $prepared->statement(['id' => 3]));
        $this->assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $connection->bindings);
    }

    public function test_prepared_calls_observe_later_registrations_and_streaming_settings(): void
    {
        $connection = (new CQL(['streaming' => false]))->registerCsv('users', $this->path);
        $prepared = $connection->prepare('SELECT name FROM users');
        $replacement = $this->directory . '/replacement.csv';
        file_put_contents($replacement, "id,name,value\n3,Carol,60\n");
        $connection->registerCsv('users', $replacement)->setStreaming(true);
        $result = $prepared->run();

        // A cursor must still be lazy after changing connection settings.
        file_put_contents($replacement, "id,name,value\n4,Dave,80\n");
        $this->assertSame(['name'], array_column($result->columns(), 'name'));
        $this->assertSame([['name' => 'Dave']], $result->fetchAll());
    }

    public function test_empty_aggregates_preserve_zero_and_null_result_types(): void
    {
        $result = (new CQL())->registerCsv('users', $this->path)->run(
            'SELECT COUNT(*) AS n, SUM(value) AS total, AVG(value) AS average, MIN(value) AS lo, MAX(value) AS hi FROM users WHERE id < 0'
        );

        $this->assertSame(['n', 'total', 'average', 'lo', 'hi'], array_column($result->columns(), 'name'));
        $this->assertSame([
            ['n' => 0, 'total' => 0, 'average' => null, 'lo' => null, 'hi' => null],
        ], $result->fetchAll());
    }

    public function test_joined_wildcard_names_match_schema_when_all_short_names_collide(): void
    {
        $result = (new CQL())->registerCsv('users', $this->path)->registerCsv('other', $this->path)
            ->run('SELECT * FROM users JOIN other ON users.id = other.id');
        $names = ['users.id', 'users.name', 'users.value', 'other.id', 'other.name', 'other.value'];

        $this->assertSame($names, array_column($result->columns(), 'name'));
        $this->assertSame([
            'users.id' => '1', 'users.name' => 'Alice', 'users.value' => '20',
            'other.id' => '1', 'other.name' => 'Alice', 'other.value' => '20',
        ], $result->fetch());
    }

    public function test_rewrite_infers_headers_that_remain_available_to_later_appends(): void
    {
        file_put_contents($this->path, '');
        $source = new CSVDataSource($this->path, CSVHeaderMode::WITH_HEADERS);
        $source->rewriteFrom((function () {
            yield ['id' => '1', 'name' => 'First'];
        })());

        $this->assertSame(['id', 'name'], $source->getHeaders());
        $this->assertSame(1, $source->appendRows([['name' => 'Second', 'id' => '2']]));
        $this->assertSame("id,name\n1,First\n2,Second\n", file_get_contents($this->path));
    }

    public function test_aggregates_distinguish_null_boolean_and_numeric_custom_values(): void
    {
        $connection = (new CQL())->registerSource('items', fn() => new class implements SchemaDataSourceInterface {
            public function load(): void {}
            public function getHeaders(): ?array { return ['id', 'value']; }
            public function getRows(): array
            {
                return [
                    ['id' => 1, 'value' => null], ['id' => 2, 'value' => '2'],
                    ['id' => 3, 'value' => false], ['id' => 4, 'value' => 0],
                ];
            }
        });

        $this->assertSame([
            ['n' => 3, 'total' => 2.0, 'average' => 1.0, 'lo' => 0, 'hi' => '2'],
        ], $connection->query('SELECT COUNT(value) AS n, SUM(value) AS total, AVG(value) AS average, MIN(value) AS lo, MAX(value) AS hi FROM items'));
    }

    public function test_non_finite_bindings_keep_source_positions_and_never_reach_writes(): void
    {
        $sql = '/* π */ UPDATE users SET value = :value WHERE id = :id';
        $query = (new CQL())->registerCsv('users', $this->path)->prepare($sql);
        $original = file_get_contents($this->path);

        foreach ([INF, -INF, NAN] as $value) {
            try {
                $query->statement([':value' => $value, 'id' => 1]);
                $this->fail('Non-finite bindings must fail before writing');
            } catch (ParameterException $error) {
                $this->assertSame(strpos($sql, ':value'), $error->position);
                $this->assertSame($original, file_get_contents($this->path));
            }
        }
    }
}
