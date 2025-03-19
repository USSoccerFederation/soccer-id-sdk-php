<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Identity;

use Http\Discovery\Psr17FactoryDiscovery;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\ApiException;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Path;

/**
 * Used to interact with USSF's Identity Service API.
 * This can be used to, for example, pull the latest profile information
 * for the authenticated user.
 */
class IdentityClient
{
    const BASE_API_URL = 'https://api.ussoccer.org/api/identity/profile'; //todo: update me
    const PROFILE_ROUTE = '/api/v1/profile';

    public function __construct(
        protected IdentityClientConfiguration $configuration,
        protected ?LoggerInterface $logger = null,
    ) {
        if ($logger === null) {
            $this->logger = new NullLogger();
        }
    }

    /**
     * Grabs the user's latest profile data from the Identity Service.
     * This should typically be called after a successful Auth0 callback.
     *
     * @param string $auth0AccessToken AT can be taken from `Auth0Session` after successful callback
     * @return object|null Data shape depends on partner configuration.
     * @throws JsonException|ClientExceptionInterface
     */
    public function getProfile(string $auth0AccessToken): ?object
    {
        $uri = (new Path($this->configuration->baseUrl))
            ->join(static::PROFILE_ROUTE)
            ->toString();

        $headers = array_merge($this->getBaseHeaders(), ['Authorization' => "Bearer {$auth0AccessToken}"]);
        $response = $this->sendRequest('GET', $uri, $headers);

        $decoded = json_decode(
            json: $response->getBody()->getContents(),
            associative: false,
            flags: JSON_THROW_ON_ERROR
        );

        return $decoded->data ?? null;
    }

    /**
     * Returns the basic headers expected to be on every request
     *
     * @return string[]
     */
    protected function getBaseHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Performs an HTTP request based on inputs and returns the response.
     * Any non-2xx responses will be logged as a warning.
     *
     * @throws ClientExceptionInterface
     */
    protected function sendRequest(string $method, string $uri, array $headers, string $body = ''): ResponseInterface
    {
        $request = $this->createRequest($method, $uri, $headers, $body);
        $response = $this->configuration->httpClient->sendRequest($request);
        $status = $response->getStatusCode();

        if ($status < 200 || $status > 299) {
            $this->logger->warning("API request returned abnormal status code {$status}", [
                'uri' => $uri,
                'method' => $method,
                'status' => $status,
                'response' => $response->getBody()->getContents(),
            ]);
        }

        return $response;
    }

    protected function createRequest(
        string $method,
        string $uri,
        array $headers = [],
        string $body = ''
    ): RequestInterface {
        static $streamFactory = null;
        if ($streamFactory === null) {
            $streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        }

        $request = $this->configuration->requestFactory->createRequest($method, $uri);

        foreach ($headers as $header => $value) {
            $request = $request->withHeader($header, $value);
        };

        if ($body !== '') {
            $request->withBody($streamFactory->createStream($body));
        }

        return $request;
    }

    /**
     * Do a PATCH update for a profile.
     * Only provided key-value pairs will be updated.
     *
     * Example:
     * ```
     * // Updates only the auth'd users favourite colour. Other profile fields will not be modified.
     * $identityClient->updateProfile([
     *       'favouriteColour' => 'blue',
     * ]);
     * ```
     *
     * @param string $auth0AccessToken
     * @param object|array $profile
     * @throws JsonException|ClientExceptionInterface
     * @throws ApiException If the update fails
     */
    public function updateProfile(string $auth0AccessToken, object|array $profile): void
    {
        $uri = (new Path($this->configuration->baseUrl))
            ->join(static::PROFILE_ROUTE)
            ->toString();

        if (is_object($profile)) {
            $profile = (array)$profile;
        }

        $body = json_encode(value: $profile, flags: JSON_THROW_ON_ERROR);
        $headers = array_merge($this->getBaseHeaders(), ['Authorization' => "Bearer {$auth0AccessToken}"]);
        $response = $this->sendRequest('PATCH', $uri, $headers, $body);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
            throw new ApiException('Failed to update profile', $response->getStatusCode());
        }
    }
}