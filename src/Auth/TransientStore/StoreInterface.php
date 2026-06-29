<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth\TransientStore;

interface StoreInterface
{
    public function get(string $key, $default = null);

    public function set(string $key, $value): void;

    public function delete(string $key): void;

    public function clear(): bool;

    public function save(): bool;
}
