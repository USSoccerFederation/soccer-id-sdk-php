<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Helpers;

use USSoccerFederation\UssfAuthSdkPhp\Exceptions\MalformedUrlException;

class Http
{
    /**
     * Returns the host (schema + domain) being requested
     * @return string|null
     */
    public static function determineHttpHost(): ?string
    {
        $schema = static::getHttpSchema();
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
