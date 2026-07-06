<?php


use USSoccerFederation\UssfAuthSdkPhp\Cache\Pool\FilesystemCacheItemPool;

const TEST_CACHE_DIR = '/tmp/ussf_soccerid_unittest_cache/';


beforeEach(function () {
    $cache = new FilesystemCacheItemPool(TEST_CACHE_DIR);
    $cache->clear();
});

afterEach(function () {
    $cache = new FilesystemCacheItemPool(TEST_CACHE_DIR);
    $cache->clear();
});


it('can cache an item', function () {
    $cache = new FilesystemCacheItemPool(TEST_CACHE_DIR);
    $cache->clear();
    $item = $cache->getItem('hello');
    $item->set('world');
    $cache->save($item);

    $cache = new FilesystemCacheItemPool(TEST_CACHE_DIR);
    $item = $cache->getItem('hello');
    expect($item->get())->toBe('world');
});

it('respects item expiry', function () {
    $cache = new FilesystemCacheItemPool(TEST_CACHE_DIR);
    $item = $cache->getItem('hello');
    $item->set('world');
    $item->expiresAfter(1);
    $cache->save($item);

    sleep(2);

    $item = $cache->getItem('hello');
    expect($item->isHit())->toBeFalse();
});
