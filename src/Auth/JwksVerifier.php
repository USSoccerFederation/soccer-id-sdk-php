<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth;

use OpenSSLAsymmetricKey;
use USSoccerFederation\UssfAuthSdkPhp\Exceptions\InvalidTokenException;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Jwt;

class JwksVerifier
{
    // RSA
    const ALGO_RS256 = 'RS256';
    const ALGO_RS384 = 'RS384';
    const ALGO_RS512 = 'RS512';

    // HMAC
    const ALGO_HS256 = 'HS256';
    const ALGO_HS384 = 'HS384';
    const ALGO_HS512 = 'HS512';

    public function __construct(
        protected JwksProvider $jwksProvider,
        protected ?string $clientSecret,
    ) {
    }

    public function verifyToken(
        string $token
    ): void {
        $parts = Jwt::parse($token);
        $signature = $parts['signature'] ?? null;
        $kid = $parts['header']['kid'] ?? null;
        $key = null;
        if ($kid !== null) {
            $key = $this->getKey($kid);
        }


        // Note: Auth0 only seems to properly support RS256 and HS256
        // HS256 JWTs are signed with client secret
        switch ($parts['header']['alg']) {
            case static::ALGO_RS256:
                $this->verifyRsa($token, $signature, $key, OPENSSL_ALGO_SHA256);
                break;

            case static::ALGO_RS384:
                $this->verifyRsa($token, $signature, $key, OPENSSL_ALGO_SHA384);
                break;

            case static::ALGO_RS512:
                $this->verifyRsa($token, $signature, $key, OPENSSL_ALGO_SHA512);
                break;

            case static::ALGO_HS256:
                $this->verifyHmac($token, $signature, 'sha256');
                break;

            case static::ALGO_HS384:
                $this->verifyHmac($token, $signature, 'sha384');
                break;

            case static::ALGO_HS512:
                $this->verifyHmac($token, $signature, 'sha512');
                break;

            default:
                throw new InvalidTokenException('Unsupported token algorithm');
        }
    }

    protected function getKey(string $kid): OpenSSLAsymmetricKey
    {
        $jwks = $this->jwksProvider->get();
        if (empty($jwks[$kid])) {
            throw new InvalidTokenException('Missing KID from JWKS');
        }

        $chunkedX5c = chunk_split($jwks[$kid]['x5c'][0], 64);
        $key = openssl_pkey_get_public(
            "-----BEGIN CERTIFICATE-----\n{$chunkedX5c}-----END CERTIFICATE-----"
        );

        if ($key === false) {
            throw new InvalidTokenException('Unable to get referenced public key');
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new InvalidTokenException('Unable to get referenced public key details');
        }

        if ($details['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new InvalidTokenException('Not an RSA key');
        }

        return $key;
    }

    protected function verifyRsa(
        string $token,
        string $signature,
        ?OpenSSLAsymmetricKey $key,
        int $openSslAlgorithm,
    ): void {
        $payload = substr($token, 0, strrpos($token, '.'));
        $result = openssl_verify($payload, $signature, $key, $openSslAlgorithm);
        if ($result !== 1) {
            throw new InvalidTokenException('Token verification failed');
        }
    }

    protected function verifyHmac(string $token, string $signature, string $algo): void
    {
        $payload = substr($token, 0, strrpos($token, '.'));
        $hash = hash_hmac($algo, $payload, $this->clientSecret, true);
        $valid = hash_equals($hash, $signature);

        if (!$valid) {
            throw new InvalidTokenException('HMAC verification failed');
        }
    }
}
