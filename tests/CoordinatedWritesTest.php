<?php

use CQL\CQL;
use CQL\Data\CSVDataSource;
use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;
use PHPUnit\Framework\TestCase;

class CoordinatedWritesTest extends TestCase
{
    private string $directory;
    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cql_write_' . uniqid();
        mkdir($this->directory);
        $this->path = $this->directory . '/counter.csv';
        file_put_contents($this->path, "id,value\n1,0\n");
    }
    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') as $file) { unlink($file); }
        foreach (glob($this->directory . '/.cql-*') as $file) { unlink($file); }
        rmdir($this->directory);
    }

    private function start(string $script, array $args = []): array
    {
        if (!function_exists('proc_open')) { $this->markTestSkipped('proc_open is unavailable'); }
        $autoload = var_export(dirname(__DIR__) . '/vendor/autoload.php', true);
        $process = proc_open([PHP_BINARY, '-r', 'require ' . $autoload . '; ' . $script, ...$args], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        return [$process, $pipes];
    }

    private function finish(array $worker): string
    {
        [$process, $pipes] = $worker;
        if (is_resource($pipes[0])) { fclose($pipes[0]); }
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $output = ''; $errors = ''; $deadline = microtime(true) + 10;
        do {
            $output .= stream_get_contents($pipes[1]); $errors .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) { break; }
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
                $this->fail('Writer did not finish within 10 seconds');
            }
            usleep(1000);
        } while (true);
        $output .= stream_get_contents($pipes[1]); $errors .= stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        $this->assertSame(0, $status['exitcode'], $errors . $output);
        return $output;
    }

    public function test_update_waits_for_the_lock_before_reading_and_symlinks_share_it(): void
    {
        $alias = $this->directory . '/alias.csv';
        if (!@symlink($this->path, $alias)) { $this->markTestSkipped('Symlinks unavailable'); }
        $lock = fopen($this->path . '.cql.lock', 'c');
        flock($lock, LOCK_EX);
        $worker = $this->start('$cql = CQL\CQL::normal()->registerCsv("counter", $argv[1]); echo "ready\n"; fflush(STDOUT); $cql->statement("UPDATE counter SET value = value + 1 WHERE id = 1"); echo "done";', [$alias]);
        try {
            $this->assertSame("ready\n", fgets($worker[1][1]));
            $read = [$worker[1][1]]; $write = null; $except = null;
            $this->assertSame(0, stream_select($read, $write, $except, 0, 100000), 'The child must wait for the canonical sidecar lock');
            file_put_contents($this->path, "id,value\n1,10\n");
        } finally {
            flock($lock, LOCK_UN); fclose($lock);
        }
        $this->assertSame('done', $this->finish($worker));
        $this->assertTrue(is_link($alias));
        $this->assertSame('11', (new CQL())->registerCsv('counter', $this->path)->first('SELECT value FROM counter')['value']);
    }

    public function test_multiple_processes_preserve_increments_and_appends_during_rewrites(): void
    {
        $workers = [];
        $script = '$cql = CQL\CQL::streaming()->registerCsv("counter", $argv[1]); echo "ready\n"; fflush(STDOUT); fread(STDIN, 1); for ($i = 0; $i < 30; $i++) { if ((int)$argv[2] < 2) { $cql->statement("UPDATE counter SET value = value + 1 WHERE id = 1"); } else { $cql->prepare("INSERT INTO counter VALUES (?, ?)")->statement([(int)$argv[2] * 1000 + $i, 0]); } } echo "done";';
        for ($i = 0; $i < 4; $i++) { $workers[] = $this->start($script, [$this->path, (string)$i]); }
        foreach ($workers as $worker) { $this->assertSame("ready\n", fgets($worker[1][1])); }
        foreach ($workers as $worker) { fwrite($worker[1][0], 'g'); fflush($worker[1][0]); }
        foreach ($workers as $worker) { $this->assertSame('done', $this->finish($worker)); }
        $cql = (new CQL())->registerCsv('counter', $this->path);
        $this->assertSame(61, $cql->count('SELECT * FROM counter'));
        $this->assertSame('60', $cql->first('SELECT value FROM counter WHERE id = 1')['value']);
    }

    public function test_failed_rewrite_leaves_original_and_releases_the_lock(): void
    {
        $source = new CSVDataSource($this->path, CSVHeaderMode::WITH_HEADERS, alias: 'counter', streaming: true);
        $source->load();
        $original = file_get_contents($this->path);
        $rows = (function () { yield ['id' => '2', 'value' => '2']; throw new RuntimeException('Interrupted transformation'); })();
        try {
            $source->withWriteLock(fn() => $source->rewriteFrom($rows));
            $this->fail('Transformation should fail');
        } catch (RuntimeException $error) {
            $this->assertSame('Interrupted transformation', $error->getMessage());
        }
        $this->assertSame($original, file_get_contents($this->path));
        $this->assertSame([], glob($this->directory . '/.cql-*'));
        $this->assertSame(1, $source->appendRows([['id' => '2', 'value' => '2']]));
        $this->assertSame(1, (new CQL())->registerCsv('counter', $this->path)->statement('DELETE FROM counter WHERE id = 2'));
    }

    public function test_invalid_append_batch_writes_nothing(): void
    {
        $source = new CSVDataSource($this->path, CSVHeaderMode::WITH_HEADERS);
        $original = file_get_contents($this->path);
        try {
            $source->appendRows([['id' => '2', 'value' => '2'], ['unexpected' => 'bad']]);
            $this->fail('Invalid batch should fail');
        } catch (DataSourceException $error) {
            $this->assertStringContainsString('Unknown column', $error->getMessage());
        }
        $this->assertSame($original, file_get_contents($this->path));
        $this->assertSame(1, $source->appendRows([['id' => '2', 'value' => '2']]));
    }
}
