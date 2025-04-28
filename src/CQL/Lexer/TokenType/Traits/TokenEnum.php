<?php

namespace CQL\Lexer\TokenType\Traits;

use ReflectionClass;

trait TokenEnum
{
    /**
     * Get all the backing values of the enum cases.
     *
     * @return array<int, string|int> List of enum backing values.
     */
    public static function values(): array
    {
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
        return '\b(' . implode('|',
                array_map(
                    static fn($value): string => preg_quote((string)$value, '/'),
                    self::values()
                )
            ) . ')\b';
    }

    /**
     * Case-insensitive version of tryFrom()
     *
     * @param string $value
     * @return ?self
     */
    public static function tryFromInsensitive(string $value): ?self
    {
        return self::tryFrom(strtoupper($value));
    }
}