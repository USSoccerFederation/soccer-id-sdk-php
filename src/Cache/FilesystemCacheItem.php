<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Cache;

use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

/**
 * A stupidly-simple filesystem cache implementation.
 * Will "just work" well enough to get you off the ground,
 * but recommended to use a "real" cache implementation instead.
 *
 * See also: Composer package symfony/cache
 */
class FilesystemCacheItem implements CacheItemInterface
{
    public function __construct(
        private string $key,
        private mixed $value = null,
        private bool $isHit = false,
        private ?int $expiresAt = null
    ) {
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
        return $this->isHit && ($this->expiresAt === null || $this->expiresAt > time());
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration?->getTimestamp();

        return $this;
    }

    public function expiresAfter($time): static
    {
        $this->expiresAt = $time === null ? null : time() + (int)$time;

        return $this;
    }

    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }
}
