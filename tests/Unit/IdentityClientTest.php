<?php

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClient;
use USSoccerFederation\UssfAuthSdkPhp\Identity\IdentityClientConfiguration;

test('can get profile', function () {
    $mockHttpClient = Mockery::mock(ClientInterface::class)->makePartial();
    $mockHttpClient->expects('sendRequest')->andReturnUsing(function () {
        $response = Mockery::mock(ResponseInterface::class);

        $response->allows('getBody')
            ->andReturnUsing(function () {
                $stream = Mockery::mock(StreamInterface::class);
                $stream->shouldReceive('getContents')
                    ->andReturn('{}');

                return $stream;
            });
        $response->allows('getStatusCode')->andReturn(200);

        return $response;
    });

    $client = new IdentityClient(new IdentityClientConfiguration(httpClient: $mockHttpClient));
    $profile = $client->getProfile('');
    expect($profile)->toBeObject();
});