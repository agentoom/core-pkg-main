<?php

namespace Agentoom\Core\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Configuration extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Sensitive keys that should be encrypted.
     */
    protected static array $sensitiveKeys = [
        'google_seo_integration',
        'google_ads_integration',
        'meta_ads_integration',
        'tiktok_ads_integration',
        'hubspot_integration',
        'gmail_integration',
        'cloudflare_integration',
        'firecrawl_api_key',
        'brightdata_api_key',
        'brightdata_scraping_password',
        'decodo_api_key',
        'scrapingbee_api_key',
        'privacy_external_service_token',
        // Holds the WhatsApp permanent access token, verify token and app secret.
        'instant_messaging_whatsapp',
    ];

    /**
     * Get a config value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $config = static::where('key', $key)->first();

        if (! $config || $config->value === null) {
            return $default;
        }

        $value = $config->value;

        // Decrypt if it's a sensitive key
        if (in_array($key, static::$sensitiveKeys) && $value !== null) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException $e) {
                // If decryption fails, it might be unencrypted legacy data
            }
        }

        // Check if the value is a string and potentially JSON
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            // If decoding succeeded and the result isn't null (or the original was 'null')
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Set a config value by key.
     */
    public static function setValue(string $key, mixed $value): void
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        // Encrypt if it's a sensitive key
        if (in_array($key, static::$sensitiveKeys) && $value !== null) {
            $value = Crypt::encryptString($value);
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Atomically adjust a numeric configuration value.
     */
    public static function adjustNumeric(string $key, float $delta, bool $floorAtZero = true): float
    {
        return DB::transaction(function () use ($key, $delta, $floorAtZero): float {
            $config = static::query()->where('key', $key)->lockForUpdate()->first();
            $current = $config ? (float) static::getValueFromModel($config, $key) : 0.0;
            $next = $current + $delta;

            if ($floorAtZero) {
                $next = max(0.0, $next);
            }

            static::setValue($key, $next);

            return $next;
        });
    }

    private static function getValueFromModel(self $config, string $key): mixed
    {
        $value = $config->value;

        if (in_array($key, static::$sensitiveKeys, true) && $value !== null) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException) {
                // Legacy unencrypted values remain readable.
            }
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Set multiple config values.
     */
    public static function setValues(array $values): void
    {
        foreach ($values as $key => $value) {
            static::setValue($key, $value);
        }
    }

    /**
     * Get multiple config values by keys.
     */
    public static function getValues(array $keys, mixed $default = null): array
    {
        $configs = static::whereIn('key', $keys)->get()->keyBy('key');

        return collect($keys)->mapWithKeys(function ($key) use ($configs, $default) {
            $config = $configs->get($key);
            $value = $config ? $config->value : $default;

            // Attempt to decode if the value is a string
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            return [$key => $value];
        })->toArray();
    }
}
