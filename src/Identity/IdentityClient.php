<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Identity;

use USSoccerFederation\UssfAuthSdkPhp\Exceptions\ApiException;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Path\Path;

class IdentityClient
{
    const BASE_API_URL = 'https://api.ussoccer.org/api/identity/profile';
    const PROFILE_ROUTE = '/profile';

    public function __construct(protected IdentityClientConfiguration $configuration)
    {
    }

    public function getProfile(string $auth0AccessToken): ?object
    {
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