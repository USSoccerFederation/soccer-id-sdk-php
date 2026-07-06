<?php

use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Configuration;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Session;
use USSoccerFederation\UssfAuthSdkPhp\Auth\JwksVerifier;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\MemoryStore;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\CodeException;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\FailedCodeExchangeException;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\StateException;
use USSoccerFederation\UssfAuthSdkPhp\Logging\StdoutLogger;

afterEach(function () {
    $_GET = [];
    $_SESSION = [];
    $_COOKIE = [];
});

test('can callback', function () {
    $_GET['state'] = 'unittest|state';
    $_GET['code'] = 'unittest|code';
    $nonce = 'unittest|nonce';
    $transientStore = new MemoryStore();
    $transientStore->set('state', $_GET['state']);
    $transientStore->set('code', $_GET['code']);
    $transientStore->set('nonce', $nonce);
    $transientStore->set('code_verifier', 'unittest|pkce');

    $mockHttpClient = Mockery::mock(ClientInterface::class);
    $mockHttpClient->expects('sendRequest')->andReturnUsing(function () use ($nonce) {
        $header = base64_encode('{"alg":"RS256"}');
        $sig = base64_encode('signatureGoesHere');
        $body = [
            'id_token' => "{$header}."
                . base64_encode(json_encode([
                    'iss' => 'http://127.0.0.1/',
                    'aud' => 'unittest',
                    'nonce' => 'unittest|nonce',
                    'exp' => time() + 3600
                ]))
                . ".{$sig}",
            'access_token' => "{$header}."
                . base64_encode(json_encode([
                        'iss' => 'http://127.0.0.1/',
                        'aud' => 'http://127.0.0.1/',
                        'sub' => 'unittest',
                        'iat' => time(),
                        'exp' => time() + 3600
                    ])
                ) . ".{$sig}",
            'refresh_token' => 'UnitTestRefreshToken',
            'scope' => 'openid profile email',
            'expires_in' => 3600,
        ];
        $response = new Response(body: json_encode($body));
        return $response;
    });

    $mockJwksVerifier = Mockery::mock(JwksVerifier::class)->makePartial();
    $mockJwksVerifier->allows('verifyToken')->andReturn(true);

    $logger = new StdoutLogger();
    $conf = new Auth0Configuration(
        domain: 'http://127.0.0.1/',
        clientId: 'unittest',
        clientSecret: 'secret',
        cookieSecret: 'secret',
        baseUrl: 'http://127.0.0.1:8000',
        audience: 'http://127.0.0.1/',
    );
    $ussfAuth = new Auth0Client(
        auth0Configuration: $conf,
        httpClient: $mockHttpClient,
        transientStore: $transientStore,
        statefulStore: new MemoryStore(),
        logger: $logger,
        jwksVerifier: $mockJwksVerifier
    );
    $session = $ussfAuth->callback();

    expect($session)->toBeInstanceOf(Auth0Session::class)
        ->and($session->user['sub'])->toBe('unittest');
});

test('exchange fails if given invalid state', function () {
    $transientStore = new MemoryStore();
    $transientStore->set('state', 'unittest|state');
    $transientStore->set('code', 'unittest|code');
    $transientStore->set('nonce', 'unittest|nonce');
    $transientStore->set('code_verifier', 'unittest|pkce');

    $mockHttpClient = Mockery::mock(ClientInterface::class);
    $logger = new StdoutLogger();
    $conf = new Auth0Configuration(
        domain: 'http://127.0.0.1/',
        clientId: 'unittest',
        clientSecret: 'secret',
        cookieSecret: 'secret',
        baseUrl: 'http://127.0.0.1:8000',
        audience: 'http://127.0.0.1/',
    );
    $ussfAuth = new Auth0Client(
        auth0Configuration: $conf,
        httpClient: $mockHttpClient,
        transientStore: $transientStore,
        statefulStore: new MemoryStore(),
        logger: $logger
    );
    $ussfAuth->exchange('/', 'unittest|code', 'unittest-INVALID|state');
})->throws(StateException::class);

test('exchange fails if given empty code', function () {
    $transientStore = new MemoryStore();
    $transientStore->set('state', 'unittest|state');
    $transientStore->set('code', 'unittest|code');
    $transientStore->set('nonce', 'unittest|nonce');
    $transientStore->set('code_verifier', 'unittest|pkce');

    $mockHttpClient = Mockery::mock(ClientInterface::class);
    $logger = new StdoutLogger();
    $conf = new Auth0Configuration(
        domain: 'http://127.0.0.1/',
        clientId: 'unittest',
        clientSecret: 'secret',
        cookieSecret: 'secret',
        baseUrl: 'http://127.0.0.1:8000',
        audience: 'http://127.0.0.1/',
    );
    $ussfAuth = new Auth0Client(
        auth0Configuration: $conf,
        httpClient: $mockHttpClient,
        transientStore: $transientStore,
        statefulStore: new MemoryStore(),
        logger: $logger
    );
    $ussfAuth->exchange('/', null, 'unittest|state');
})->throws(CodeException::class);

