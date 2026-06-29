<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth;

use Auth0\SDK\Exception\StateException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JetBrains\PhpStorm\NoReturn;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;
use USSoccerFederation\UssfAuthSdkPhp\Auth\TransientStore\CookieStore;
use USSoccerFederation\UssfAuthSdkPhp\Auth\TransientStore\StoreInterface;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\CodeException;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\FailedCodeExchangeException;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Http;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Path;


/**
 * Wraps Auth0. Used to simplify authentication against USSF tenant.
 */
class Auth0Client
{
    const USSF_GATEWAY = 'https://gateway.ussoccer.com';
    const AUTHORIZE_ENDPOINT = 'authorize';
    const TOKEN_ENDPOINT = 'oauth/token';

    protected ClientInterface $httpClient;
    protected RequestFactoryInterface $requestFactory;
    protected StreamFactoryInterface $streamFactory;

    public function __construct(
        protected Auth0Configuration $auth0Configuration,
        protected ?StoreInterface $store = null,
        protected ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = Psr18ClientDiscovery::find();
        $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        /*
        if ($this->auth0 === null) {
            $httpClient = Psr18ClientDiscovery::find();
            $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
            $streamFactory = Psr17FactoryDiscovery::findStreamFactory();
            $this->auth0 = new Auth0([
                'domain' => $auth0Configuration->domain,
                'audience' => $this->auth0Configuration->audience,
                'clientId' => $auth0Configuration->clientId,
                'clientSecret' => $auth0Configuration->clientSecret,
                'cookieSecret' => $auth0Configuration->cookieSecret,
                'httpClient' => $httpClient,
                'httpRequestFactory' => $requestFactory,
                'httpStreamFactory' => $streamFactory,
                'redirectUri' => $auth0Configuration->redirectUri,
            ]);
        }*/

        $this->store = $store ?? new CookieStore();

        if ($logger === null) {
            $this->logger = new NullLogger();
        }
    }

    #[NoReturn]
    public function logout(?string $redirectUri = null): void
    {
        if ($redirectUri === null) {
            $redirectUri = $this->auth0Configuration->redirectUri;
        }

        if (!(str_starts_with($redirectUri, 'http://') || str_starts_with($redirectUri, 'https://'))) {
            $redirectUri = (new Path($this->getBaseUrl()))->join($redirectUri);
        }

        header("Location: {$this->auth0->logout($redirectUri)}");
        exit();
    }

    public function callback(): Auth0Session
    {
        $redirectUri = $this->getRedirectUri();
        $state = $_GET['state'];
        $code = $_GET['code'];
        $this->logger->debug('Starting Auth0 Callback');
        $this->logger->debug('redirect_uri: ' . ($redirectUri ?? 'NULL'));
        $this->logger->debug('state: ' . ($state ?? 'NULL'));
        $this->logger->debug('code: ' . ($code ?? 'NULL'));

        $this->exchange($redirectUri, $code, $state);

        try {
            //$this->auth0->exchange($this->getCallbackRoute());
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

    protected function getRedirectUri(): string
    {
        $redirectUri = $this->auth0Configuration->redirectUri;
        if (empty($redirectUri) || $redirectUri === '/') {
            $redirectUri = (new Path($this->getBaseUrl()))
                ->join($this->auth0Configuration->callbackRoute)
                ->toString();
        }

        return $redirectUri;
    }

    protected function getAuthBaseUrl(): string
    {
        $url = $this->auth0Configuration->domain;
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    protected function getLoginUri(string $state): string
    {
        $redirectUri = $this->getRedirectUri();

        $params = [
            'response_type' => 'code',
            'client_id' => $this->auth0Configuration->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'openid profile email',
            'state' => $state,
        ];

        $uri = (new Path($this->getAuthBaseUrl()))
                ->join(static::AUTHORIZE_ENDPOINT)
                ->toString() . '?' . http_build_query($params);

        return $uri;
    }

    protected function genNewState(): string
    {
        return uniqid(); // todo: replace me with something more secure
    }

    #[NoReturn]
    public function login(): void
    {
        //$this->auth0->clear();
        $this->logger->debug('Starting Auth0 login');

        $state = $this->genNewState();
        $this->store->clear();
        $this->storeState($state);

        $this->logger->debug('storing state: ' . $state);

        $url = $this->getLoginUri($state);
        header("Location: {$url}");
        exit();
    }

    public function exchange(
        string $redirectUri,
        ?string $code = null,
        ?string $state = null,
    ): void {
        // todo: nonce?
        $storedState = $this->getState($state);
        $this->logger->debug('storedState: ' . ($storedState ?? 'NULL'));

        if ($state === null || $storedState !== $state) {
            $this->logger->debug(
                'Invalid state encountered during code exchange: '
                . ($state === null ? 'state is `null`' : "`{$state}` !== `{$storedState}`"),
            );
            $this->clear();
            throw new StateException();
        }

        if ($code === null) {
            $this->clear();
            throw new CodeException();
        }

        // todo: PKCE code verifier

        // todo: code exchange
        $codeVerifier = null;
        $params = [
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'client_id' => $this->auth0Configuration->clientId,
            'client_secret' => $this->auth0Configuration->clientSecret,
            'code' => $code,
        ];

        $uri = (new Path($this->getAuthBaseUrl()))
            ->join(static::TOKEN_ENDPOINT)
            ->toString();

        $this->logger->debug('sending request token: ' . $uri);

        $bodyStream = $this->streamFactory->createStream(http_build_query($params));
        $request = $this->requestFactory->createRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($bodyStream);

        $response = $this->httpClient->sendRequest($request);
        $bodyContents = $response->getBody()->getContents();
        if ($response->getStatusCode() !== 200) {
            $this->logger->error(
                'Failed code exchange: HTTP ' . $response->getStatusCode(),
                [
                    'uri' => $uri,
                    'response_code' => $response->getStatusCode(),
                    'response_body' => $bodyContents,
                ]
            );

            $this->clear();
            throw new FailedCodeExchangeException();
        }

        $decodedBody = json_decode($bodyContents, false);
        if (empty($decodedBody)) {
            throw new FailedCodeExchangeException('Invalid response content received from code exchange.');
        }

        /** @var object{access_token?: string, scope?: string, refresh_token?: string, id_token?: string, expires_in?: int|string, token_type?: string} $decodedBody */

        $session = new Auth0Session();
        $session->idToken = $decodedBody->id_token;
        $session->accessToken = $decodedBody->access_token;
        $session->accessTokenScope = array_map(function ($item) {
            return trim($item);
        }, explode(' ', $decodedBody->scope));
        $session->accessTokenExpiration = $decodedBody->expires_in;
        $session->accessTokenExpired = false; // todo: How can we set this?
        $session->refreshToken = $decodedBody->refresh_token ?? null;
        $session->user = [
            // todo: comes from decoded ID token
        ];

        dd($session);
    }

    public function clear(): void
    {
        // todo
        $this->store->clear();
        $this->store->save();
    }

    protected function storeState(string $state): void
    {
        $this->store->set('state', $state);
        $this->store->save();
    }

    protected function getState(string $state): ?string
    {
        $state = $this->store->get('state', null);
        $this->store->delete('state');
        $this->store->save();

        return $state;
    }
}
