<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth;

use Exception;
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

    protected RequestFactoryInterface $requestFactory;
    protected StreamFactoryInterface $streamFactory;

    protected ?Auth0Session $auth0Session = null;

    public function __construct(
        protected Auth0Configuration $auth0Configuration,
        protected ?ClientInterface $httpClient = null,
        protected ?StoreInterface $transientStore = null,
        protected ?StoreInterface $statefulStore = null,
        protected ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $this->statefulStore = $statefulStore ?? new SessionStore();
        $this->logger = $logger ?? new NullLogger();
        $this->transientStore = $transientStore ?? new CookieStore(
            cookieName: 'transient_ussf_soccerid',
            cookieSecret: $this->auth0Configuration->cookieSecret
        );
    }

    /**
     * Initiate the login process by directing the user's browser to the login portal
     *
     * @return void
     * @throws \Random\RandomException
     */
    #[NoReturn]
    public function login(): void
    {
        $this->logger->debug('Starting Auth0 login', ['session_id' => session_id()]);
        $this->transientStore->clear();
        $state = $this->genNewState();
        $nonce = $this->genNewState();
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

    /**
     * Handles the "callback" part of the OAuth2 flow. This is where the user lands after having entered their
     * credentials on the IdP login portal. If the code exchange succeeds, the user can be considered authenticated.
     *
     * @return Auth0Session
     * @throws \Random\RandomException
     */
    public function callback(): Auth0Session
    {
        $state = trim($_GET['state']);
        $code = trim($_GET['code']);
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

    /**
     * Performs the code exchange between your application and the IdP, using information gathered from the
     * user's browser session, to ensure that all parties agree. If any discrepancies or errors occur along
     * the way, an Exception will be raised, otherwise the user will be authenticated and an `Auth0Session`
     * returned.
     *
     * @param string $redirectUri
     * @param string|null $code
     * @param string|null $state
     * @return Auth0Session
     * @throws CodeException
     * @throws FailedCodeExchangeException
     * @throws StateException
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
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
            } else {
                $this->statefulStore->delete('session');
            }
        }

        return $this->auth0Session;
    }

    /**
     * Shorthand method for purging the user's transient & stateful storage (ex: cookies & session). This is
     * typically used when an error or data mismatch has occurred during the OAuth2 process and the data
     * should be cleared prior to restarting the process over.
     *
     * This does *not* clear transient or stateful storage on the IdP end; it focuses purely on your application.
     * @return void
     */
    public function flushStores(): void
    {
        $this->statefulStore->clear();
        $this->transientStore->clear();
        $this->transientStore->save();
    }

    /**
     * Get the base URL (schema + domain) of your application
     * @return string
     */
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

    /**
     * Get the fully-formed callback URI (domain + configured callback route) of your application
     * @return string
     */
    protected function getCallbackRoute(): string
    {
        return (new Path($this->getBaseUrl()))->join($this->auth0Configuration->callbackRoute);
    }

    /**
     * Get the fully-formed redirect URI of your application - where the user should be sent after logging out.
     * @return string
     */
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

    /**
     * Get the IdP authorization (ex: Auth0 Universal Domain) base URL for U. S. Soccer Federation
     * @return string
     */
    protected function getAuthBaseUrl(): string
    {
        $url = $this->auth0Configuration->domain;
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * Get the fully-formed login URI. This is where the user's browser should be directed to in order to
     * initiate the login process using U. S. Soccer Federation's authorization page.
     *
     * @param string $state
     * @param string|null $codeChallenge
     * @param string|null $nonce
     * @return string
     */
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

    /**
     * Get the fully-formed logout URI. This is where the user's browser should be directed to in order
     * to log the user out on the IdP side. They should then be redirected back to the given `$returnTo`
     * address on your application.
     *
     * @param string $returnTo
     * @return string
     */
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

    /**
     * Generate and return a cryptographically-secure random string that is suitable for usage as an
     * OAuth2 `state` or `nonce`.
     *
     * @return string
     */
    protected function genNewState(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Given a valid JWT, this will extract the claims from the `payload` section and return them as
     * an array of key-value pairs.
     *
     * This does *not* handle JWT verification or validation.
     * @param string $token
     * @return array
     * @throws \JsonException
     */
    protected function extractTokenClaims(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) < 3) {
            throw new Exception('Cannot decode JWT token');
        }

        $decoded = base64_decode($parts[1]);
        return json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
    }
}
