<?php

/*
 * Just a simple place to dump reused logic on example scripts.
 */

use USSoccerFederation\UssfAuthSdkPhp\Auth0Configuration;
use USSoccerFederation\UssfAuthSdkPhp\Logging\StdoutLogger;
use USSoccerFederation\UssfAuthSdkPhp\UssfAuth;

require_once "../vendor/autoload.php";
$envPath = __DIR__ . '/../';
if (file_exists("{$envPath}/.env")) {
    (Dotenv\Dotenv::createImmutable($envPath))->load();
}

function getAuthInstance(): UssfAuth
{
    static $instance = null;
    if ($instance === null) {
        $instance = new UssfAuth(Auth0Configuration::fromEnv(), null, new StdoutLogger());
    }

    return $instance;
}

$auth = new UssfAuth(Auth0Configuration::fromEnv());