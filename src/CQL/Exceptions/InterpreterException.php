<?php

namespace CQL\Exceptions;

/** Errors raised by the interpreter component. */
class InterpreterException extends CQLException
{
    public const ERROR_CODE = 'CQL_EXECUTION_ERROR';
}
