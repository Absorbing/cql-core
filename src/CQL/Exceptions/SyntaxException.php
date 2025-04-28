<?php

namespace CQL\Exceptions;

use RuntimeException;

/**
 * Class SyntaxException
 *
 * This class represents an exception that occurs during the lexing process.
 */
class SyntaxException extends RuntimeException
{
    /**
     * SyntaxException constructor.
     *
     * @param string $message The error message.
     * @param int $code The error code (optional).
     * @param \Throwable|null $previous The previous exception (optional).
     */
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * String representation of the exception.
     *
     * @return string The error message.
     */
    public function __toString(): string
    {
        return sprintf(
            "SyntaxException: [%d]: %s\n",
            $this->code,
            $this->message
        );
    }

}