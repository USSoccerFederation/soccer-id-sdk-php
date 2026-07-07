<?php


namespace USSoccerFederation\UssfAuthSdkPhp\Exceptions;

use Exception;
use Throwable;

class FailedCodeExchangeException extends Exception
{
    public function __construct(
        string $message = "Code exchange failed; could not verify against remote service",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
