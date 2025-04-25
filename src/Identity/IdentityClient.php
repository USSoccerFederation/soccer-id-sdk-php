<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Identity;

use Exception;
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
    const BASE_API_URL = 'https://gateway.ussoccer.com/ids';
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
    public function getProfile(string $auth0AccessToken, null|array $params = null): ?object
    {
        $query = $this->buildQueryString($params);
        $uri = (new Path($this->configuration->baseUrl))
            ->join(static::PROFILE_ROUTE . $query)
            ->toString();

        $headers = array_merge($this->getBaseHeaders(), ['Authorization' => "Bearer {$auth0AccessToken}"]);
        $response = $this->sendRequest('GET', $uri, $headers);

        try {
            $decoded = json_decode(
                json: $response->getBody()->getContents(),
                associative: false,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (Exception $e) {
            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        }

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
            $request = $request->withBody($streamFactory->createStream($body));
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

    /**
     * Helps construct the GET parameters.
     * Cannot simply use `http_build_query()` as it'll format repeated fields as arrays.
     * For example, we need: fields=a&fields=b&fields=c
     * http_build_query would return: fields[]=a&fields=b&fields[]=c
     *
     * @param array|null $params
     * @return string
     */
    protected function buildQueryString(null|array $params): string
    {
        // We can't use `http_build_query` here as we key by `fields` but don't use an array
        if (empty($params)) {
            return '';
        }

        $params = array_map(function ($item) {
            if (!is_string($item)) {
                throw new \DomainException("params must only contain strings.");
            }

            return 'fields=' . urlencode($item);
        }, $params);

        return '?' . implode('&', $params);
    }
}