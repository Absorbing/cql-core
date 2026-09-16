<?php

namespace CQL\Exceptions;

/** Invalid bindings for a prepared query. */
class ParameterException extends CQLException
{
    public const ERROR_CODE = 'CQL_PARAMETER_ERROR';
}
