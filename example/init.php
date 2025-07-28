<?php

/*
 * Just a simple place to dump reused logic on example scripts.
 */

use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Configuration;
use USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClient;
use USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClientConfiguration;
use USSoccerFederation\UssfAuthSdkPhp\Logging\StdoutLogger;
use USSoccerFederation\UssfAuthSdkPhp\UssfAuth;

require_once "../vendor/autoload.php";
$envPath = __DIR__ . '/../';
if (file_exists("{$envPath}/.env")) {
    (Dotenv\Dotenv::createImmutable($envPath))->load();
}

/*
 * Run a demo server with:
 * ```
 * php -S 127.0.0.1:8000 -t example/
 * ```
 */

session_start([
    'cookie_lifetime' => 3600, // Cookie expires in 1 hour
    'cookie_path' => '/',
    'cookie_domain' => '', // Defaults to the current domain
    'cookie_secure' => false, // Should typically be `true`, but we'll allow http:// for testing purposes
    'cookie_httponly' => true, // Prevent JavaScript access
    'cookie_samesite' => 'Strict' // Restrict cross-site requests
]);

function getUssfAuth(): UssfAuth
{
    static $instance = null;
    if ($instance === null) {
        $logger = new StdoutLogger(); // Can also specify your own PSR/log-compatible logger, such as Monolog

        /*
         * Just an example of how you would bootstrap UssfAuth.
         * In Laravel, you would place this into a Service Provider.
         * You might also put this into a composer autoload.
         */
        $instance = new UssfAuth(
            auth0: new Auth0Client(
                auth0Configuration: Auth0Configuration::fromEnv(), // Load from environment variables
                auth0: null, // Can specify our own Auth0 instance; leave `null` to create from `auth0Configuration`
                logger: $logger,
            ),
            identity: new IdentityClient(
                configuration: IdentityClientConfiguration::fromEnv(),
                logger: $logger
            ),
        );
    }

    return $instance;
}