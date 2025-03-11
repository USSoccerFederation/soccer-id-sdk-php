<?php

use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Session;
use USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClient;
use USSoccerFederation\UssfAuthSdkPhp\UssfAuth;

test('can complete callback and sync profile', function () {
    $mockAuth0Client = Mockery::mock(Auth0Client::class)->makePartial();
    $mockAuth0Client->allows('callback')->andReturn(
        Auth0Session::fromStdObject(
            (object)[
                'user' => [],
                'idToken' => 'test-id-token',
                'accessToken' => '',
                'accessTokenScope' => [],
                'accessTokenExpiration' => 1_000_000,
                'accessTokenExpired' => false,
                'refreshToken' => null,
                'backchannel' => '',
            ]
        )
    );

    $mockIdentityClient = Mockery::mock(IdentityClient::class)->makePartial();
    $mockIdentityClient->allows('getProfile')->andReturn(
        (object)['testKey' => 'test-value']
    );

    $ussfAuth = new UssfAuth(
        auth0: $mockAuth0Client,
        identity: $mockIdentityClient
    );

    $receivedIdToken = null;
    $receivedTestToken = null;
    $ussfAuth->callback(function (Auth0Session $session, ?object $profile) use (&$receivedIdToken, &$receivedTestToken) {
        $receivedIdToken = $session->idToken;
        $receivedTestToken = $profile->testKey;
    });

    expect($receivedIdToken)->toBe('test-id-token');
    expect($receivedTestToken)->toBe('test-value');
});