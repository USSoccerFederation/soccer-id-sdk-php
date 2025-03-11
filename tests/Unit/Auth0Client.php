<?php

use Auth0\SDK\Contract\Auth0Interface;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Configuration;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Session;
use USSoccerFederation\UssfAuthSdkPhp\Auth\Auth0Client;
use USSoccerFederation\UssfAuthSdkPhp\Logging\StdoutLogger;

test('can callback', function () {
    $auth0Mock = Mockery::mock(Auth0Interface::class)->makePartial();
    $auth0Mock->expects('exchange');
    $auth0Mock->expects('getCredentials')->andReturnUsing(function () {
        return (object)[
            'user' => [
                'employeeDiscountEligible' => true,
                'https://ussoccer.com/loyalty_id' => '00000000-0000-0000-0000-000000000000',
                'nickname' => 'ExampleUser',
                'name' => 'John Doe',
                'updated_at' => '2025-01-01T12:00:00.000Z',
                'email' => 'j.doe@noreply.com',
                'email_verified' => true,
                'iss' => 'https://dev-41ua7lcvua0w6wte.us.auth0.com/',
                'aud' => 'SoMeExAmPleAuTh0ClIeNtIdGoEsHeRe',
                'sub' => 'google-oauth2|000000000000000000000',
                'iat' => 1741190400,
                'exp' => 1741276800,
                'sid' => 'L5JvRrYBdSkJxej7uiYSwdcTSVdfVCPT',
                'nonce' => '8d01100905332501ac735e74e1cb7842',
            ],
            'idToken' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6InVlb2FZZEtnMTN2b0ZCTWdZMmtRYyJ9.eyJlbXBsb3llZURpc2NvdW50RWxpZ2libGUiOnRydWUsImh0dHBzOi8vdXNzb2NjZXIuY29tL2xveWFsdHlfaWQiOiIwMDAwMDAwMC0wMDAwLTAwMDAtMDAwMC0wMDAwMDAwMDAwMDAiLCJuaWNrbmFtZSI6IkV4YW1wbGVVc2VyIiwibmFtZSI6IkpvaG5Eb2UiLCJ1cGRhdGVkX2F0IjoiMjAyNS0wMS0wMVQxMjowMDowMC4wMDBaIiwiZW1haWwiOiJqLmRvZUBub3JlcGx5LmNvbSIsImVtYWlsX3ZlcmlmaWVkIjp0cnVlLCJpc3MiOiJodHRwczovL2Rldi00MXVhN2xjdnVhMHc2d3RlLnVzLmF1dGgwLmNvbS8iLCJhdWQiOiJTb01lRXhBbVBsZUF1VGgwQ2xJZU50SWRHb0VzSGVSZSIsInN1YiI6Imdvb2dsZS1vYXV0aDJ8MDAwMDAwMDAwMDAwMDAwMDAwMDAwIiwiaWF0IjoxNzQxMTkwNDAwLCJleHAiOjE3NDEyNzY4MDAsInNpZCI6Ikw1SnZScllCZFNrSnhlajd1aVlTd2RjVFNWZGZWQ1BUIiwibm9uY2UiOiI4ZDAxMTAwOTA1MzMyNTAxYWM3MzVlNzRlMWNiNzg0MiJ9',
            'accessToken' => 'eyJhbGciOiJkaXIiLCJlbmMiOiJBMjU2R0NNIiwiaXNzIjoiaHR0cHM6Ly9kZXYtNDF1YTdsY3Z1YTB3Nnd0ZS51cy5hdXRoMC5jb20vIn0',
            'accessTokenScope' => [
                'openid',
                'profile',
                'email',
            ],
            'accessTokenExpired' => false,
            'refreshToken' => null,
            'backchannel' => '5499ef1c1c33126ff80d8b82dcf6fc39705c5c9e35b4c2d11717bf2f70f79539'
        ];
    });
    $auth0Mock->allows('clear');

    $logger = new StdoutLogger();
    $conf = new Auth0Configuration('', '', '', '', 'http://127.0.0.1:8000');
    $ussfAuth = new Auth0Client($conf, $auth0Mock, $logger);
    $session = $ussfAuth->callback();

    expect($session)->toBeInstanceOf(Auth0Session::class);
});
