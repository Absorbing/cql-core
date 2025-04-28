<?php

namespace Lexer\Enums;

use CQL\Lexer\TokenType\Keyword;
use PHPUnit\Framework\TestCase;

class KeywordTest extends TestCase
{
    public function test_tryFromInsensitive_matches_case_insensitively()
    {
        $this->assertSame(Keyword::SELECT, Keyword::tryFromInsensitive('select'));
        $this->assertSame(Keyword::SELECT, Keyword::tryFromInsensitive('SELECT'));
        $this->assertSame(Keyword::FROM, Keyword::tryFromInsensitive('from'));
        $this->assertSame(Keyword::FROM, Keyword::tryFromInsensitive('FROM'));
    }

    public function test_tryFromInsensitive_returns_null_for_invalid_value()
    {
        $this->assertNull(Keyword::tryFromInsensitive('foobar'));
        $this->assertNull(Keyword::tryFromInsensitive(''));
    }
}