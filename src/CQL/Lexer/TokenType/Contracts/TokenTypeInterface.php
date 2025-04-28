<?php

namespace CQL\Lexer\TokenType\Contracts;

interface TokenTypeInterface
{
    /**
     * Get the group name.
     *
     * @return string
     */
    public static function groupName(): string;

    /**
     * Get the pattern for the token group.
     *
     * @return string
     */
    public static function pattern(): string;
}