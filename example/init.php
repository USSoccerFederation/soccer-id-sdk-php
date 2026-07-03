<?php

/*
 * Just a simple place to dump reused logic on example scripts.
 */

use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Configuration;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\SessionStore;
use USSoccerFederation\UssfAuthSdkPhp\Logging\StdoutLogger;
use USSoccerFederation\UssfAuthSdkPhp\UssfAuth;

require_once "../vendor/autoload.php";

/*
 * Note that the usage of .env file is entirely optional. You may choose to manually
 * configure your auth client rather than relying on loading them using  `fromEnv()`.
 *
 * For example purposes, we will assume the usage of .env files, so you'll want to
 * have installed vlucas/phpdotenv via composer.
 */
$envPath = __DIR__ . '/../';
if (file_exists("{$envPath}/.env") && class_exists('Dotenv\Dotenv')) {
    (Dotenv\Dotenv::createImmutable($envPath))->load();
}

/*
 * Run a demo server with:
 * ```
 * php -S 127.0.0.1:8000 -t example/
 * ```
 */

$logger = new StdoutLogger(); // Can also specify your own PSR/log-compatible logger, such as Monolog


function getUssfAuth(): UssfAuth
{
    global $logger;

    static $instance = null;
    if ($instance === null) {
        $sessionStore = new SessionStore(
            cookieSecure: false, // Should typically be `true`, but we'll allow http:// for testing purposes
        );
        $logger->info('Session ID: ' . session_id());

        /*
         * Just an example of how you would bootstrap UssfAuth.
         * In Laravel, you would place this into a Service Provider.
         * You might also put this into a composer autoload.
         */
        $instance = new UssfAuth(
            auth0: new Auth0Client(
                auth0Configuration: Auth0Configuration::fromEnv(), // Load from environment variables
                statefulStore: $sessionStore,
                logger: $logger,
            ),
            identity: null, /*new IdentityClient(
                configuration: IdentityClientConfiguration::fromEnv(),
                logger: $logger
            ),*/
        );
    }

    return $instance;
}
