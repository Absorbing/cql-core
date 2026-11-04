<?php

namespace CQL\Exceptions;

use RuntimeException;
use Throwable;

/** A domain error with a stable identifier and optional source location. */
class CQLException extends RuntimeException
{
    public const ERROR_CODE = 'CQL_ERROR';
    public readonly string $errorCode;

    /**
     * @param string $message Human-readable explanation.
     * @param int $code Legacy numeric exception code.
     * @param Throwable|null $previous Original exception.
     * @param int|null $position Zero-based byte offset in the query.
     * @param array<string, mixed> $context Source alias, path or CSV row.
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        public ?int $position = null,
        public array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = static::ERROR_CODE;
    }

    /**
     * @param array<string, mixed> $context Additional diagnostic context.
     * @return static
     */
    public function withContext(array $context): static
    {
        $this->context += $context;
        return $this;
    }
}
