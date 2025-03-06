<?php

namespace USSoccerFederation\UssfAuthSdkPhp;

use Auth0\SDK\Auth0;
use Auth0\SDK\Contract\Auth0Interface;
use JetBrains\PhpStorm\NoReturn;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class UssfAuth
{
    public function __construct(
        protected Auth0Configuration $auth0Configuration,
        protected ?Auth0Interface $auth0 = null,
        protected ?LoggerInterface $logger = null,
    ) {
        if ($this->auth0 === null) {
            $this->auth0 = new Auth0([
                'domain' => $auth0Configuration->domain,
                'clientId' => $auth0Configuration->clientId,
                'clientSecret' => $auth0Configuration->clientSecret,
                'cookieSecret' => $auth0Configuration->cookieSecret,
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

    protected function getHttpSchema(): string
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    }

    #[NoReturn]
    public function login(): void
    {
        $this->auth0->clear();
        $url = $this->auth0->login("{$this->getBaseUrl()}/{$this->auth0Configuration->callbackRoute}");
        header("Location: {$url}");
        exit();
    }

    public function logout(): void
    {
        // todo
    }

    public function callback(): Auth0Session
    {
        try {
            $this->auth0->exchange("{$this->getBaseUrl()}/{$this->auth0Configuration->callbackRoute}");
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
        return true; // todo: implement me
    }
}