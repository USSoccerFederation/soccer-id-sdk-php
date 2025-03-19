<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth;

use Auth0\SDK\Auth0;
use Auth0\SDK\Contract\Auth0Interface;
use Auth0\SDK\Exception\StateException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JetBrains\PhpStorm\NoReturn;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Http;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Path;


/**
 * Wraps Auth0. Used to simplify authentication against USSF tenant.
 */
class Auth0Client
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

    #[NoReturn]
    public function logout(string $returnUrl = '/'): void
    {
        header("Location: {$this->auth0->logout($returnUrl)}");
        exit();
    }

    public function callback(): Auth0Session
    {
        try {
            $this->auth0->exchange($this->getCallbackRoute());
        } catch (StateException) {
            // This can happen if something is misconfigured, or if a user reloads the callback page (reusing state).
            $this->logger->warning(
                'Invalid state encountered during code exchange with Auth0.',
                ['code' => $_GET['code'], 'state' => $_GET['state'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']
            );
            $this->login();
        } catch (Throwable $e) {
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

    protected function getCallbackRoute(): string
    {
        return (new Path($this->getBaseUrl()))->join($this->auth0Configuration->callbackRoute);
    }

    protected function getBaseUrl(): string
    {
        if (!empty($this->auth0Configuration->baseUrl)) {
            return $this->auth0Configuration->baseUrl;
        }

        $url = Http::determineHttpHost();
        if ($url !== null) {
            return $url;
        }

        throw new RuntimeException('Unable to determine base URL.');
    }

    #[NoReturn]
    public function login(): void
    {
        $this->auth0->clear();
        $url = $this->auth0->login($this->getCallbackRoute());
        header("Location: {$url}");
        exit();
    }
}