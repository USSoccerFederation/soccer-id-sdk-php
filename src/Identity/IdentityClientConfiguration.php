<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Identity;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

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
}