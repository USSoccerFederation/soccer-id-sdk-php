<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Helpers;

class Http
{
    public static function determineHttpHost(): ?string
    {
        $schema = static::getHttpSchema();

        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $items = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
            return htmlspecialchars(trim(end($items)));
        }

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

    protected static function getHttpSchema(): string
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    }
}