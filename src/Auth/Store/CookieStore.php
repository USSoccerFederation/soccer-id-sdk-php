<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Auth\Store;

use Random\RandomException;
use Throwable;
use USSoccerFederation\UssfAuthSdkPhp\Helpers\Http;

/**
 * Cookie storage medium. Data will be serialized into the given cookie name.
 * Optionally, you may also encrypt the cookie's data.
 */
class CookieStore implements StoreInterface
{
    const DEFAULT_COOKIE_NAME = 'ussf_soccerid';
    const COOKIE_EXPIRE_SECONDS = 600; // 10 minutes
    const KEY_DERIVATION_ALGO = 'sha256';
    const KEY_DERIVATION_LENGTH = 32;
    const KEY_DERIVATION_INFO = 'ussf_soccerid';
    const OPENSSL_ENCRYPTION_ALGO = 'aes-256-gcm';
    const OPENSSL_TAG_LEN = 16; // Standard GCM tag length


    private string $cookieName = self::DEFAULT_COOKIE_NAME;
    private ?string $cookieKey = null;
    private bool $encrypted = false;
    private array $store = [];
    private bool $dirty = false;

    public function __construct(
        ?string $cookieName = null,
        ?string $cookieSecret = null,
        private string $cookiePath = '/',
        private string $cookieDomain = '',
        private string $cookieSamesite = 'Lax',
    ) {
        $this->cookieName = $cookieName ?? self::DEFAULT_COOKIE_NAME;
        if ($cookieSecret !== null) {
            $this->setEncryptionSecret($cookieSecret);
        }

        $this->cookiePath = $cookiePath;
        $this->cookieDomain = $cookieDomain;
        $cookieSamesite = $cookieSamesite;

        $this->rehydrate();
    }

    public function setCookieName(string $cookieName): void
    {
        $this->cookieName = $cookieName;
    }

    public function setEncryptionSecret(string $secret): void
    {
        $this->cookieKey = hash_hkdf(
            algo: static::KEY_DERIVATION_ALGO,
            key: $secret,
            length: static::KEY_DERIVATION_LENGTH,
            info: static::KEY_DERIVATION_INFO
        );
        $this->encrypted = true;
    }

    /**
     * Restores the cookie store from its cookie.
     * @return void
     * @throws \Exception
     */
    public function rehydrate(): void
    {
        $contents = $_COOKIE[$this->cookieName] ?? [];
        if (empty($contents)) {
            $this->store = [];
            $this->dirty = false;
            return;
        }

        try {
            $this->deserialize($contents);
        } catch (Throwable $e) {
            $this->store = [];
            $this->dirty = false;
        }
    }

    public function get(string $key, $default = null)
    {
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

    /**
     * Actually writes the cookie out (part of client response).
     * It is recommended to try and call this only once per client-request where possible by batching together
     * several writes (`set()`) before calling `save()`.
     * @return bool
     * @throws RandomException
     * @throws \JsonException
     */
    public function save(): bool
    {
        if (!$this->dirty) {
            return true;
        }

        $contents = $this->serialize();
        setcookie($this->cookieName, $contents, [
            'path' => $this->cookiePath,
            'domain' => $this->cookieDomain,
            'samesite' => $this->cookieSamesite,
            'secure' => $this->isSecure(),
            'httponly' => true,
            'expires' => time() + static::COOKIE_EXPIRE_SECONDS,
        ]);

        $this->dirty = false;

        return true;
    }

    /**
     * Serialize the cookie store to a string. If encryption has been enabled, the resulting string will
     * have already had encryption applied.
     *
     * @return string
     * @throws \JsonException
     * @throws RandomException
     */
    public function serialize(): string
    {
        $contents = json_encode($this->store, flags: JSON_THROW_ON_ERROR);
        if ($this->encrypted) {
            $contents = $this->encrypt($contents);
        }

        return urlencode(base64_encode($contents));
    }

    /**
     * Deserializes a string (such as what would be read from the cookie) back into the store.
     * If encryption is enabled, the contents will be decrypted for you.
     *
     * @param string $contents
     * @return void
     * @throws \Exception
     */
    public function deserialize(string $contents): void
    {
        $contents = base64_decode(urldecode($contents));
        if ($this->encrypted) {
            $contents = $this->decrypt($contents);

            if ($contents === null) {
                $this->store = [];
                $this->dirty = false;
                return;
            }
        }

        $this->store = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $this->dirty = false;
    }

    /**
     * Encrypts the store and returns it as a binary string
     * @param string $contents
     * @return string
     * @throws \Random\RandomException
     */
    public function encrypt(string $contents): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(static::OPENSSL_ENCRYPTION_ALGO));
        $tag = null;
        $ciphertext = openssl_encrypt(
            $contents,
            static::OPENSSL_ENCRYPTION_ALGO,
            $this->cookieKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $iv . $tag . $ciphertext;
    }

    /**
     * Given an encrypted binary string, decrypts it, or throws an exception on failure
     *
     * @param string $contents
     * @return string
     * @throws \Exception
     */
    public function decrypt(string $contents): ?string
    {
        $ivLen = openssl_cipher_iv_length(static::OPENSSL_ENCRYPTION_ALGO);
        $iv = substr($contents, 0, $ivLen);
        $tag = substr($contents, $ivLen, static::OPENSSL_TAG_LEN);
        $ciphertext = substr($contents, $ivLen + static::OPENSSL_TAG_LEN);

        $result = openssl_decrypt(
            $ciphertext,
            static::OPENSSL_ENCRYPTION_ALGO,
            $this->cookieKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * Determines whether to use secure cookies based on whether the service is running on HTTP or HTTPS
     * @return bool
     */
    protected function isSecure(): bool
    {
        return Http::getHttpSchema() === 'https';
    }
}
