<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use USSoccerFederation\UssfAuthSdkPhp\Cache\Pool\FilesystemCacheItemPool;

class JwksProvider
{
    const CACHE_PREFIX = 'ussf_soccerid_jwks';
    const CACHE_TLL_SECONDS = 3600;
    protected CacheItemPoolInterface $cacheItemPool;

    public function __construct(
        protected string $jwksUri,
        ?CacheItemPoolInterface $cacheItemPool = null,
    ) {
        $this->cacheItemPool = $cacheItemPool ?? new FilesystemCacheItemPool();
    }

    protected function getCacheKeyName(): string
    {
        return self::CACHE_PREFIX . '_' . md5($this->jwksUri);
    }

    /**
     * Returns a set of JWKS from cache.
     * If the item is not in cache, it will fetch, cache, and return it.
     *
     * @return array
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public function get(): array
    {
        $cached = $this->cacheItemPool->getItem($this->getCacheKeyName());
        if ($cached->isHit()) {
            return $cached->get();
        }

        return $this->refresh();
    }

    /**
     * Refresh local cache from the JWKS endpoint. Returns the new JWKS that was fetched.
     *
     * @return array
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public function refresh(): array
    {
        $json = $this->fetchFromConfigurationEndpoint();
        $jwks = json_decode($json, true, flags: JSON_THROW_ON_ERROR)['keys'];

        $mapped = [];
        foreach ($jwks as $key) {
            $mapped[$key['kid']] = $key;
        }

        $cached = $this->cacheItemPool->getItem($this->getCacheKeyName());
        $cached->set($mapped);
        $cached->expiresAfter(self::CACHE_TLL_SECONDS);
        $this->cacheItemPool->saveDeferred($cached);

        return $mapped;
    }

    /**
     * Read and return the JWKS from the remote server's OAuth OpenID Connect Configuration Endpoint. The contents
     * will be returned as a JSON-encoded string.
     *
     * Example:   https://auth.ussoccer.com/.well-known/jwks.json
     * Returns:
     * CodeCoverageIgnore
     * @return string
     */
    public function fetchFromConfigurationEndpoint(): string
    {
        return file_get_contents($this->jwksUri);
    }
}
