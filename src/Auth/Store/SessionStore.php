<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth\Store;

/**
 * Rely on PHP sessions for a storage medium
 */
class SessionStore implements StoreInterface
{
    public function __construct(
        protected string $prefix = 'ussf_soccerid',
        protected int $cookieTtlSeconds = 3600, // 1 hour
        protected string $cookiePath = '/',
        protected string $cookieDomain = '',
        protected bool $cookieSecure = true,
        protected string $cookieSamesite = 'Lax',
    ) {
        $this->init();
    }

    public function init(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_register_shutdown();
        session_start([
            'cookie_lifetime' => $this->cookieTtlSeconds,
            'cookie_path' => $this->cookiePath,
            'cookie_domain' => $this->cookieDomain,
            'cookie_secure' => $this->cookieSecure,
            'cookie_httponly' => true,
            'cookie_samesite' => $this->cookieSamesite,
        ]);
    }

    public function get(string $key, $default = null)
    {
        return $_SESSION[$this->prefix . '_' . $key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $_SESSION[$this->prefix . '_' . $key] = $value;
    }

    public function delete(string $key): void
    {
        unset($_SESSION[$this->prefix . '_' . $key]);
    }

    public function clear(): bool
    {
        if (empty($_SESSION)) {
            return true;
        }

        $session = array_keys($_SESSION);
        foreach ($session as $key) {
            if (str_starts_with($key, $this->prefix . '_')) {
                unset($_SESSION[$key]);
            }
        }

        return true;
    }

    public function save(): bool
    {
        return true;
    }
}
