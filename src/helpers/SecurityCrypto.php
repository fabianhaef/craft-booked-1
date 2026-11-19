<?php

namespace anvildev\booked\helpers;

use Craft;
use craft\helpers\App;
use Illuminate\Support\Facades\Crypt;

/**
 * Craft 6's yii2-adapter still lets Craft::$app->getSecurity()->encryptByKey()/
 * decryptByKey() run without an explicit key, but then it falls back to the
 * `securityKey` general config setting — deprecated as of Craft 6.0.0 in favor
 * of APP_KEY, and empty by default on any install that hasn't manually set it
 * (the expected state for a fresh Craft 6 app, since the whole point of the
 * deprecation is to stop people configuring it). hash_hkdf() rejects an empty
 * key outright, so every one of Booked's own encryptByKey()/decryptByKey()
 * calls without an explicit key crashes on a default Craft 6 install, and
 * every raw read of $securityKey for HMAC signing/salting silently signs with
 * an empty, guessable key instead.
 *
 * On Craft 6, this bridges straight to Laravel's own Crypt facade and APP_KEY —
 * the adapter's own deprecation notice on encryptByKey() points here. On
 * Craft 5, it keeps using the real, correctly-configured Security service and
 * securityKey setting, unchanged.
 */
final class SecurityCrypto
{
    /** Encrypts a value for storage. Always returns a string safe to persist as-is. */
    public static function encrypt(string $data): string
    {
        if (class_exists(Crypt::class)) {
            return Crypt::encrypt($data);
        }

        return base64_encode(Craft::$app->getSecurity()->encryptByKey($data));
    }

    /** Decrypts a value produced by {@see encrypt()}. */
    public static function decrypt(string $data): string
    {
        if (class_exists(Crypt::class)) {
            return Crypt::decrypt($data);
        }

        $decoded = base64_decode($data, true);

        return Craft::$app->getSecurity()->decryptByKey($decoded !== false ? $decoded : $data);
    }

    /** A non-empty application secret, for HMAC signing/salting - not for encrypt/decrypt. */
    public static function key(): string
    {
        if (class_exists(Crypt::class)) {
            return (string) config('app.key');
        }

        return (string) App::parseEnv(Craft::$app->getConfig()->getGeneral()->securityKey);
    }
}
