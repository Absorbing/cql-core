<?php

namespace Lexer\Traits;

use LogicException;
use PHPUnit\Framework\TestCase;

class TokenEnumTraitTest extends TestCase
{
    public function test_values_throws_logic_exception_when_cases_method_missing()
    {
        $mock = new class {
            use \CQL\Lexer\Traits\TokenEnumTrait;
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/must be an enum implementing cases\(\)/');

        $mock::values();
    }

    public function test_tryFromInsensitive_throws_logic_exception_when_tryFrom_method_missing()
    {
        $mock = new class {
            use \CQL\Lexer\Traits\TokenEnumTrait;
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/must be an enum implementing tryFrom\(\)/');

        $mock::tryFromInsensitive('SELECT');
    }
}
