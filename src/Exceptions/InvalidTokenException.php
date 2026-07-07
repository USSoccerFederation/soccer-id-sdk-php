<?php


namespace USSoccerFederation\UssfAuthSdkPhp\Exceptions;

use Exception;
use Throwable;

class InvalidTokenException extends Exception
{
    public function __construct(
        string $message = "Token could not be validated",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
