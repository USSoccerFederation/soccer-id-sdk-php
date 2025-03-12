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

    // If we pass a callback, it should automatically get and return the profile
    $mockIdentityClient = Mockery::mock(IdentityClient::class)->makePartial();
    $mockIdentityClient->expects('getProfile')->andReturn(
        (object)['testKey' => 'test-value']
    );

    // If we return an object|array, it should automatically try to update the profile
    // based on our (return) outputs.
    $mockIdentityClient->expects('updateProfile')
        ->andReturnUsing(function (string $at, object|array $profile) {
            expect($profile['updated'])->toBeTrue();
            return null;
        });


    $ussfAuth = new UssfAuth(
        auth0: $mockAuth0Client,
        identity: $mockIdentityClient
    );

    $receivedIdToken = null;
    $receivedTestToken = null;
    $ussfAuth->callback(
        function (Auth0Session $session, ?object $profile) use (&$receivedIdToken, &$receivedTestToken) {
            $receivedIdToken = $session->idToken;
            $receivedTestToken = $profile->testKey;

            return [
                'updated' => true
            ];
        }
    );

    // The callback must have been passed the Auth0Session and the profile that it fetched
    expect($receivedIdToken)->toBe('test-id-token');
    expect($receivedTestToken)->toBe('test-value');
});