test('exchange fails if given invalid nonce', function () {
    $nonce = 'unittest|nonce';
    $transientStore = new MemoryStore();
    $transientStore->set('state', 'unittest|state');
    $transientStore->set('code', 'unittest|code');
    $transientStore->set('nonce', $nonce);
    $transientStore->set('code_verifier', 'unittest|pkce');

    $mockHttpClient = Mockery::mock(ClientInterface::class);
    $mockHttpClient->allows('sendRequest')->andReturnUsing(function () use ($nonce) {
        $header = base64_encode('{"alg":"RS256"}');
        $sig = base64_encode('signatureGoesHere');
        $body = [
            'id_token' => "{$header}."
                . base64_encode(json_encode([
                    'iss' => 'http://127.0.0.1/',
                    'aud' => 'unittest',
                    'nonce' => 'unittest-INVALID|nonce',
                    'exp' => time() + 3600
                ]))
                . ".{$sig}",
            'access_token' => "{$header}."
                . base64_encode(json_encode([
                        'iss' => 'http://127.0.0.1/',
                        'aud' => 'http://127.0.0.1/',
                        'sub' => 'unittest',
                        'iat' => time(),
                        'exp' => time() + 3600
                    ])
                ) . ".{$sig}",
            'refresh_token' => 'UnitTestRefreshToken',
            'scope' => 'openid profile email',
            'expires_in' => 3600,
        ];
        $response = new Response(body: json_encode($body));
        return $response;
    });

    $logger = new StdoutLogger();
    $conf = new Auth0Configuration(
        domain: 'http://127.0.0.1/',
        clientId: 'unittest',
        clientSecret: 'secret',
        cookieSecret: 'secret',
        baseUrl: 'http://127.0.0.1:8000',
        audience: 'http://127.0.0.1/',
    );
    $ussfAuth = new Auth0Client(
        auth0Configuration: $conf,
        httpClient: $mockHttpClient,
        transientStore: $transientStore,
        statefulStore: new MemoryStore(),
        logger: $logger
    );
    $ussfAuth->exchange('/', 'unittest|code', 'unittest|state');
})->throws(StateException::class);

test('exchange fails if PKCE is missing', function () {
    $transientStore = new MemoryStore();
    $transientStore->set('state', 'unittest|state');
    $transientStore->set('code', 'unittest|code');
    $transientStore->set('nonce', 'unittest|nonce');
    // Note: did not set code_verifier

    $mockHttpClient = Mockery::mock(ClientInterface::class);
    $logger = new StdoutLogger();
    $conf = new Auth0Configuration(
        domain: 'http://127.0.0.1/',
        clientId: 'unittest',
        clientSecret: 'secret',
        cookieSecret: 'secret',
        baseUrl: 'http://127.0.0.1:8000',
        audience: 'http://127.0.0.1/',
    );
    $ussfAuth = new Auth0Client(
        auth0Configuration: $conf,
        httpClient: $mockHttpClient,
        transientStore: $transientStore,
        statefulStore: new MemoryStore(),
        logger: $logger
    );
    $ussfAuth->exchange('/', 'unittest|code', 'unittest|state');
})->throws(FailedCodeExchangeException::class);

test('flushStores terminates the user session', function () {
    $session = Auth0Session::fromStdObject(
        (object)[
            'user' => [],
            'idToken' => 'UnitTestIdToken',
            'accessToken' => 'UnitTestAccessToken',
            'accessTokenScope' => ['openid', 'profile', 'email'],
            'accessTokenExpiration' => time() + 3600,
            'accessTokenExpired' => false,
            'refreshToken' => null,
            'backchannel' => '',
        ]
    );

    $statefulStore = new MemoryStore();
    $statefulStore->set('session', $session);

    $client = Mockery::mock(Auth0Client::class, [
        new Auth0Configuration('localhost', 'clientId', 'clientSecret', 'cookieSecret', 'http://127.0.0.1:8000'),
        Psr18ClientDiscovery::find(),
        new MemoryStore(),
        $statefulStore,
        new NullLogger(),
    ])->makePartial();

    expect($client->getSession())->toBeInstanceOf(Auth0Session::class);
    $client->flushStores();
    expect($client->getSession())->toBeNull();
});
