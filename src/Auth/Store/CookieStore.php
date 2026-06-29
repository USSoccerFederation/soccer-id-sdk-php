<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth\Store;

class CookieStore implements StoreInterface
{
    const DEFAULT_COOKIE_NAME = 'ussf_soccerid';
    const COOKIE_EXPIRE_SECONDS = 600; // 10 minutes

    private string $cookieName;
    private bool $encrypted = true;
    private array $store = [];
    private bool $dirty = false;

    public function __construct(
        ?string $cookieName = null,
    ) {
        $this->cookieName = $cookieName ?? self::DEFAULT_COOKIE_NAME;
        $this->rehydrate();
    }

    public function rehydrate(): void
    {
        $contents = $_COOKIE[$this->cookieName] ?? [];
        if (empty($contents)) {
            $this->store = [];
            $this->dirty = false;
            return;
        }

        $this->store = json_decode($contents, true);
        $this->dirty = false;
    }

    public function get(string $key, $default = null)
    {
        // todo: secure me; just for testing
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->store[$key] = $value;
        $this->dirty = true;
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
        $this->dirty = true;
    }

    public function clear(): bool
    {
        $this->store = [];
        $this->dirty = true;
        return true;
    }

    protected function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
            ($_SERVER['SERVER_PORT'] == 443);
    }

    public function save(): bool
    {
        if (!$this->dirty) {
            return true;
        }

        // todo: secure me; just for testing
        $contents = json_encode($this->store, true, JSON_THROW_ON_ERROR);
        setcookie(
            name: $this->cookieName,
            value: $contents,
            expires_or_options: time() + static::COOKIE_EXPIRE_SECONDS,
            secure: $this->isSecure(),
            httponly: true,
        );

        $this->dirty = false;

        return true;
    }
}
