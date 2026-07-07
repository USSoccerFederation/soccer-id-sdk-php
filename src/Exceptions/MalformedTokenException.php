<?php


namespace USSoccerFederation\UssfAuthSdkPhp\Exceptions;

use Exception;
use Throwable;

class MalformedTokenException extends Exception
{
    public function __construct(
        string $message = "Malformed Token",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
