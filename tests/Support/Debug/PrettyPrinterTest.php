<?php

namespace Support\Debug;

use CQL\Lexer\Tokenizer;
use CQL\Parser\Parser;
use CQL\Support\Debug\PrettyPrinter;
use PHPUnit\Framework\TestCase;

class PrettyPrinterTest extends TestCase
{
    public function test_prints_all_definitions_and_typed_select_columns(): void
    {
        $sql = "DEFINE 'users.csv' AS u WITH HEADERS DEFINE 'sales.csv' AS s "
            . 'SELECT *, u.*, u.name AS label, COUNT(*) AS total, YEAR(:date) AS cohort FROM u';
        $query = (new Parser((new Tokenizer($sql))->tokenize()))->parse();
        $output = PrettyPrinter::print($query);

        $this->assertStringContainsString("DEFINE: 'users.csv' AS u WITH HEADERS", $output);
        $this->assertStringContainsString("DEFINE: 'sales.csv' AS s WITHOUT HEADERS", $output);
        $this->assertStringContainsString(
            'SELECT: *, u.*, u.name AS label, COUNT(*) AS total, YEAR(:date) AS cohort',
            $output
        );
        $this->assertStringContainsString('FROM: u', $output);
    }

    public function test_prints_typed_operands_and_arithmetic_in_conditions(): void
    {
        $sql = "DEFINE 'users.csv' AS u SELECT name FROM u "
            . "WHERE u.name = 'O''Brien' AND u.score + 2 >= :min AND u.active = TRUE AND u.deleted = NULL";
        $query = (new Parser((new Tokenizer($sql))->tokenize()))->parse();
        $output = PrettyPrinter::print($query);

        $this->assertStringContainsString("(u.name = 'O''Brien')", $output);
        $this->assertStringContainsString('((u.score + 2) >= :min)', $output);
        $this->assertStringContainsString('(u.active = TRUE)', $output);
        $this->assertStringContainsString('(u.deleted = NULL)', $output);
    }
}
