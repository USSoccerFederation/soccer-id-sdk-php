<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Helpers;

use USSoccerFederation\UssfAuthSdkPhp\Exceptions\InvalidTokenException;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\MalformedTokenException;

class Jwt
{
    /**
     * @param string $jwt
     * @return array{header: array, payload: array, structure: array}
     * @throws MalformedTokenException
     */
    public static function parse(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 3) {
            throw new MalformedTokenException('Cannot decode JWT token: Invalid JWT structure');
        }

        $header = static::extractClaimsFromPart($parts[0]);
        $payload = static::extractClaimsFromPart($parts[1]);
        $signature = base64_decode(static::normalizeTokenPart($parts[2]), true); // Binary; not JSON encoded

        if ($signature === false) {
            throw new MalformedTokenException('Signature could not be decoded');
        }

        return [
            'header' => $header,
            'payload' => $payload,
            'signature' => $signature
        ];
    }

    protected static function normalizeTokenPart(string $part): string
    {
        $payload = strtr($part, '-_', '+/');
        $remainder = strlen($payload) % 4;
        if ($remainder > 0) { // required length to be a multiple of 4
            $payload .= str_repeat('=', 4 - $remainder);
        }

        return $payload;
    }

    protected static function extractClaimsFromPart(string $part): array
    {
        $normalized = static::normalizeTokenPart($part);
        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            throw new MalformedTokenException('Payload contains invalid Base64 characters');
        }

        return json_decode($decoded, true, JSON_THROW_ON_ERROR);
    }
}
