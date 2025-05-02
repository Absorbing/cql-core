<?php

namespace CQL\Engine\Operators\Registry;

use CQL\Engine\Operators\Contracts\OperatorInterface;
use CQL\Providers\OperatorProvider;
use InvalidArgumentException;

class OperatorRegistry
{
    /**
     * @var array<string, string>
     */
    protected static array $operators = [];

    /**
     * Initialize the operator registry with default operators.
     *
     * This method registers all operators provided by the OperatorProvider.
     *
     * @return void
     */
    public static function initialize(): void
    {
        self::bulkRegister(OperatorProvider::provide());
    }

    /**
     * Register a single operator class.
     *
     * @param string $class
     * @return void
     * @throws InvalidArgumentException
     */
    public static function register(string $class): void
    {
        if (!is_subclass_of($class, OperatorInterface::class)) {
            throw new InvalidArgumentException("Class $class must implement OperatorInterface.");
        }

        foreach ($class::symbols() as $symbol) {
            self::$operators[$symbol] = $class;
        }
    }

    /**
     * Register multiple operator classes.
     *
     * @param array<string> $classes
     * @return void
     */
    public static function bulkRegister(array $classes): void
    {
        foreach ($classes as $class) {
            self::register($class);
        }
    }

    /**
     * Get the operator class by its symbol.
     *
     * @param string $symbol
     * @return string|null
     */
    public static function getOperator(string $symbol): ?string
    {
        return self::$operators[$symbol] ?? null;
    }

    /**
     * Return the operator instance by its symbol.
     *
     * @param string $symbol
     * @return OperatorInterface
     */
    public static function resolve(string $symbol): OperatorInterface
    {
        $class = self::getOperator($symbol);

        if ($class === null) {
            throw new InvalidArgumentException("Operator $symbol not found");
        }

        assert(is_subclass_of($class, OperatorInterface::class));
        return new $class();
    }
}