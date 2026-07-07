<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth\Store;

/**
 * In-memory data store. Not applicable for usage outside unit testing.
 */
class MemoryStore implements StoreInterface
{
    protected array $store = [];

    public function __construct()
    {
    }

    public function get(string $key, $default = null)
    {
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->store[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
    }

    public function save(): bool
    {
        return true;
    }
}
