<?php

namespace CQL\Exceptions;

/** Errors raised by the syntax component. */
class SyntaxException extends CQLException
{
    public const ERROR_CODE = 'CQL_SYNTAX_ERROR';
}
