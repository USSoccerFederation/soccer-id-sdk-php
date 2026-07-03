<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth;

use Exception;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JetBrains\PhpStorm\NoReturn;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Random\RandomException;
use RuntimeException;
use Throwable;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\CookieStore;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\SessionStore;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\StoreInterface;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\CodeException;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\FailedCodeExchangeException;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\InvalidTokenClaimsException;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\MalformedUrlException;
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
            $codeVerifier = $this->genCodeVerifier();
            $codeChallenge = $this->genCodeChallenge($codeVerifier);
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
        $state = !empty($_GET['state']) ? trim($_GET['state']) : null;
        $code = !empty($_GET['code']) ? trim($_GET['code']) : null;

        try {
            $redirectUri = $this->getCallbackRoute();
            return $this->exchange($redirectUri, $code, $state);
        } catch (StateException|CodeException|FailedCodeExchangeException $e) {
            // This can happen if something is misconfigured, or if a user reloads the callback page (reusing state).
            $this->logger->warning(
                $e->getMessage(),
                [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
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
     * @throws InvalidTokenClaimsException
     * @throws MalformedUrlException
     * @throws StateException
     * @throws \JsonException
     * @throws ClientExceptionInterface
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
            'code' => $code
        ];

        // If PKCE is enabled, include it in the payload to the IdP for verification
        // against the code_challenge that was previously sent.
        if ($this->auth0Configuration->usePkce) {
            if (empty($originalCodeVerifier)) {
                throw new FailedCodeExchangeException('Missing PKCE code_verifier');
            }
            $params['code_verifier'] = $originalCodeVerifier;
        }

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

        if (!empty($nonce) && $nonce !== ($idTokenClaims['nonce'] ?? null)) {
            throw new StateException('Invalid nonce');
        }

        if (empty($accessTokenClaims)) {
            $this->flushStores();
            throw new StateException('Missing or invalid accessToken');
        }

        $this->verifyTokenClaims($accessTokenClaims);
        $this->verifyTokenClaims($idTokenClaims);

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
     * @throws MalformedUrlException
     */
    #[NoReturn]
    public function logout(?string $returnUri = null): void
    {
        if ($returnUri === null) {
            $returnUri = $this->auth0Configuration->redirectUri;
        }

        // If missing schema, it is probably a relative path; prefix with base URL
        if (!Http::urlHasSchema($returnUri)) {
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
        $this->auth0Session = null;
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

        $url = Http::determineHttpHost($this->auth0Configuration->trustedProxies);
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
        return (new Path($this->getBaseUrl()))
            ->join($this->auth0Configuration->callbackRoute)
            ->toString();
    }

    /**
     * Get the fully-formed redirect URI of your application - where the user should be sent after logging out.
     * @return string
     * @throws MalformedUrlException
     */
    protected function getLogoutRedirectUri(): string
    {
        $redirectUri = $this->auth0Configuration->redirectUri;
        if (empty($redirectUri) || $redirectUri === '/' || !Http::urlHasSchema($redirectUri)) {
            $redirectUri = (new Path($this->getBaseUrl()))
                ->join($this->auth0Configuration->redirectUri)
                ->toString();
        }

        return $redirectUri;
    }

    /**
     * Get the IdP authorization (ex: Auth0 Universal Domain) base URL for U. S. Soccer Federation
     * @return string
     * @throws MalformedUrlException
     */
    protected function getAuthBaseUrl(): string
    {
        return Http::autoPrefixSchema($this->auth0Configuration->domain, assumeHttps: true);
    }

    /**
     * Get the fully-formed login URI. This is where the user's browser should be directed to in order to
     * initiate the login process using U. S. Soccer Federation's authorization page.
     *
     * @param string $state
     * @param string|null $codeChallenge
     * @param string|null $nonce
     * @return string
     * @throws MalformedUrlException
     */
    protected function getLoginUri(
        string $state,
        ?string $codeChallenge = null,
        ?string $nonce = null,
    ): string {
        $redirectUri = $this->getCallbackRoute();

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

        if ($this->auth0Configuration->alwaysPromptForConsent) {
            $params['prompt'] = 'consent';
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
     * @throws MalformedUrlException
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
     * @throws RandomException
     */
    protected function genNewState(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate a code verifier for PKCE (Proof Key for Code Exchange).
     * This must be a highly random string, containing only "A-Za-Z\-\._~", generated at the beginning of
     * the login process, and will be verified later during the code exchange.
     *
     * The code verifier is used to produce the code challenge which is sent to the IdP immediately.
     * During the code exchange step, the code verifier is sent to the IdP to then be encoded, hashed,
     * and compared against the previously-given code challenge.
     *
     * See also: https://oauth.net/2/pkce/
     * @return string
     * @throws \Random\RandomException
     */
    protected function genCodeVerifier(): string
    {
        // Need to follow PKCE spec; strip disallowed characters
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    /**
     * Given a code verifier, produces a code challenge. This is a base-64 encoded and sha-256 hashed
     * version of the code verifier. This is the value sent to the IdP.
     *
     * @param string $codeVerifier
     * @return string
     */
    protected function genCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
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
            throw new Exception('Cannot decode JWT token: Invalid JWT structure');
        }

        $payload = strtr($parts[1], '-_', '+/');
        $remainder = strlen($payload) % 4;
        if ($remainder > 0) { // required length to be a multiple of 4
            $payload .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new Exception('Payload contains invalid Base64 characters');
        }
        return json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Given a token's claims, this verifies that the contents seem appropriate for the configuration.
     * This ensures that the token was issued by the correct IdP tenant (iss), for the correct service (audience),
     * and falls within its intended usage time (exp, nbf)
     *
     * Any indication that the token may be invalid will throw an exception.
     *
     * @param array $claims
     * @return void
     * @throws InvalidTokenClaimsException
     * @throws MalformedUrlException
     */
    protected function verifyTokenClaims(array $claims): void
    {
        // Verify issuer
        $expectedIss = rtrim($this->getAuthBaseUrl(), '/');
        if (empty($claims['iss']) || rtrim($claims['iss'], '/') !== $expectedIss) {
            $this->logger->debug(
                'Invalid issuer encountered',
                ['expected' => $expectedIss, 'received' => $claims['iss']]
            );
            throw new InvalidTokenClaimsException('Invalid issuer: ' . $claims['iss']);
        }

        // Verify audience
        $audValidated = false;
        $validAudiences = [$this->auth0Configuration->clientId, $this->auth0Configuration->audience];

        if (!is_array($claims['aud'])) {
            $claims['aud'] = [$claims['aud']];
        }

        $this->logger->debug('Checking audience validity', [
            'expected_one_of' => $validAudiences,
            'received' => $claims['aud']
        ]);
        foreach ($claims['aud'] as $aud) {
            if (in_array($aud, $validAudiences)) {
                $audValidated = true;
            }
        }

        if (!$audValidated) {
            throw new InvalidTokenClaimsException('Invalid audience');
        }

        // Verify expiry
        if (empty($claims['exp']) || $claims['exp'] < time()) {
            throw new InvalidTokenClaimsException('Token expired');
        }

        // Verify Not-Before
        if (!empty($claims['nbf']) && $claims['nbf'] > time()) {
            throw new InvalidTokenClaimsException('Token must not be accepted yet (NBF)');
        }
    }
}
