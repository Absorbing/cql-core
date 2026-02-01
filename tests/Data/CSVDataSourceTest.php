<?php

namespace Data;

use CQL\Data\CSVDataSource;
use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;
use PHPUnit\Framework\TestCase;

class CSVDataSourceTest extends TestCase
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

    public function test_load_csv_with_headers(): void
    {
        file_put_contents($this->testFile, "id,name,age\n1,Alice,30\n2,Bob,25");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->load();
        $rows = $source->getRows();

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['users.name']);
        $this->assertSame('30', $rows[0]['users.age']);
    }

    public function test_load_csv_without_headers(): void
    {
        file_put_contents($this->testFile, "1,Alice,30\n2,Bob,25");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITHOUT_HEADERS, ',', 'users');
        $source->load();
        $rows = $source->getRows();

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['users.column_2']);
        $this->assertSame('30', $rows[0]['users.column_3']);
    }

    public function test_streaming_mode_enabled(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users', streaming: true);
        
        $this->assertTrue($source->isStreaming());
        
        $source->load();
        $rows = $source->getRows();

        $this->assertCount(2, $rows);
    }

    public function test_streaming_mode_disabled(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users', streaming: false);
        
        $this->assertFalse($source->isStreaming());
        
        $source->load();
        $rows = $source->getRows();

        $this->assertCount(2, $rows);
    }

    public function test_stream_rows_generator(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob\n3,Charlie");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users', streaming: true);
        $source->load();

        $count = 0;
        foreach ($source->streamRows() as $row) {
            $count++;
            $this->assertArrayHasKey('users.id', $row);
            $this->assertArrayHasKey('users.name', $row);
        }

        $this->assertSame(3, $count);
    }

    public function test_get_file_size(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $source = new CSVDataSource($this->testFile);
        $size = $source->getFileSize();

        $this->assertIsInt($size);
        $this->assertGreaterThan(0, $size);
    }

    public function test_get_file_size_formatted(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $source = new CSVDataSource($this->testFile);
        $formatted = $source->getFileSizeFormatted();

        $this->assertIsString($formatted);
        $this->assertStringContainsString('B', $formatted); // Contains bytes unit
    }

    public function test_set_streaming_mode(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice");

        $source = new CSVDataSource($this->testFile, streaming: false);
        $this->assertFalse($source->isStreaming());

        $source->setStreaming(true);
        $this->assertTrue($source->isStreaming());
    }

    public function test_file_not_found_throws_exception(): void
    {
        $this->expectException(DataSourceException::class);
        $this->expectExceptionMessage('File not found');

        new CSVDataSource('/nonexistent/file.csv');
    }

    public function test_streaming_and_normal_produce_same_results(): void
    {
        file_put_contents($this->testFile, "id,name,value\n1,Alice,100\n2,Bob,200\n3,Charlie,300");

        // Normal mode
        $normalSource = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'data', streaming: false);
        $normalSource->load();
        $normalRows = $normalSource->getRows();

        // Streaming mode
        $streamSource = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'data', streaming: true);
        $streamSource->load();
        $streamRows = $streamSource->getRows();

        $this->assertSame($normalRows, $streamRows);
    }

    public function test_custom_delimiter(): void
    {
        file_put_contents($this->testFile, "id;name;age\n1;Alice;30\n2;Bob;25");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ';', 'users');
        $source->load();
        $rows = $source->getRows();

        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['users.name']);
    }

    public function test_namespaced_column_keys(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'myalias');
        $source->load();
        $rows = $source->getRows();

        $this->assertArrayHasKey('myalias.id', $rows[0]);
        $this->assertArrayHasKey('myalias.name', $rows[0]);
        $this->assertArrayNotHasKey('id', $rows[0]);
        $this->assertArrayNotHasKey('name', $rows[0]);
    }
}
