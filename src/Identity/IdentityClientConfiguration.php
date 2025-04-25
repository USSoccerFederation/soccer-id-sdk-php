<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Identity;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class IdentityClientConfiguration
{
    public function __construct(
        public $baseUrl = IdentityClient::BASE_API_URL,
        public ?ClientInterface $httpClient = null,
        public ?RequestFactoryInterface $requestFactory = null
    ) {
        if ($this->httpClient === null) {
            $this->httpClient = Psr18ClientDiscovery::find();
        }

        if ($this->requestFactory === null) {
            $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        }
    }

    public static function fromEnv(): self
    {
        return new self(
            baseUrl: $_ENV['IDENTITY_SERVICE_BASE_URL'] ?? IdentityClient::BASE_API_URL
        );
    }
}