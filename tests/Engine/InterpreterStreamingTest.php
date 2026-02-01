<?php

namespace Engine;

use CQL\Engine\Interpreter;
use CQL\Lexer\Tokenizer;
use CQL\Parser\Parser;
use PHPUnit\Framework\TestCase;

class InterpreterStreamingTest extends TestCase
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

    public function test_explicit_normal_mode(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT * FROM data";
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        $interpreter = new Interpreter($parser->parse(), streaming: false);

        $this->assertFalse($interpreter->isStreaming());
        
        $results = $interpreter->execute();
        $this->assertSame(2, $results->count());
    }

    public function test_explicit_streaming_mode(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT * FROM data";
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        $interpreter = new Interpreter($parser->parse(), streaming: true);

        $this->assertTrue($interpreter->isStreaming());
        
        $results = $interpreter->execute();
        $this->assertSame(2, $results->count());
    }

    public function test_automatic_mode_small_file(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice\n2,Bob");

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT * FROM data";
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        
        // Automatic mode with default 50MB threshold
        $interpreter = new Interpreter($parser->parse(), streaming: null);

        // Small file should use normal mode
        $this->assertFalse($interpreter->isStreaming());
        $this->assertSame(52428800, $interpreter->getAutoStreamingThreshold());
    }

    public function test_automatic_mode_with_custom_threshold(): void
    {
        // Create a file larger than 100 bytes
        $csv = "id,name,value\n";
        for ($i = 1; $i <= 10; $i++) {
            $csv .= "$i,User$i,Value$i\n";
        }
        file_put_contents($this->testFile, $csv);

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT * FROM data";
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        
        // Set threshold to 50 bytes (file is larger)
        $interpreter = new Interpreter($parser->parse(), streaming: null, autoStreamingThreshold: 50);

        // File is larger than threshold, should use streaming
        $this->assertTrue($interpreter->isStreaming());
        $this->assertSame(50, $interpreter->getAutoStreamingThreshold());
    }

    public function test_streaming_and_normal_produce_identical_results(): void
    {
        file_put_contents($this->testFile, "id,name,value\n1,Alice,100\n2,Bob,200\n3,Charlie,300");

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT name, value FROM data WHERE value > 150";

        // Normal mode
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        $normalResults = (new Interpreter($parser->parse(), streaming: false))->execute()->toArray();

        // Streaming mode
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        $streamResults = (new Interpreter($parser->parse(), streaming: true))->execute()->toArray();

        // Automatic mode
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        $autoResults = (new Interpreter($parser->parse(), streaming: null))->execute()->toArray();

        $this->assertSame($normalResults, $streamResults);
        $this->assertSame($streamResults, $autoResults);
    }

    public function test_streaming_with_where_clause(): void
    {
        file_put_contents($this->testFile, "id,name,age\n1,Alice,30\n2,Bob,25\n3,Charlie,35");

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT name, age FROM data WHERE age > 26";
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        $interpreter = new Interpreter($parser->parse(), streaming: true);

        $results = $interpreter->execute();
        
        $this->assertSame(2, $results->count());
        
        $array = array_values($results->toArray()); // Re-index array
        $this->assertSame('Alice', $array[0]['name']);
        $this->assertSame('Charlie', $array[1]['name']);
    }

    public function test_streaming_with_column_selection(): void
    {
        file_put_contents($this->testFile, "id,name,age,city\n1,Alice,30,NYC\n2,Bob,25,LA");

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT name, city FROM data";
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        $interpreter = new Interpreter($parser->parse(), streaming: true);

        $results = $interpreter->execute();
        $array = $results->toArray();

        $this->assertArrayHasKey('name', $array[0]);
        $this->assertArrayHasKey('city', $array[0]);
        $this->assertArrayNotHasKey('id', $array[0]);
        $this->assertArrayNotHasKey('age', $array[0]);
    }

    public function test_streaming_with_wildcard_selection(): void
    {
        file_put_contents($this->testFile, "id,name,age\n1,Alice,30\n2,Bob,25");

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT * FROM data";
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        $interpreter = new Interpreter($parser->parse(), streaming: true);

        $results = $interpreter->execute();
        $array = $results->toArray();

        $this->assertArrayHasKey('id', $array[0]);
        $this->assertArrayHasKey('name', $array[0]);
        $this->assertArrayHasKey('age', $array[0]);
    }

    public function test_default_constructor_uses_automatic_mode(): void
    {
        file_put_contents($this->testFile, "id,name\n1,Alice");

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT * FROM data";
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        
        // No streaming parameter = automatic mode
        $interpreter = new Interpreter($parser->parse());

        $this->assertIsInt($interpreter->getAutoStreamingThreshold());
        $this->assertSame(52428800, $interpreter->getAutoStreamingThreshold()); // 50MB
    }

    public function test_streaming_mode_with_mathematical_expressions(): void
    {
        file_put_contents($this->testFile, "id,price,quantity\n1,10,5\n2,20,3\n3,15,4");

        $query = "DEFINE '{$this->testFile}' AS data WITH HEADERS SELECT price, quantity FROM data WHERE price * quantity > 50";
        $tokenizer = new Tokenizer($query);
        $parser = new Parser($tokenizer->tokenize());
        $interpreter = new Interpreter($parser->parse(), streaming: true);

        $results = $interpreter->execute();
        
        $this->assertSame(2, $results->count());
    }
}
