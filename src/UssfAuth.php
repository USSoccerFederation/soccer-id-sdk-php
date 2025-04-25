<?php

namespace USSoccerFederation\UssfAuthSdkPhp;

use Closure;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Session;
use USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClient;

class UssfAuth
{
    public function __construct(
        protected Auth0Client $auth0,
        protected ?IdentityClient $identity = null
    ) {
    }

    /**
     * Direct the user to USSF's Universal Login page
     * @return void
     */
    #[NoReturn]
    public function login(): void
    {
        $this->auth0->login();
    }

    /**
     * Direct the user to Auth0 in order to cleanly log them out on the Auth0 side.
     * This should be done _after_ flushing their session for your app.
     * @param string|null $returnUrl
     * @return void
     */
    #[NoReturn]
    public function logout(?string $returnUrl = null): void
    {
        $this->auth0->logout($returnUrl);
    }

    /**
     * Handle code exchange with Auth0. If successful, the user will be logged in.
     * You may provide a callback to aid with USSF Identity Service tasks (get/update profile).
     * Your callback should be responsible for:
     * - Updating the user record in your database, if needed
     * - Set up your app's session to ensure the user is considered "logged in" to your app
     * - Return an array|object of key-value pairs for any profile updates that are needed (ie. update profile)
     *
     * @param Closure|null $callback
     * @param array|null $profileParams
     * @return Auth0Session
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function callback(?Closure $callback = null, null|array $profileParams = null): Auth0Session
    {
        $session = $this->auth0->callback();

        if ($callback !== null) {
            $profile = $this->identity?->getProfile($session->accessToken, $profileParams);
            $updates = $callback($session, $profile);

            if ($profile !== null && (is_array($updates) || is_object($updates))) {
                $this->identity->updateProfile($session->accessToken, $updates);
            }
        }

        return $session;
    }

    /**
     * Returns the instance of the Identity Service client.
     * You may use this to get/update the user's profile manually instead of using a closure in `callback()`
     * @return ?IdentityClient
     */
    public function identity(): ?IdentityClient
    {
        return $this->identity;
    }
}