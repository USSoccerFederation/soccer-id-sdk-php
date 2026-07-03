# Soccer ID - U.S. Soccer Federation Partner Authentication SDK

![Packagist License](https://img.shields.io/packagist/l/USSoccerFederation/soccer-id-sdk-php?label=License)
![Unit tests](https://img.shields.io/github/check-runs/USSoccerFederation/soccer-id-sdk-php/main?label=Tests)
![Packagist Version](https://img.shields.io/packagist/v/USSoccerFederation/soccer-id-sdk-php?label=Version)

## Requirements

- PHP 8+
- Any PSR-18 compatible HTTP client, such as Guzzle
- Client ID & Secret from U.S. Soccer
- An agreed-upon callback URL hosted by your application (for OAuth2 code exchange)

## About

Soccer ID is an initiative by the U.S. Soccer Federation to empower partner applications. This SDK simplifies the
integration of third-party applications with U.S. Soccer's identity provider (IdP), enabling seamless user
authentication via
U.S. Soccer’s user pool.

With this SDK, developers can quickly implement secure login functionality, allowing users of their applications to
authenticate using their U.S. Soccer credentials. It abstracts the complexities of identity federation, handling
authentication flows, token validation, and user session management with minimal configuration. Whether you're building
a membership portal, a fan engagement platform, or an internal team tool, this SDK streamlines the authentication
process, ensuring a secure and consistent login experience.

## How it works

Your application will complete the expected Auth0 login flow, then will interact with U.S. Soccer's Identity Service to
get or update information about the user. Afterward, you finalize the user's session, logging them into your app.

For a detailed view on all the pieces involved in this flow, see the sequence diagram below:

```mermaid
sequenceDiagram
    autonumber

    actor User
    participant Webserver as Webserver

    User ->> Webserver: Visit login page, choose U.S. Soccer Auth
    create participant Auth0
    Webserver ->> Auth0: Forward user to Universal Login Portal
    activate Auth0
    User <<-->> Auth0: Credential exchange
    Auth0 -->> User: Auth success, set cookie
    Auth0 ->> Webserver: Redirect user back to callback endpoint
    deactivate Auth0

    activate Webserver
    destroy Auth0
    Webserver <<-->> Auth0: Handshake, code exchange
    
    create participant IdService as U.S. Soccer Identity Service
    Webserver -->> IdService: Get profile
    Note over Webserver: Create or update user in database
    destroy IdService
    Webserver -->> IdService: POST amendments to profile

    Note over Webserver: Any additional login steps
    Webserver ->> User: Set cookies, render app dashboard
    deactivate Webserver
```

1. On your application's login page, provide the user with the option to "Login with U.S. Soccer."
2. Direct the user to U.S. Soccer's Universal Login page.
3. The user will then be prompted to enter their credentials (email and password) if they are not already logged into
   U.S. Soccer on the IdP end
4. The U.S. Soccer login portal will record some temporary data about the user's attempted login, and then...
5. Redirect the user to the configured "callback URL" for your application with some additional information used for
   verification in the next step.
6. Your application will need to perform a "code exchange" with the IdP. On success, the user can be considered
   authenticated
7. (Optional) Send a GET request to U.S. Soccer's Identity Service to get the user's profile. This contains additional
   information about the user. Use info from the login session + profile to upsert the user into your app's database.
8. You _may_ provide updates/changes to the user's profile if needed.
9. Do any additional steps needed to log the user into your application and set their cookie(s).

## Quick setup

Install the SDK:

```sh
composer require ussoccerfederation/soccer-id-sdk-php
```

Install a PSR-18 HTTP client _if you don't already have one_:

```sh
composer require guzzlehttp/guzzle guzzlehttp/psr7 http-interop/http-factory-guzzle
```

Configure your environment variables, or use `.env`. See `.env.example` for a good starting point.

```dotenv
# Get these from U.S. Soccer:
USSF_AUTH0_CLIENT_ID=example-client-id-from-ussoccer
USSF_AUTH0_CLIENT_SECRET=example-client-secret-from-ussoccer
USSF_AUTH0_DOMAIN=auth-dev.ussoccer.com

# Create your own cookie secret; do not just copy the example below. This is used to encrypt the auth0 cookie.
# This can be generated using `openssl rand -hex 32` from your shell.
USSF_AUTH0_COOKIE_SECRET=dd60d4b06b73480172f08741cb00f0c3b70d559965669a808edb1b89c0d30dd5

# Specify the schema (HTTP/HTTPS) and domain for your app.
# If blank, UssfAuth will make its best guess.
# Example: http://my-app.fakeorganization.org
APP_URL=

# This should be the route to the "callback" endpoint.
# This is where users will be directed to in your app in order to complete
# the Auth0 exchange.
USSF_AUTH0_CALLBACK_ROUTE=ussf_callback.php
```

If you are using Laravel, please jump forward to [Laravel Integration](#Laravel-Integration)

If you'd like to use `.env` files with your application and have not already included `phpdotenv`, do so now:

```shell
composer require vlucas/phpdotenv
```

Next you will set up the object(s) needed to handle authentication. If you only need to log the user in and do not
need to fetch/update user profiles, see [Manually handling auth](#manually-handling-auth). Otherwise, continue below.

In your application, create an instance of the `UssfAuth` client. For example:

```php
<?php

require 'vendor/autoload.php';

// Load .env - not needed if using real environment variables
(Dotenv\Dotenv::createImmutable(__DIR__))->load();

$logger = new StdoutLogger(); // Can also point to your PSR/log instance (ex: Monolog)
$ussfAuth = new UssfAuth(
   auth0: new Auth0Client(
       auth0Configuration: Auth0Configuration::fromEnv(), // Load from environment variables
       logger: new StdoutLogger(), // Can specify your own PSR/log-compatible logger, such as Monolog
   ),
   identity: new IdentityClient(new IdentityClientConfiguration()), // can be `null` if you don't need profiles
);
```

By default, the auth client will assume using PHP sessions for stateful data (data about the user if they are logged in)
and encrypted cookies for transient data (temporary data needed only during the login process). To customize this,
see [Manually handling auth](#manually-handling-auth).

You will use the instance of `UssfAuth` on a few different pages: when the user chooses to log in via U.S. Soccer,
during the callback phase of authentication, and when logging out. You may want to bind it to a singleton or use a
factory to make it available to these pages.

Next, we need an action to associate with the user choosing to log in with U.S. Soccer. You may have a button or link
that binds to `/ussf_login.php`, for example. We'll need to use our `UssfAuth` instance to initiate the login attempt:

```php
$ussfAuth->login();
```

That is enough to send the user over to Auth0 to prompt for permission and credentials. Next, we need a landing page
that Auth0 will redirect them to in order to perform a code exchange. Let's call it `/ussf_callback.php`. It will also
need access to the `UssfAuth` instance.

```php
$session = $ussfAuth->callback(function (Auth0Session $session, ?object $profile) {
    // $session will contain information related to Auth0, such as name, email, access token, etc.
    // $profile will contain additional information about the user. Data shape is configurable.

    // This is where you can sync the user's info with your database.
    
    // Remember to set any cookies or do any other session management here
    
    // You can also update the user's profile by returning an array of key-value pairs to change:
    // return [
    //   'name' => 'John Doe',
    // ];
});
```

Finally, we need to allow the user to log out. Modify your logout script to perform a logout action against the
`UssfAuth` instance if the user is logged in via this method. This may look something like:

```php
$ussfAuth->logout('/index.php'); // Redirect them back to index.php after logout
```

With everything in place, you should now be able to start your app and complete the full login/logout cycle using U.S.
Soccer Auth.

## Manually handling auth

This section covers using the auth client directly rather than going through the `UssfAuth` class. With this, you
will be able to handle log in, log out, and sessions however you would like.

```php
use \USSoccerFederation\UssfAuthSdkPhp\Auth\Store\CookieStore;
use \USSoccerFederation\UssfAuthSdkPhp\Auth\Store\SessionStore;
use \USSoccerFederation\UssfAuthSdkPhp\Logging\StdoutLogger;
use \USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
// ...

$logger = new StdoutLogger(); // Feel free to substitute your own PSR/log logger instance instead

// All session properties are optional; safe defaults will be chosen for you
// This will hold information about the user when they are logged in
$statefulStore = new SessionStore(
    prefix: 'my_app_auth', // Session fields used by Soccer ID are prefixed with this
    cookieTtlSeconds: 600, // 10 minutes - or whatever you want
    cookiePath: '/',
    cookieDomain: '',
    cookieSecure: true, // HTTPS only
    cookieSamesite: 'Strict', // Or use 'Lax' if you prefer
);

// This holds temporary data during the login process
$transientStore = new CookieStore(
    cookieName: 'my_app_cookie',
    cookieSecret: $_ENV['APP_COOKIE_SECRET'], // Used to encrypt/decrypt the cookie. Keep this secured
);

$authClient = new Auth0Client(
    auth0Configuration: Auth0Configuration::fromEnv(), // Load from environment variables
    statefulStore: $statefulStore,
    transientStore: $transientStore,
    logger: $logger,
);
```

Initiate login:

```php
$authClient->login(); // Will redirect browser
```

After providing credentials, the user will be redirected back to your configured callback endpoint. You will be
expected to then handle the OAuth callback:

```php
$authClient->callback(); // Will automatically handle code-exchange for you and set up the user's session
```

Example of working with user session:

```php
$session = $authClient->getSession();
$loggedIn = ($session !== null);

if( $loggedIn ) {
    $userId = $esssion->user['sub'];
    $userEmail = $session->user['email'] ?? null; // Should work if the `email` claim was requested
    $accessToken = $session->accessToken;
    $idToken = $session->idToken;
}
```

Finally, you can log the user out. You have two options:

1. Log the user out by clearing their session locally. If the user tries to log back in soon, they will not be
   re-prompted for their credentials.
2. Log the user out by directing them to the IdP logout endpoint. This will log them out on the remote end as well,
   requiring them to re-enter their credentials upon logging in again.

```php
// Option 1: Clear all data on the user (stateful & transient). The user will, effectively, be considered
// logged out within your application. You may continue to run additional code after calling this.
$authClient->flushStores();

// Option 2: Log the user out locally & on the IdP end. This redirects the user, so you cannot run additional
// code after this. After the session has been terminated on the IdP end, the user is redirected back to the
// given route, or to the configured logout URI otherwise.
$authClient->logout('/index.php');
```

## Manually handling identities (Profiles)

```php
use \USSoccerFederation\UssfAuthSdkPhp\Logging\StdoutLogger;
use \USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClient;
use \USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClientConfiguration;

$identityClient = new IdentityClient(
    configuration: IdentityClientConfiguration::fromEnv(), // Or manually configure if you'd like
    logger: new StdoutLogger()
);

// The below code assumes the user had already logged into your application and that you have their access_token
$profile = $identityClient->getProfile($accessToken);
$fullName = $profile?->first_name . ' ' . $profile?->last_name;


$identityClient->updateProfile($accessToken, [
    'example_property' => 'abc123'
]);
```

## Laravel Integration

Laravel allows you to access environment variables via the `env()` helper, however this is only considered valid while
within the context of config files. Instead of accessing the environment variables directly when instantiating the
`UssfAuth` instance, we'll need to create a config file. Create a new file: `config/soccerid.php`

```php
<?php

return [
    'auth0' => [
        /* Get these from U.S. Soccer */
        'client_id' => env('USSF_AUTH0_CLIENT_ID'),
        'client_secret' => env('USSF_AUTH0_CLIENT_SECRET'),
        'domain' => env('USSF_AUTH0_DOMAIN'),

        /* Use a long, random secret for cookie encryption */
        'cookie_secret' => env('USSF_AUTH0_COOKIE_SECRET'),

        /* The route that the user will be directed back to handle Auth0 code exchange and complete login */
        'callback_route' => env('USSF_AUTH0_CALLBACK_ROUTE', '/ussf_callback.php'),
    ],
];
```

Next, we'll create a service provider to bind our `UssfAuth` instance. Create a new file:
`Providers/SoccerIdServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Configuration;
use USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClient;
use USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClientConfiguration;
use USSoccerFederation\UssfAuthSdkPhp\UssfAuth;

class SoccerIdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->bind(Auth0Configuration::class, function () {
            return new Auth0Configuration(
                domain: config('soccerid.auth0.domain'),
                clientId: config('soccerid.auth0.client_id'),
                clientSecret: config('soccerid.auth0.client_secret'),
                cookieSecret: config('soccerid.auth0.cookie_secret'),
                callbackRoute: config('soccerid.auth0.callback_route'),
            );
        });

        $this->app->bind(
            UssfAuth::class,
            function (Application $app) {
                $configuration = $app->make(Auth0Configuration::class);
                $logger = $app->make(LoggerInterface::class);

                return new UssfAuth(
                    auth0: new Auth0Client(
                        auth0Configuration: $configuration,
                        logger: $logger,
                    ),
                    identity: new IdentityClient(new IdentityClientConfiguration()),
                );
            }
        );
    }
}
```

Remember to add the provider to boostrapping. For example, in Laravel 12, this is done by adding it to
`bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\SoccerIdServiceProvider::class, // Add this
];
```

Finally, we need to hook up routing and serving. In the example below, we will do this the easy way. Add login, logout,
and callback routes into `/routes/web.php`:

```php
Route::get('/login_ussf', function (UssfAuth $ussfAuth) {
    $ussfAuth->login();
});

Route::get('/logout_ussf', function (UssfAuth $ussfAuth) {
    session()->invalidate(); // Or whatever you need to log out a user from your app
    $ussfAuth->logout('/');
});

Route::get(config('soccerid.auth0.callback_route'), function (UssfAuth $ussfAuth) {
    $ussfAuth->callback(function (Auth0Session $session, ?object $profile) {
        session()->put('logged_in', true); // Example only; do whatever you need to actually log the user into your app

        // Update your database with information from the Auth0 session and/or U.S. Soccer profile
        // Example:
        // ```php
        // $userRepository->update(['name' => $session->user['name'], 'email' => $session->user['email']]);
        // ```
    });

    redirect('/dashboard'); // Redirect user into the app. Don't keep them on callback!
});
```

This is enough to test out functionality. Start your app and visit `/login_ussf` to give it a try. Once you're ready,
move the core logic into a [Controller](https://laravel.com/docs/12.x/controllers#main-content) and configure your final
[Routing](https://laravel.com/docs/12.x/routing#main-content).
