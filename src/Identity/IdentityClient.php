<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Identity;

use USSoccerFederation\UssfAuthSdkPhp\Exceptions\ApiException;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Path;

/**
 * Used to interact with USSF's Identity Service API.
 * This can be used to, for example, pull the latest profile information
 * for the authenticated user.
 */
class IdentityClient
{
    const BASE_API_URL = 'https://api.ussoccer.org/api/identity/profile';
    const PROFILE_ROUTE = '/profile';

    public function __construct(protected IdentityClientConfiguration $configuration)
    {
    }

    /**
     * Grabs the user's latest profile data from the Identity Service.
     * This should typically be called after a successful Auth0 callback.
     *
     * @param string $auth0AccessToken AT can be taken from `Auth0Session` after successful callback
     * @return object|null Data shape depends on partner configuration.
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function getProfile(string $auth0AccessToken): ?object
    {
        return (object)[
            'hello' => 'world',
        ];
        $uri = (new Path($this->configuration->baseUrl))
            ->join(static::PROFILE_ROUTE)
            ->join($auth0AccessToken)
            ->toString();

        $request = $this->configuration->requestFactory->createRequest('GET', $uri);

        $response = $this->configuration->httpClient->sendRequest($request);
        $status = $response->getStatusCode();
        if ($status < 200 || $status > 299) {
            throw new ApiException("API request returned status code {$status}", $status);
        }

        return json_decode(
            json: $response->getBody()->getContents(),
            associative: false,
            flags: JSON_THROW_ON_ERROR
        );
    }
}