<?php

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Configuration;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Session;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\MemoryStore;
use USSoccerFederation\UssfAuthSdkPhp\Logging\StdoutLogger;

test('can callback', function () {
    $_GET['state'] = 'unittest|state';
    $_GET['code'] = 'unittest|code';
    $nonce = 'unittest|nonce';
    $transientStore = new MemoryStore();
    $transientStore->set('state', $_GET['state']);
    $transientStore->set('code', $_GET['code']);
    $transientStore->set('nonce', $nonce);

    $mockHttpClient = Mockery::mock(ClientInterface::class);
    $mockHttpClient->expects('sendRequest')->andReturnUsing(function () use ($nonce) {
        $header = base64_encode('{"alg":"RS256"}');
        $sig = base64_encode('signatureGoesHere');
        $body = [
            'id_token' => "{$header}." . base64_encode(json_encode(['nonce' => 'unittest|nonce'])) . ".{$sig}",
            'access_token' => "{$header}." . base64_encode(
                    json_encode(['sub' => 'unittest', 'iat' => time(), 'exp' => time() + 3600])
                ) . ".{$sig}",
            'refresh_token' => 'UnitTestRefreshToken',
            'scope' => 'openid profile email',
            'expires_in' => 3600,
        ];
        $response = new Response(body: json_encode($body));
        return $response;
    });

    $logger = new StdoutLogger();
    $conf = new Auth0Configuration('', '', '', '', 'http://127.0.0.1:8000');
    $ussfAuth = new Auth0Client(
        auth0Configuration: $conf,
        httpClient: $mockHttpClient,
        transientStore: $transientStore,
        statefulStore: new MemoryStore(),
        logger: $logger
    );
    $session = $ussfAuth->callback();

    expect($session)->toBeInstanceOf(Auth0Session::class)
        ->and($session->user['sub'])->toBe('unittest');
});
