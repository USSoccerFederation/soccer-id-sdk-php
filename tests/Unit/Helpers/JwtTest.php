<?php


use USSoccerFederation\UssfAuthSdkPhp\Exceptions\MalformedTokenException;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Jwt;

covers(Jwt::class);

it('can parse JWT', function () {
    $token = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6IlVuaXRUZXN0In0.eyJodHRwczpcL1wvdXNzb2NjZXIuY29tXC9wcm9maWxlX2lkIjoiMmMyZmQ4NWItYjkwZi00MGU1LThkZGUtZWZlOTgxMWQ4YmViIiwiaHR0cHM6XC9cL3Vzc29jY2VyLmNvbVwvbG95YWx0eV9pZCI6ImU0YTE0MTA0LTMwMjAtNDk4OS1hN2NkLTM5MjZkZDE0ODcxZSIsImVtcGxveWVlRGlzY291bnRFbGlnaWJsZSI6dHJ1ZSwicGFydG5lckRpc2NvdW50RWxpZ2libGUiOmZhbHNlLCJ2b2x1bnRlZXJEaXNjb3VudEVsaWdpYmxlIjpmYWxzZSwiaXNzIjoiaHR0cHM6XC9cL2F1dGgtZGV2LnVzc29jY2VyLmNvbVwvIiwic3ViIjoiY29ubmVjdGlvbnxVbml0VGVzdFN1YiIsImF1ZCI6WyJodHRwczpcL1wvZGV2LWdhdGV3YXkudXNzb2NjZXIuY29tIiwiaHR0cHM6XC9cL2Rldi00MXVhN2xjdnVhMHc2d3RlLnVzLmF1dGgwLmNvbVwvdXNlcmluZm8iXSwiaWF0IjoxNzgzMzQ1ODk4LCJleHAiOjE3ODM0MzIyOTgsInNjb3BlIjoib3BlbmlkIHByb2ZpbGUgZW1haWwiLCJhenAiOiJsSGZ0Y0pxZXRDemN1YzZraERITU1XR3huZHBQRzlMMyIsInBlcm1pc3Npb25zIjpbXX0.GJf53WkFVBXMcMwAj1RoTg';
    $jwt = new Jwt();
    $result = $jwt->parse($token);

    expect($result['header']['alg'])->toBe('RS256')
        ->and($result['header']['typ'])->toBe('JWT')
        ->and($result['header']['kid'])->toBe('UnitTest')
        ->and($result['payload']['sub'])->toBe('connection|UnitTestSub');
});

it('throws if invalid JWT', function () {
    $token = 'thisismostdefinitelynotarealtoken';
    $jwt = new Jwt();
    $jwt->parse($token);
})->throws(MalformedTokenException::class);
