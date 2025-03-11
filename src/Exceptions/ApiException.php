<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Exceptions;

use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    public function __construct(string $message, int $code, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}