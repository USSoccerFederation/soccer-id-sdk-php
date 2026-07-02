<?php

use USSoccerFederation\UssfAuthSdkPhp\Auth\Store\CookieStore;

covers(CookieStore::class);

it('can serialize and deserialize', function () {
    $store = new CookieStore('unittest', 'secret');
    $store->set('hello', 'world');

    $serialized = $store->serialize();
    $store->deserialize($serialized);

    expect($serialized)->toBeString()
        ->and($store->get('hello'))->toBe('world');
});

it('does not encrypt when encryption is disabled', function () {
    $store = Mockery::mock(CookieStore::class)->makePartial();
    $store->shouldNotReceive('encrypt');
    $store->shouldNotReceive('decrypt');
    $store->set('hello', 'world');

    $serialized = $store->serialize();
    $store->deserialize($serialized);

    expect($serialized)->toBeString()
        ->and($store->get('hello'))->toBe('world');
});

it('can encrypt and decrypt serialization', function () {
    $store = Mockery::mock(CookieStore::class)->makePartial();
    $store->expects('encrypt')->once()->passthru();
    $store->expects('decrypt')->once()->passthru();

    $store->setEncryptionSecret('secret');
    $store->set('hello', 'world');

    $serialized = $store->serialize(); // Performs encryption when a secret is given
    $store->deserialize($serialized); // Decrypts before deserializing

    expect($serialized)->toBeString()
        ->and($store->get('hello'))->toBe('world');
});

it('performs encryption when saving', function () {
    $store = Mockery::mock(CookieStore::class)->makePartial();
    $store->setEncryptionSecret('secret');
    $store->set('hello', 'world');
    $store->expects('encrypt')->once();

    $store->serialize();
});

it('performs decryption when rehydrating', function () {
    $store = Mockery::mock(CookieStore::class)->makePartial();
    $cookieName = 'unittest';
    $store->setCookieName($cookieName);
    $store->setEncryptionSecret('secret');

    $store->set('hello', 'world');
    $serialized = $store->serialize();
    $_COOKIE[$cookieName] = $serialized;
    $store->expects('decrypt')->once()->andReturn(serialize(['hello' => 'world']));

    $store->rehydrate();
});
