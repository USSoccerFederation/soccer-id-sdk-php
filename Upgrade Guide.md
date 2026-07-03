# Upgrade Guide

## Upgrading from 1.x to 2.x

Update:

```sh
composer update ussoccerfederation/soccer-id-sdk-php
```

### Fixes

The `IdentityClient` has been updated to be compatible with recent changes to our Identity Service. You should now
be able to read and update Profiles again.

### Configuration changes

New configuration options have been made available with version 2.0. If you would like to take advantage of these,
you may either add to your `.env` (if using `vlucas/phpdotenv`), or update the construction of `Auth0Configuration`.

These new configurations are entirely optional and safe defaults will be chosen for you if you prefer to not
make any modification.

`.env` update:

```dotenv
# (Optional) Whether to use PKCE - Proof Key for Code Exchange
# Highly recommended to leave this on at all times.
# Defaults to being turned on if not set
USSF_AUTH0_USE_PKCE=true

# (Optional) Whether to always prompt the user for consent when directing
# them to the Universal Login portal.
# Set to `true`, `yes`, or `1` to enable
# Set to `false`, `no`, `0`, or leave empty to disable
# Defaults to being turned off if not set
USSF_AUTH0_ALWAYS_PROMPT_FOR_CONSENT=

# (Optional) If behind a reverse proxy, specify them as a comma-delimited list.
# While "*" is valid to accept any proxy, you must ensure that you protect against
# header injection attacks by configuring your gateway to strip X-Forwarded-* headers
# originating from outside of your own network.
# Example: USSF_AUTH_TRUSTED_PROXIES=10.0.0.5,10.0.0.6,192.168.1.100
USSF_AUTH_TRUSTED_PROXIES=
```

`Auth0Configuration` construction:

```php
$config = new \USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Configuration(
    // ... existing properties unchanged
    usePkce: true,
    alwaysPromptForConsent: false,
    trustedProxies: []
);
```

### Breaking changes

#### `Auth0Configuration`

The `audience` property was changed from an array to string. If you were manually constructing a `Auth0Configuration`
and setting the `audience` property as an array, please update to providing only a single string.

#### `Auth0Client`

**Likelihood Of Impact: Low**

The dependency on the base SDK provided by Auth0, `auth0/auth0-php`, has been removed. As such, the `Auth0Client` will
no longer be able to accept an instance of `Auth0Interface`. If you were relying on this behaviour previously, you
will need to update the code that constructs your `Auth0Client`.

Instead, you may _optionally_ pass in a `Psr\Http\Client\ClientInterface` (to modify HTTP client behaviour),
and two separate `USSoccerFederation\UssfAuthSdkPhp\Auth\Store\StoreInterface` (to modify data storage behaviour)
instances. Generally speaking, you are unlikely to need to change these from their defaults.
