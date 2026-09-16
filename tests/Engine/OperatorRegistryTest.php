<?php

namespace Engine;

use CQL\CQL;
use CQL\Engine\Operators\NotOperator;
use CQL\Engine\Operators\OrOperator;
use CQL\Engine\Operators\Registry\OperatorRegistry;
use CQL\Providers\OperatorProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OperatorRegistryTest extends TestCase
{
    /**
     * Regression: OrOperator::symbols() returned ['NOT'], so OR was never
     * registered and its registration overwrote NotOperator's entry.
     */
    public function test_or_symbol_resolves_to_or_operator(): void
    {
        $this->assertTrue(OperatorRegistry::has('OR'));
        $this->assertInstanceOf(OrOperator::class, OperatorRegistry::resolve('OR'));
    }

    public function test_not_symbol_resolves_to_not_operator(): void
    {
        $this->assertInstanceOf(NotOperator::class, OperatorRegistry::resolve('NOT'));
    }

    /**
     * Guard against future copy-paste collisions: no two provided operator
     * classes may claim the same symbol.
     */
    public function test_provider_has_no_symbol_collisions(): void
    {
        $map = [];

        foreach (OperatorProvider::provide() as $class) {
            foreach ($class::symbols() as $symbol) {
                if (isset($map[$symbol]) && $map[$symbol] !== $class) {
                    $this->fail(
                        "Symbol '{$symbol}' is claimed by both {$map[$symbol]} and {$class}"
                    );
                }

                $map[$symbol] = $class;
            }
        }

        $this->assertNotEmpty($map);
    }

    public function test_resolve_binary_returns_binary_operator(): void
    {
        $operator = OperatorRegistry::resolveBinary('=');

        $this->assertTrue(
            is_subclass_of($operator, \CQL\Engine\Operators\Contracts\BinaryOperatorInterface::class)
                || $operator instanceof \CQL\Engine\Operators\Contracts\BinaryOperatorInterface
        );
    }

    public function test_resolve_binary_rejects_unary_operator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Operator 'NOT' is not a binary operator");

        OperatorRegistry::resolveBinary('NOT');
    }

    public function test_resolve_unary_returns_unary_operator(): void
    {
        $this->assertInstanceOf(NotOperator::class, OperatorRegistry::resolveUnary('NOT'));
    }

    public function test_resolve_unary_rejects_binary_operator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Operator '=' is not a unary operator");

        OperatorRegistry::resolveUnary('=');
    }

    /**
     * End-to-end: NOT used as a binary WHERE operator is now rejected at
     * parse time (trailing tokens after the parsed condition), instead of
     * silently evaluating the wrong operator as it did pre-0.2.0.
     */
    public function test_not_as_binary_where_operator_throws_clearly(): void
    {
        $file = sys_get_temp_dir() . '/test_' . uniqid() . '.csv';
        file_put_contents($file, "id,age\n1,30\n");

        try {
            $this->expectException(\CQL\Exceptions\SyntaxException::class);
            $this->expectExceptionMessage('after end of statement');

            (new CQL())->query("DEFINE '{$file}' AS d WITH HEADERS SELECT * FROM d WHERE age NOT 18");
        } finally {
            unlink($file);
        }
    }
}
