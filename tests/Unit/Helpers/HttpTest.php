<?php

use USSoccerFederation\UssfAuthSdkPhp\Helpers\Http;

covers(Http::class);

it('can determine host', function () {
    $_SERVER = ['HTTP_HOST' => 'www.example.com', 'HTTPS' => 'on'];
    $result = Http::determineHttpHost();
    expect($result)->toBe('https://www.example.com');

    $_SERVER = ['SERVER_NAME' => 'www.myapp.com', 'HTTPS' => 'off'];
    $result = Http::determineHttpHost();
    expect($result)->toBe('http://www.myapp.com');
});

it('can extract schema from url', function (string $url, bool $hasSchema) {
    $result = Http::urlHasSchema($url);
    expect($result)->toBe($hasSchema);
})->with([
    ['http://127.0.0.1/', true],
    ['https://127.0.0.1/', true],
    ['myapp://callback', true],

    ['www.google.com', false],
]);

it('can auto-prefix schema using original schema', function () {
    // If request was HTTPS, should always result in an HTTPS schema
    $_SERVER = ['HTTPS' => 'on'];
    $result = Http::autoPrefixSchema('www.example.com');
    expect(str_starts_with($result, 'https://'))->toBeTrue();

    // If proxy says it is HTTPS, respect it
    $_SERVER = ['HTTP_X_FORWARDED_PROTO' => 'https'];
    $result = Http::autoPrefixSchema('www.example.com');
    expect(str_starts_with($result, 'https://'))->toBeTrue();

    // If requested by SSL port, it should be HTTPS
    $_SERVER = ['SERVER_PORT' => 443];
    $result = Http::autoPrefixSchema('www.example.com');
    expect(str_starts_with($result, 'https://'))->toBeTrue();

    // If all else fails, must be HTTP
    $_SERVER = [];
    $result = Http::autoPrefixSchema('www.example.com');
    expect(str_starts_with($result, 'http://'))->toBeTrue();
});

it('does not prefix schema if a schema is already provided', function () {
    $result = Http::autoPrefixSchema('http://127.0.0.1/');
    expect($result)->toBe('http://127.0.0.1/');

    $result = Http::autoPrefixSchema('https://127.0.0.1/');
    expect($result)->toBe('https://127.0.0.1/');

    $result = Http::autoPrefixSchema('myapp://callback');
    expect($result)->toBe('myapp://callback');
});
