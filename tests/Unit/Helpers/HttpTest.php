<?php

use USSoccerFederation\UssfAuthSdkPhp\Helpers\Http;

covers(Http::class);

afterEach(function () {
    $_SESSION = [];
});

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

it('can determine if IP is in CIRD network', function (string $ip, string $network, bool $expectedResult) {
    expect(Http::isIpInCidrNetwork($ip, $network))->toBe($expectedResult);
})->with([
    // Exact IP must match
    ['192.168.1.1', '192.168.1.1/32', true],
    ['192.168.1.2', '192.168.1.1/32', false],
    ['1.2.3.4', '192.168.1.1/32', false],

    // First 3 octets must match
    ['192.168.1.50', '192.168.1.1/24', true],
    ['192.168.2.1', '192.168.1.1/24', false],

    // First 2 octets must match
    ['192.168.128.255', '192.168.1.1/16', true],
    ['192.169.1.1', '192.168.1.1/16', false],

    // First octet must match
    ['192.255.255.255', '192.168.1.1/8', true],
    ['193.1.1.1', '192.168.1.1/8', false],

    // block of 32 IPs - 10.1.5.0 - 10.1.5.31
    ['10.1.5.0', '10.1.5.5/27', true],
    ['10.1.5.16', '10.1.5.5/27', true],
    ['10.1.5.31', '10.1.5.5/27', true],
    ['10.1.5.32', '10.1.5.5/27', false],
]);

it('can trust proxies', function (array $trustedProxies, bool $expectedResult) {
    $_SERVER['HTTP_X_FORWARDED_HOST'] = 'www.myapp.com';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    $_SERVER['REMOTE_ADDR'] = '10.1.5.16';
    $_SERVER['HTTP_HOST'] = '10.1.5.16'; // Shouldn't be hit unless we don't trust the proxy

    expect(Http::determineHttpHost($trustedProxies) === 'https://www.myapp.com')->toBe($expectedResult);
})->with([
    [['*'], true], // Always trusted
    [['10.1.5.16'], true], // Exact match
    [['10.1.5.0/24'], true], // Within CIDR block
    [['10.1.5.0', '10.1.5.8', '10.1.5.16', '10.1.5.32'], true], // Matches one of the set proxies

    [[], false], // Never trusted
    [['10.1.5.0'], false], // Does not match
    [['10.1.20.0/24'], false], // Outside CIDR block
    [['10.1.5.3', '10.1.5.5', '10.1.5.15', '10.1.5.24'], false], // Not in list
]);
