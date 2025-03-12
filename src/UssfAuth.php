<?php

namespace USSoccerFederation\UssfAuthSdkPhp;

use Closure;
use JetBrains\PhpStorm\NoReturn;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Session;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\ApiException;
use USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClient;

class UssfAuth
{
    public function __construct(
        protected Auth0Client $auth0,
        protected IdentityClient $identity
    ) {
    }

    #[NoReturn]
    public function login(): void
    {
        $this->auth0->login();
    }

    public function logout(?string $returnUrl): void
    {
        $this->auth0->logout($returnUrl);
    }

    public function callback(?Closure $callback = null): Auth0Session
    {
        $session = $this->auth0->callback();

        if ($callback !== null) {
            $profile = $this->identity->getProfile($session->accessToken);
            $updates = $callback($session, $profile);
        }

        if (is_array($updates) || is_object($updates)) {
            $this->identity->updateProfile($session->accessToken, $updates);
        }

        return $session;
    }

    public function identity(): IdentityClient
    {
        return $this->identity;
    }
}