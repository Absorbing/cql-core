<?php

namespace CQL\Engine\Operators\Registry;

use CQL\Engine\Operators\Contracts\OperatorInterface;
use CQL\Engine\Operators\Contracts\ResolvableOperatorInterface;
use CQL\Engine\Operators\Contracts\UnaryOperatorInterface;
use CQL\Engine\Operators\Enum\OperatorType;
use CQL\Providers\OperatorProvider;
use InvalidArgumentException;

class OperatorRegistry
{
    /**
     * @var array<string, string>
     */
    protected static array $operators = [];

    /**
     * @var bool
     */
    protected static bool $booted = false;


//    /**
//     * Initialize the operator registry with default operators.
//     *
//     * This method registers all operators provided by the OperatorProvider.
//     *
//     * @return void
//     */
//    public static function initialize(): void
//    {
//        self::bulkRegister(OperatorProvider::provide());
//    }

    /**
     * Ensure operators are loaded once.
     */
    protected static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::bulkRegister(\CQL\Providers\OperatorProvider::provide());
        self::$booted = true;
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
        if (!is_subclass_of($class, ResolvableOperatorInterface::class)) {
            throw new InvalidArgumentException("Class $class must implement ResolvableOperatorInterface.");
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
        self::boot();

        return self::$operators[$symbol] ?? null;
    }

    /**
     * Return the operator instance by its symbol.
     *
     * @param string $symbol
     * @return ResolvableOperatorInterface
     */
    public static function resolve(string $symbol): ResolvableOperatorInterface
    {
        self::boot();

        $operator = self::tryResolve($symbol);

        if ($operator === false) {
            throw new InvalidArgumentException("Operator $symbol not found");
        }

        return $operator;
    }


    /**
     * Try to resolve an operator by its type safely.
     *
     * @param string $symbol
     * @return ResolvableOperatorInterface|false
     */
    public static function tryResolve(string $symbol): ResolvableOperatorInterface|false
    {
        self::boot();

        $class = self::getOperator($symbol);

        if ($class === null || !is_subclass_of($class, ResolvableOperatorInterface::class)) {
            return false;
        }

        return new $class();
    }


    /**
     * Check if an operator is registered.
     *
     * @param string $symbol
     * @return bool
     */
    public static function has(string $symbol): bool
    {
        self::boot();

        return isset(self::$operators[$symbol]);
    }

    /**
     * Get the type of an operator by its symbol.
     *
     * @param string $symbol
     * @return OperatorType
     * @throws InvalidArgumentException
     */
    public static function getType(string $symbol): OperatorType
    {
        self::boot();

        $class = self::$operators[$symbol] ?? null;

        if (is_null($class)) {
            throw new InvalidArgumentException("Operator $symbol not found");
        }

        return match (true) {
            is_subclass_of($class, UnaryOperatorInterface::class) => OperatorType::UNARY,
            is_subclass_of($class, OperatorInterface::class) => OperatorType::BINARY,
            default => throw new InvalidArgumentException("Unknown operator type for '$symbol'")
        };
    }
}