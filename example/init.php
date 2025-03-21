<?php

/*
 * Just a simple place to dump reused logic on example scripts.
 */

use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Configuration;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
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

function getUssfAuth(): UssfAuth
{
    static $instance = null;
    if ($instance === null) {
        /*
         * Just an example of how you would bootstrap UssfAuth.
         * In Laravel, you would place this into a Service Provider.
         * You might also put this into a composer autoload.
         */
        $instance = new UssfAuth(
            auth0: new Auth0Client(
                auth0Configuration: Auth0Configuration::fromEnv(), // Load from environment variables
                auth0: null, // Can specify our own Auth0 instance; leave `null` to create from `auth0Configuration`
                logger: new StdoutLogger(), // Can specify your own PSR/log-compatible logger, such as Monolog
            ),
            identity: new IdentityClient(new IdentityClientConfiguration()),
        );
    }

    return $instance;
}

session_start();