<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth;

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
use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\CookieStore;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\SessionStore;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\StoreInterface;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\CodeException;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\FailedCodeExchangeException;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\StateException;
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

    protected ?Auth0Session $auth0Session = null;

    public function __construct(
        protected Auth0Configuration $auth0Configuration,
        protected ?StoreInterface $transientStore = null,
        protected ?StoreInterface $statefulStore = null,
        protected ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = Psr18ClientDiscovery::find();
        $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $this->transientStore = $transientStore ?? new CookieStore('transient_ussf_soccerid');
        $this->statefulStore = $statefulStore ?? new SessionStore();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Log out of the application both locally and on the IdP (will require credentials to log back in)
     *
     * @param string|null $returnUri
     * @return void
     */
    #[NoReturn]
    public function logout(?string $returnUri = null): void
    {
        if ($returnUri === null) {
            $returnUri = $this->auth0Configuration->redirectUri;
        }

        if (!(str_starts_with($returnUri, 'http://') || str_starts_with($returnUri, 'https://'))) {
            $returnUri = (new Path($this->getBaseUrl()))->join($returnUri)->toString();
        }

        $this->flushStores();
        $location = $this->getLogoutUri($returnUri);
        header("Location: {$location}");
        exit();
    }

    /**
     * Return the current session, if logged in, otherwise returns null
     *
     * @return Auth0Session|null
     */
    public function getSession(): ?Auth0Session
    {
        if ($this->auth0Session !== null) {
            return $this->auth0Session;
        }

        $storedSession = $this->statefulStore->get('session');
        if ($storedSession !== null) {
            $session = Auth0Session::fromStdObject($storedSession);
            $session->accessTokenExpired = $session->accessTokenExpiration < time();

            if (!$session->accessTokenExpired) {
                $this->auth0Session = $session;
            }
        }

        return $this->auth0Session;
    }

    public function callback(): Auth0Session
    {
        $state = $_GET['state'];
        $code = $_GET['code'];
        $this->logger->debug('Starting Auth0 Callback', [
            'session_id' => session_id(),
            'state' => $state,
            'code' => $code,
        ]);


        try {
            $redirectUri = $this->getRedirectUri();
            return $this->exchange($redirectUri, $code, $state);
        } catch (StateException|CodeException|FailedCodeExchangeException $e) {
            // This can happen if something is misconfigured, or if a user reloads the callback page (reusing state).
            $this->logger->warning(
                $e->getMessage(),
                [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $code,
                    'state' => $state,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
            $this->flushStores();
            $this->login();
        } catch (Throwable $e) {
            $this->logger->error($e);
            $this->login();
        }
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

    protected function getLoginUri(
        string $state,
        ?string $codeChallenge = null,
        ?string $nonce = null,
    ): string {
        $redirectUri = $this->getRedirectUri();

        $params = [
            'response_mode' => 'query',
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'redirect_uri' => $redirectUri,
            'client_id' => $this->auth0Configuration->clientId,
            'audience' => $this->auth0Configuration->audience,
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        }

        $uri = (new Path($this->getAuthBaseUrl()))
                ->join(static::AUTHORIZE_ENDPOINT)
                ->toString() . '?' . http_build_query($params);

        return $uri;
    }

    protected function getLogoutUri(string $returnTo): string
    {
        $path = (new Path($this->getAuthBaseUrl()))
            ->join('v2/logout')
            ->toString();

        $params = [
            'returnTo' => $returnTo,
            'client_id' => $this->auth0Configuration->clientId,
        ];

        return $path . '?' . http_build_query($params);
    }

    protected function genNewState(): string
    {
        static $inc = 1;
        $fingerprint = gethostname() . microtime(false);
        return hash('sha1', $fingerprint . ($inc++) . uniqid(), false);
    }

    #[NoReturn]
    public function login(): void
    {
        $this->logger->debug('Starting Auth0 login', ['session_id' => session_id()]);
        $this->transientStore->clear();
        $state = $this->genNewState();
        $nonce = hash('sha1', uniqid(more_entropy: true));
        $this->transientStore->set('nonce', $nonce);

        $codeChallenge = null;
        if ($this->auth0Configuration->usePkce) {
            // Need to follow PKCE spec; strip disallowed characters
            $codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
            $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            $this->transientStore->set('code_verifier', $codeVerifier);
        }

        // Store local data and direct the user to authorization endpoint
        $this->transientStore->set('state', $state);
        $this->transientStore->save();
        $url = $this->getLoginUri($state, $codeChallenge, $nonce);
        header("Location: {$url}");
        exit();
    }

    public function exchange(
        string $redirectUri,
        ?string $code = null,
        ?string $state = null
    ): Auth0Session {
        $storedState = $this->transientStore->get('state');
        $this->transientStore->delete('state'); // `state` needs to be one-time-use

        if ($state === null || $storedState !== $state) {
            $this->flushStores();
            throw new StateException();
        }

        if ($code === null) {
            $this->flushStores();
            throw new CodeException('Missing code');
        }

        // Handle PKCE code verification
        $originalCodeVerifier = $this->transientStore->get('code_verifier');

        // Perform code exchange to finalize the login process, and to get access & ID tokens
        $params = [
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'client_id' => $this->auth0Configuration->clientId,
            'client_secret' => $this->auth0Configuration->clientSecret,
            'code' => $code,
            'code_verifier' => $originalCodeVerifier,
        ];

        $uri = (new Path($this->getAuthBaseUrl()))
            ->join(static::TOKEN_ENDPOINT)
            ->toString();

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

            $this->flushStores();
            throw new FailedCodeExchangeException();
        }

        $decodedBody = json_decode($bodyContents, false);
        if (empty($decodedBody)) {
            $this->flushStores();
            throw new FailedCodeExchangeException('Invalid response content received from code exchange.');
        }

        $nonce = $this->transientStore->get('nonce');
        $this->transientStore->delete('nonce'); // `nonce` can only be used once
        if (!empty($decodedBody->id_token) && $nonce === null) {
            $this->flushStores();
            throw new StateException('Missing nonce');
        }

        /** @var object{access_token?: string, scope?: string, refresh_token?: string, id_token?: string, expires_in?: int|string, token_type?: string} $decodedBody */
        $accessTokenClaims = $this->extractTokenClaims($decodedBody->access_token);
        $idTokenClaims = empty($decodedBody->id_token) ? [] : $this->extractTokenClaims($decodedBody->id_token);

        if (empty($accessTokenClaims)) {
            $this->flushStores();
            throw new StateException('Missing or invalid accessToken');
        }

        $backchannel = hash(
            'sha256',
            implode('|', [
                    $accessTokenClaims['sub'] ?? '',
                    $accessTokenClaims['iss'] ?? '',
                    $idTokenClaims['sid'] ?? ''
                ]
            )
        );

        $session = new Auth0Session();
        $session->idToken = $decodedBody->id_token;
        $session->accessToken = $decodedBody->access_token;
        $session->accessTokenScope = array_map(function ($item) {
            return trim($item);
        }, explode(' ', $decodedBody->scope));
        $session->accessTokenExpiration = time() + (int)$decodedBody->expires_in;
        $session->accessTokenExpired = false; // todo: How can we set this?
        $session->refreshToken = $decodedBody->refresh_token ?? null;
        $session->backchannel = $backchannel;
        $session->user = array_merge($accessTokenClaims, $idTokenClaims);

        $this->transientStore->clear();
        $this->statefulStore->set('session', $session);

        return $session;
    }

    protected function extractTokenClaims(string $token): array
    {
        $parts = explode('.', $token);
        $decoded = base64_decode($parts[1]);
        return json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
    }

    public function flushStores(): void
    {
        $this->statefulStore->clear();
        $this->transientStore->clear();
        $this->transientStore->save();
    }
}
