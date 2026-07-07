<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Cache\Pool;

namespace USSoccerFederation\UssfAuthSdkPhp\Cache\Pool;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use USSoccerFederation\UssfAuthSdkPhp\Cache\FilesystemCacheItem;

class FilesystemCacheItemPool implements CacheItemPoolInterface
{
    private string $cacheDir;

    public function __construct(
        ?string $cacheDir = null
    ) {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ussf_soccerid_cache';
        if (!is_dir($this->cacheDir)) {
            mkdir(directory: $this->cacheDir, recursive: true);
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    public function getItem(string $key): CacheItemInterface
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), associative: true);

            if (is_array($data)) { // Ensure that we decoded it properly
                if (
                    $data['expires'] === null || $data['expires'] > time() // Check if still valid (not expired)
                ) {
                    return new FilesystemCacheItem($key, $data['value'], true, $data['expires']);
                }
            }

            unlink($file); // Expired or malformed get rid of it
        }

        return new FilesystemCacheItem($key, null, false, null);
    }

    public function save(CacheItemInterface $item): bool
    {
        if ($item instanceof FilesystemCacheItem) {
            $data = [
                'value' => $item->get(),
                'expires' => $item->getExpiresAt()
            ];
        } else {
            $data = ['value' => $item->get(), 'expires' => null];
        }

        $success = file_put_contents(
            $this->getFilePath($item->getKey()),
            json_encode($data, flags: JSON_THROW_ON_ERROR)
        );

        return ($success !== false);
    }

    public function getItems(array $keys = []): iterable
    {
        return array_map([$this, 'getItem'], $keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        $filesIter = glob("$this->cacheDir/*.cache");
        if ($filesIter === false) {
            return false;
        }

        array_map('unlink', $filesIter);
        return true;
    }

    public function deleteItem(string $key): bool
    {
        return @unlink($this->getFilePath($key));
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }
}
