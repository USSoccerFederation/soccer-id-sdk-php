<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth;

use InvalidArgumentException;

class Auth0Configuration
{
    public function __construct(
        public string $domain,
        public string $clientId,
        public string $clientSecret,
        public string $cookieSecret,
        public ?string $baseUrl = null,
        public string $callbackRoute = '/auth0_callback',
        public string $redirectUri = '/',
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            domain: $_ENV['USSF_AUTH0_DOMAIN'] ?? throw new InvalidArgumentException(
            'Missing USSF_AUTH0_DOMAIN from ENV'
        ),
            clientId: $_ENV['USSF_AUTH0_CLIENT_ID'] ?? throw new InvalidArgumentException(
            'Missing USSF_AUTH0_CLIENT_ID from ENV'
        ),
            clientSecret: $_ENV['USSF_AUTH0_CLIENT_SECRET'] ?? throw new InvalidArgumentException(
            'Missing USSF_AUTH0_CLIENT_SECRET from ENV'
        ),
            cookieSecret: $_ENV['USSF_AUTH0_COOKIE_SECRET'] ?? throw new InvalidArgumentException(
            'Missing USSF_AUTH0_COOKIE_SECRET from ENV'
        ),
            baseUrl: $_ENV['APP_URL'] ?? '',
            callbackRoute: $_ENV['USSF_AUTH0_CALLBACK_ROUTE'] ?? throw new InvalidArgumentException(
            'Missing USSF_AUTH0_CALLBACK_ROUTE from ENV'
        ),
            redirectUri: $_ENV['USSF_AUTH0_REDIRECT_URI'] ?? '/',
        );
    }
}