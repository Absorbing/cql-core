<?php

use CQL\CQL;
use CQL\Data\Contracts\StreamingDataSourceInterface;
use CQL\Exceptions\DataSourceException;
use PHPUnit\Framework\TestCase;

class StreamingCursorTest extends TestCase
{
    private string $path;
    protected function setUp(): void { $this->path = sys_get_temp_dir() . '/cql_stream_' . uniqid() . '.csv'; }
    protected function tearDown(): void { @unlink($this->path); }

    public function test_csv_rows_are_not_prefetched_and_errors_include_the_data_row(): void
    {
        file_put_contents($this->path, "id,name\n1,Alice\n2,Bob,invalid\n");
        $cql = CQL::streaming()->registerCsv('users', $this->path);
        $result = $cql->run('SELECT * FROM users');
        $this->assertSame(['id', 'name'], array_column($result->columns(), 'name'));
        $this->assertSame(['id' => '1', 'name' => 'Alice'], $result->fetch());
        try {
            $result->fetch();
            $this->fail('The malformed second row should fail when fetched');
        } catch (DataSourceException $error) {
            $this->assertSame(1, $error->context['row']);
            $this->assertNull($result->fetch());
        }
        $this->assertSame(['id' => '1', 'name' => 'Alice'], $cql->first('SELECT * FROM users'));
    }

    public function test_custom_streaming_source_is_consumed_only_as_far_as_needed(): void
    {
        $source = new class implements StreamingDataSourceInterface {
            public int $reads = 0;
            public bool $released = false;
            public function load(): void {}
            public function getHeaders(): ?array { return ['id']; }
            public function getRows(): array { throw new LogicException('Buffered API must not be called'); }
            public function streamRows(): iterable {
                try {
                    for ($i = 0; $i < 100; $i++) { $this->reads++; yield ['id' => $i]; }
                } finally { $this->released = true; }
            }
        };
        $result = (new CQL())->registerSource('items', fn() => $source)->run('SELECT id FROM items WHERE id >= 2');
        $this->assertSame(0, $source->reads);
        $this->assertSame(['id' => 2], $result->fetch());
        $this->assertSame(3, $source->reads);
        $result->close();
        $this->assertTrue($source->released);
        $this->assertSame(3, $source->reads);
    }

    public function test_streaming_and_buffered_results_match_for_simple_and_blocking_queries(): void
    {
        file_put_contents($this->path, "id,name\n1,Alice\n2,Bob\n3,Alice\n");
        $normal = CQL::normal()->registerCsv('users', $this->path);
        $stream = CQL::streaming()->registerCsv('users', $this->path);
        foreach (['SELECT name AS person FROM users WHERE id > 1', 'SELECT name, COUNT(*) AS total FROM users GROUP BY name', 'SELECT COUNT(*) AS total FROM users', 'SELECT users.id AS left_id, other.id AS right_id FROM users JOIN other ON users.id = other.id'] as $sql) {
            $normal->registerCsv('other', $this->path); $stream->registerCsv('other', $this->path);
            $this->assertSame($normal->run($sql)->fetchAll(), $stream->run($sql)->fetchAll());
        }
    }

    public function test_full_scan_stays_within_a_24_megabyte_process_limit(): void
    {
        if (!function_exists('proc_open')) { $this->markTestSkipped('proc_open is unavailable'); }
        $handle = fopen($this->path, 'w');
        fwrite($handle, "id,payload\n");
        $payload = str_repeat('x', 256);
        for ($i = 0; $i < 60000; $i++) { fwrite($handle, $i . ',' . $payload . "\n"); }
        fclose($handle);
        $autoload = var_export(dirname(__DIR__) . '/vendor/autoload.php', true);
        $script = 'require ' . $autoload . '; $cql = CQL\CQL::streaming()->registerCsv("items", $argv[1]); $result = $cql->run("SELECT id FROM items WHERE id >= 0"); $count = 0; foreach ($result->rows() as $row) { $count++; } echo json_encode([$count, memory_get_peak_usage(true)]);';
        $process = proc_open([PHP_BINARY, '-d', 'memory_limit=24M', '-r', $script, $this->path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors . $output);
        [$count, $peak] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(60000, $count);
        $this->assertLessThan(24 * 1024 * 1024, $peak);
    }
}
