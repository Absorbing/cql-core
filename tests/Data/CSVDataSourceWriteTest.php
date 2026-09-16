<?php

namespace Data;

use CQL\Data\CSVDataSource;
use CQL\Data\Enums\CSVHeaderMode;
use CQL\Exceptions\DataSourceException;
use PHPUnit\Framework\TestCase;

class CSVDataSourceWriteTest extends TestCase
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

    public function test_get_headers_after_in_memory_load(): void
    {
        file_put_contents($this->testFile, "id,name,age\n1,Alice,30");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->load();

        $this->assertSame(['id', 'name', 'age'], $source->getHeaders());
    }

    public function test_append_rows(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->load();

        $written = $source->appendRows([
            ['id' => '2', 'name' => 'Bob'],
            ['id' => '3', 'name' => 'Carol'],
        ]);

        $this->assertSame(2, $written);
        $this->assertSame(
            "id,name\n1,Alice\n2,Bob\n3,Carol\n",
            file_get_contents($this->testFile)
        );
    }

    public function test_append_rows_handles_missing_trailing_newline(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice"); // no trailing newline

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->load();
        $source->appendRows([['id' => '2', 'name' => 'Bob']]);

        $rows = array_filter(explode("\n", (string)file_get_contents($this->testFile)));
        $this->assertSame(['id,name', '1,Alice', '2,Bob'], array_values($rows));
    }

    public function test_append_rows_fills_missing_columns(): void
    {
        file_put_contents($this->testFile, "id,name,age\n1,Alice,30");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->load();
        $source->appendRows([['name' => 'Bob']]);

        $this->assertStringContainsString(",Bob,", (string)file_get_contents($this->testFile));
    }

    public function test_append_rows_rejects_unknown_columns(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->load();

        $this->expectException(DataSourceException::class);
        $source->appendRows([['nonexistent' => 'x']]);
    }

    public function test_append_rows_to_empty_file_writes_header_line(): void
    {
        file_put_contents($this->testFile, '');

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->appendRows([['id' => '1', 'name' => 'Alice']]);

        $this->assertSame(
            "id,name\n1,Alice\n",
            file_get_contents($this->testFile)
        );
    }

    public function test_rewrite_from_replaces_contents_and_preserves_headers(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->load();

        $source->rewriteFrom([
            ['id' => '1', 'name' => 'Alicia'],
        ]);

        $this->assertSame(
            "id,name\n1,Alicia\n",
            file_get_contents($this->testFile)
        );
    }

    public function test_rewrite_from_accepts_generator(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->load();

        $generator = (function () {
            yield ['id' => '1', 'name' => 'Alice'];
            yield ['id' => '3', 'name' => 'Carol'];
        })();

        $source->rewriteFrom($generator);

        $this->assertSame(
            "id,name\n1,Alice\n3,Carol\n",
            file_get_contents($this->testFile)
        );
    }

    public function test_rewrite_from_leaves_no_temp_files(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITH_HEADERS, ',', 'users');
        $source->load();
        $source->rewriteFrom([['id' => '1', 'name' => 'Alice']]);

        $leftovers = glob($this->testFile . '.*.tmp') ?: [];
        $this->assertSame([], $leftovers);
    }

    public function test_rewrite_without_headers_writes_no_header_line(): void
    {
        file_put_contents($this->testFile, "1,Alice\n2,Bob");

        $source = new CSVDataSource($this->testFile, CSVHeaderMode::WITHOUT_HEADERS, ',', 'users');
        $source->load();

        $source->rewriteFrom([
            ['column_1' => '1', 'column_2' => 'Alicia'],
        ]);

        $this->assertSame(
            "1,Alicia\n",
            file_get_contents($this->testFile)
        );
    }
}
