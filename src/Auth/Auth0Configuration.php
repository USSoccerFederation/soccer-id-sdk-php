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
        public string $audience = Auth0Client::USSF_GATEWAY,
        public string $callbackRoute = '/auth0_callback',
        public string $redirectUri = '/',
        public bool $usePkce = true,
        public bool $alwaysPromptForConsent = false,
    ) {
        $this->validate();
    }

    public function validate(): void
    {
        $requiredFields = [
            'domain',
            'clientId',
            'clientSecret',
            'cookieSecret',
            'audience',
            'callbackRoute',
            'redirectUri',
        ];

        foreach ($requiredFields as $field) {
            if (trim($this->{$field}) === '') {
                throw new InvalidArgumentException("'{$field}' is required");
            }
        }
    }

    public static function fromEnv(): self
    {
        $promptForConsent = !empty($_ENV['USSF_AUTH0_ALWAYS_PROMPT_FOR_CONSENT']) && in_array(
                strtolower(trim($_ENV['USSF_AUTH0_ALWAYS_PROMPT_FOR_CONSENT'])),
                ['true', 'yes', '1']
            );

        $usePkce = !isset($_ENV['USSF_AUTH0_USE_PKCE']) || in_array(
                strtolower(trim($_ENV['USSF_AUTH0_USE_PKCE'])),
                ['true', 'yes', '1']
            );

        return new self(
            domain: !empty($_ENV['USSF_AUTH0_DOMAIN'])
                ? $_ENV['USSF_AUTH0_DOMAIN']
                : throw new InvalidArgumentException('Missing USSF_AUTH0_DOMAIN from ENV'),

            clientId: !empty($_ENV['USSF_AUTH0_CLIENT_ID'])
                ? $_ENV['USSF_AUTH0_CLIENT_ID']
                : throw new InvalidArgumentException('Missing USSF_AUTH0_CLIENT_ID from ENV'),

            clientSecret: !empty($_ENV['USSF_AUTH0_CLIENT_SECRET'])
                ? $_ENV['USSF_AUTH0_CLIENT_SECRET']
                : throw new InvalidArgumentException('Missing USSF_AUTH0_CLIENT_SECRET from ENV'),

            cookieSecret: !empty($_ENV['USSF_AUTH0_COOKIE_SECRET'])
                ? $_ENV['USSF_AUTH0_COOKIE_SECRET']
                : $_ENV['APP_KEY']
                ?? throw new InvalidArgumentException('Missing USSF_AUTH0_COOKIE_SECRET from ENV'),

            baseUrl: $_ENV['APP_URL'] ?? '',
            audience: !empty($_ENV['USSF_AUTH0_AUDIENCE'])
                ? $_ENV['USSF_AUTH0_AUDIENCE']
                : Auth0Client::USSF_GATEWAY,

            callbackRoute: !empty($_ENV['USSF_AUTH0_CALLBACK_ROUTE'])
                ? $_ENV['USSF_AUTH0_CALLBACK_ROUTE']
                : throw new InvalidArgumentException('Missing USSF_AUTH0_CALLBACK_ROUTE from ENV'),

            redirectUri: $_ENV['USSF_AUTH0_REDIRECT_URI'] ?? '/',
            usePkce: $usePkce,
            alwaysPromptForConsent: $promptForConsent,
        );
    }
}
