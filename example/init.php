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

/*
 * Just an example of how you would bootstrap UssfAuth.
 * In Laravel, you would place this into a Service Provider.
 * You might also put this into a composer autoload.
 */

function getUssfAuth(): UssfAuth
{
    static $instance = null;
    if ($instance === null) {
        $instance = new UssfAuth(
            auth0: new Auth0Client(Auth0Configuration::fromEnv(), null, new StdoutLogger()),
            identity: new IdentityClient(new IdentityClientConfiguration()),
        );
    }

    return $instance;
}