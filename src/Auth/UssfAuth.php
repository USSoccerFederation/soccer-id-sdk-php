<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth;

use Auth0\SDK\Auth0;
use Auth0\SDK\Contract\Auth0Interface;
use Closure;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JetBrains\PhpStorm\NoReturn;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Path\Path;


class UssfAuth
{
    public function __construct(
        protected Auth0Configuration $auth0Configuration,
        protected ?Auth0Interface $auth0 = null,
        protected ?LoggerInterface $logger = null,
    ) {
        if ($this->auth0 === null) {
            $httpClient = Psr18ClientDiscovery::find();
            $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
            $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

            $this->auth0 = new Auth0([
                'domain' => $auth0Configuration->domain,
                'clientId' => $auth0Configuration->clientId,
                'clientSecret' => $auth0Configuration->clientSecret,
                'cookieSecret' => $auth0Configuration->cookieSecret,
                'httpClient' => $httpClient,
                'httpRequestFactory' => $requestFactory,
                'httpStreamFactory' => $streamFactory,
            ]);
        }

        if ($logger === null) {
            $this->logger = new NullLogger();
        }
    }

    protected function getBaseUrl(): string
    {
        if (!empty($this->auth0Configuration->baseUrl)) {
            return $this->auth0Configuration->baseUrl;
        }

        return $this->getHttpSchema() . '://' . $_SERVER['HTTP_HOST'];
    }

    protected function getCallbackRoute(): string
    {
        return (new Path($this->getBaseUrl()))->join($this->auth0Configuration->callbackRoute);
    }

    protected function getHttpSchema(): string
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    }

    #[NoReturn]
    public function login(): void
    {
        $this->auth0->clear();
        $url = $this->auth0->login($this->getCallbackRoute());
        header("Location: {$url}");
        exit();
    }

    public function logout(?string $returnUrl): void
    {
        if ($returnUrl === null) {
            $returnUrl = $this->getCallbackRoute();
        }

        header("Location: " . $this->auth0->logout($returnUrl));
    }

    public function callback(): Auth0Session
    {
        try {
            $this->auth0->exchange($this->getCallbackRoute());
        } catch (\Throwable $e) {
            $this->logger->error($e);
            $this->login();
        }

        $creds = $this->auth0->getCredentials();
        if (empty($creds)) {
            $this->logger->warning("Invalid Auth0 credentials after successful exchange; resetting.");
            $this->login();
        }

        return Auth0Session::fromStdObject($creds);
    }

    protected function needsSync(object $session): bool
    {
        // todo: implement me
        // Check last synced timestamp
        return true;
    }

    public function syncProfile(Auth0Session $session, Closure $closure): void
    {
        if (!($this->needsSync($session))) {
            return;
        }
    }
}