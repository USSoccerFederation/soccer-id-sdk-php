<?php


namespace USSoccerFederation\UssfAuthSdkPhp\Exceptions;

use Exception;
use Throwable;

class InvalidTokenClaimsException extends Exception
{
    public function __construct(
        string $message = "Token claims could not be verified",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
