<?php

namespace USSoccerFederation\UssfAuthSdkPhp;

class Auth0Configuration
{
    public function __construct(
        public string $domain,
        public string $clientId,
        public string $clientSecret,
        public string $cookieSecret,
        public ?string $baseUrl = null,
        public string $callbackRoute = '/auth0_callback',
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            domain: $_ENV['USSF_AUTH0_DOMAIN'],
            clientId: $_ENV['USSF_AUTH0_CLIENT_ID'],
            clientSecret: $_ENV['USSF_AUTH0_CLIENT_SECRET'],
            cookieSecret: $_ENV['USSF_AUTH0_COOKIE_SECRET'],
            baseUrl: $_ENV['USSF_AUTH0_BASE_URL'],
            callbackRoute: $_ENV['USSF_AUTH0_CALLBACK_ROUTE'],
        );
    }
}