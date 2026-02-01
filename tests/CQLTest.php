<?php

use CQL\CQL;
use PHPUnit\Framework\TestCase;

class CQLTest extends TestCase
{
    private string $testFile;

    protected function setUp(): void
    {
        $this->testFile = sys_get_temp_dir() . '/test_' . uniqid() . '.csv';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    public function test_execute_returns_collection(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $cql = new CQL();
        $results = $cql->execute("
            DEFINE '{$this->testFile}' AS data WITH HEADERS
            SELECT * FROM data
        ");

        $this->assertInstanceOf(\CQL\Data\Support\Collection::class, $results);
        $this->assertSame(2, $results->count());
    }

    public function test_query_returns_array(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $cql = new CQL();
        $results = $cql->query("
            DEFINE '{$this->testFile}' AS data WITH HEADERS
            SELECT * FROM data
        ");

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    public function test_first_returns_first_row(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $cql = new CQL();
        $first = $cql->first("
            DEFINE '{$this->testFile}' AS data WITH HEADERS
            SELECT * FROM data
        ");

        $this->assertIsArray($first);
        $this->assertSame('1', $first['id']);
        $this->assertSame('Alice', $first['name']);
    }

    public function test_count_returns_row_count(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob\n3,Charlie");

        $cql = new CQL();
        $count = $cql->count("
            DEFINE '{$this->testFile}' AS data WITH HEADERS
            SELECT * FROM data
        ");

        $this->assertSame(3, $count);
    }

    public function test_static_streaming_factory(): void
    {
        $cql = CQL::streaming();
        $this->assertTrue($cql->getStreaming());
    }

    public function test_static_normal_factory(): void
    {
        $cql = CQL::normal();
        $this->assertFalse($cql->getStreaming());
    }

    public function test_static_auto_factory(): void
    {
        $cql = CQL::auto();
        $this->assertNull($cql->getStreaming());
    }

    public function test_static_auto_factory_with_threshold(): void
    {
        $threshold = 10 * 1024 * 1024;
        $cql = CQL::auto($threshold);
        
        $this->assertNull($cql->getStreaming());
        $this->assertSame($threshold, $cql->getAutoStreamingThreshold());
    }

    public function test_constructor_with_options(): void
    {
        $cql = new CQL([
            'streaming' => true,
            'autoStreamingThreshold' => 20 * 1024 * 1024
        ]);

        $this->assertTrue($cql->getStreaming());
        $this->assertSame(20 * 1024 * 1024, $cql->getAutoStreamingThreshold());
    }

    public function test_fluent_configuration(): void
    {
        $cql = (new CQL())
            ->setStreaming(true)
            ->setAutoStreamingThreshold(30 * 1024 * 1024);

        $this->assertTrue($cql->getStreaming());
        $this->assertSame(30 * 1024 * 1024, $cql->getAutoStreamingThreshold());
    }

    public function test_where_clause_filtering(): void
    {
        file_put_contents($this->testFile, "id,name,age\n1,Alice,30\n2,Bob,25\n3,Charlie,35");

        $cql = new CQL();
        $results = $cql->query("
            DEFINE '{$this->testFile}' AS data WITH HEADERS
            SELECT name
            FROM data
            WHERE age > 26
        ");

        $this->assertCount(2, $results);
        $names = array_column($results, 'name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Charlie', $names);
        $this->assertNotContains('Bob', $names);
    }

    public function test_exception_handling(): void
    {
        $this->expectException(\CQL\Exceptions\DataSourceException::class);

        $cql = new CQL();
        $cql->execute("
            DEFINE 'nonexistent.csv' AS data WITH HEADERS
            SELECT * FROM data
        ");
    }
}
