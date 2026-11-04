<?php

namespace Parser;

use CQL\CQL;
use CQL\Exceptions\CQLException;
use CQL\Exceptions\LexerException;
use CQL\Lexer\Tokenizer;
use CQL\Parser\Parser;
use PHPUnit\Framework\TestCase;

class StrictParsingTest extends TestCase
{
    public function test_lexer_reports_unmatched_bytes_and_can_be_reused(): void
    {
        $tokenizer = new Tokenizer('SELECT name FROM users');
        $this->assertEquals($tokenizer->tokenize(), $tokenizer->tokenize());
        try {
            (new Tokenizer('SELECT @name'))->tokenize();
            $this->fail('An invalid character must be rejected');
        } catch (LexerException $error) {
            $this->assertSame('CQL_LEXER_ERROR', $error->errorCode);
            $this->assertSame(7, $error->position);
        }
    }

    public function test_comments_and_quotes_do_not_hide_invalid_queries(): void
    {
        foreach (["SELECT 'unterminated", 'SELECT /* unfinished', 'SELECT name @'] as $sql) {
            try {
                (new Tokenizer($sql))->tokenize();
                $this->fail('Malformed query should fail');
            } catch (LexerException $error) {
                $this->assertNotNull($error->position);
            }
        }
        $tokens = (new Tokenizer("-- comment\nSELECT 'O''Brien', \"a\"\"b\" /* comment */"))->tokenize();
        $this->assertSame(['SELECT', "'O''Brien'", ',', '"a""b"'], array_column($tokens, 'value'));
    }

    public function test_truncated_statements_raise_domain_errors_with_offsets(): void
    {
        foreach (["DEFINE 'a.csv' AS a SELECT", "DEFINE 'a.csv' AS a SELECT * FROM a WHERE id =", "DEFINE 'a.csv' AS a SELECT * FROM a LEFT"] as $sql) {
            try {
                (new Parser((new Tokenizer($sql))->tokenize()))->parse();
                $this->fail('Truncated statement should fail');
            } catch (CQLException $error) {
                $this->assertSame(strlen($sql), $error->position);
            }
        }
    }

    public function test_typed_literals_and_escaped_quotes_execute_in_reads_and_writes(): void
    {
        $path = sys_get_temp_dir() . '/cql_quotes_' . uniqid() . "'s.csv";
        file_put_contents($path, "id,name,flag\n1,Alice,TRUE\n");
        $define = "DEFINE '" . str_replace("'", "''", $path) . "' AS u WITH HEADERS ";
        try {
            $cql = new CQL();
            $this->assertSame(1, $cql->statement($define . "UPDATE u SET name = 'O''Brien', flag = FALSE WHERE id = 1"));
            $this->assertSame([['name' => "O'Brien", 'flag' => 'FALSE']], $cql->query($define . "SELECT name, flag FROM u WHERE name = 'O''Brien' AND NOT FALSE"));
            $this->assertSame(1, $cql->statement($define . 'UPDATE u SET id = -2 WHERE TRUE'));
            $this->assertSame(1, $cql->count($define . 'SELECT * FROM u WHERE id = -2'));
            $this->assertSame(0, $cql->count($define . 'SELECT * FROM u WHERE NULL'));
        } finally {
            unlink($path);
        }
    }
}
