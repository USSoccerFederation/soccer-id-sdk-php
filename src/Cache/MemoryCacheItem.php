<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Cache;

use Psr\Cache\CacheItemInterface;

/**
 * Intended for unit testing
 */
class MemoryCacheItem implements CacheItemInterface
{
    private $key, $value, $isHit = false;

    public function __construct($key)
    {
        $this->key = $key;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set($value): static
    {
        $this->value = $value;
        $this->isHit = true;
        return $this;
    }

    public function expiresAt($expiration): static
    {
        return $this;
    }

    public function expiresAfter($time): static
    {
        return $this;
    }
}
