<?php


namespace USSoccerFederation\UssfAuthSdkPhp\Exceptions;

use Exception;
use Throwable;

class CodeException extends Exception
{
    public function __construct(string $message = "Invalid code", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
