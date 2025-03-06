<?php

namespace USSoccerFederation\UssfAuthSdkPhp;

class Auth0Session
{
    public array $user;
    public string $idToken;
    public string $accessToken;
    public array $accessTokenScope;
    public int $accessTokenExpiration;
    public bool $accessTokenExpired;
    public ?string $refreshToken;
    public string $backchannel;

    public static function fromStdObject(object $object): self
    {
        $inst = new static();
        foreach (get_object_vars($object) as $key => $value) {
            $inst->{$key} = $value;
        }

        return $inst;
    }
}