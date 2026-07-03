<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Helpers;

use USSoccerFederation\UssfAuthSdkPhp\Exceptions\MalformedUrlException;

class Http
{
    /**
     * Returns the host (schema + domain) being requested.
     * If `$trustedProxies` is given, this will allow a reverse proxy
     * @param array $trustedProxies
     * @return string|null
     * @throws MalformedUrlException
     */
    public static function determineHttpHost(array $trustedProxies = []): ?string
    {
        // Allow reverse proxy, if it is trusted
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $schema = static::getHttpSchema();
        $isTrusted = in_array('*', $trustedProxies, true);
        if (!$isTrusted) {
            foreach ($trustedProxies as $proxy) {
                if (static::isIpInCidrNetwork($remoteAddr, $proxy)) {
                    $isTrusted = true;
                    break;
                }
            }
        }

        if ($isTrusted && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);

            $schema = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $schema);
            return static::autoPrefixSchema($host, $schema === 'https');
        }

        // If not behind a trusted reverse proxy, use the server name/domain being accessed
        if (!empty($_SERVER['HTTP_HOST'])) {
            return "{$schema}://" . htmlspecialchars($_SERVER['HTTP_HOST']);
        }

        if (!empty($_SERVER['SERVER_NAME'])) {
            $host = "{$schema}://" . htmlspecialchars($_SERVER['SERVER_NAME']);
            if (!empty($_SERVER['HTTP_PORT'])) {
                $host .= ':' . (int)($_SERVER['HTTP_PORT']);
            }
            return $host;
        }

        return null;
    }

    public static function isIpInCidrNetwork(string $ip, string $network): bool
    {
        if (!str_contains($network, '/')) {
            return $ip === $network;
        }

        $parts = explode('/', $network, 2);
        $subnet = $parts[0];
        $bits = $parts[1];
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int)$bits);
        $subnet &= $mask;

        return ($ip & $mask) === $subnet;
    }

    /**
     * Returns just the HTTP schema (either "http" or "https") of the incoming request
     *
     * @return string
     */
    public static function getHttpSchema(): string
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return 'https';
        }

        if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === 443) {
            return 'https';
        }

        return 'http';
    }

    /**
     * Returns whether the URL has a schema prefixed
     * @param string $url
     * @return bool
     * @throws MalformedUrlException
     */
    public static function urlHasSchema(string $url): bool
    {
        $parsed = parse_url($url, PHP_URL_SCHEME);
        if ($parsed === false) {
            throw new MalformedUrlException();
        }

        return !empty($parsed);
    }

    /**
     * Return the given URL, prefixing with the appropriate schema if needed
     * @param string $url
     * @param bool $assumeHttps
     * @return string
     * @throws MalformedUrlException
     */
    public static function autoPrefixSchema(string $url, bool $assumeHttps = false): string
    {
        if (static::urlHasSchema($url)) {
            return $url;
        }

        if ($assumeHttps) {
            return 'https://' . $url;
        }

        return static::getHttpSchema() . "://{$url}";
    }
}
