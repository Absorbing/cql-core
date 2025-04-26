<?php

namespace CQL\Lexer\Traits;

use ReflectionClass;

trait TokenEnumTrait
{
    /**
     * Get all the backing values of the enum cases.
     *
     * @return array<int, string|int> List of enum backing values.
     */
    public static function values(): array
    {
        if (!method_exists(static::class, 'cases')) {
            throw new \LogicException(static::class . ' must be an enum implementing cases().');
        }

        return array_column(static::cases(), 'value');
    }

    /**
     * Get the name of the token group.
     *
     * @return string
     */
    public static function groupName(): string
    {
        return strtoupper((new ReflectionClass(static::class))->getShortName());
    }

    /**
     * Get the regex pattern for this token group.
     *
     * @return string
     */
    public static function pattern(): string
    {
        return '\b(' . implode('|', array_map('preg_quote', self::values())) . ')\b';
    }

    /**
     * Case-insensitive version of tryFrom()
     *
     * @param string $value
     * @return mixed
     */
    public static function tryFromInsensitive(string $value): ?self
    {
        if (!method_exists(static::class, 'tryFrom')) {
            throw new \LogicException(static::class . ' must be an enum implementing tryFrom().');
        }

        return self::tryFrom(strtoupper($value));
    }
}