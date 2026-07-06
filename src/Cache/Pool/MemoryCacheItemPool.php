<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Cache\Pool;

namespace USSoccerFederation\UssfAuthSdkPhp\Cache\Pool;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use USSoccerFederation\UssfAuthSdkPhp\Cache\MemoryCacheItem;

class MemoryCacheItemPool implements CacheItemPoolInterface
{
    private $items = [];

    public function getItem($key): CacheItemInterface
    {
        return $this->items[$key] ?? new MemoryCacheItem($key);
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->items[$item->getKey()] = $item;
        return true;
    }

    public function getItems(array $keys = []): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getItem($key);
        }

        return $result;
    }

    public function hasItem($key): bool
    {
        return isset($this->items[$key]);
    }

    public function clear(): bool
    {
        $this->items = [];
        return true;
    }

    public function deleteItem($key): bool
    {
        unset($this->items[$key]);
        return true;
    }

    public function deleteItems(array $keys): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return true;
    }
}
