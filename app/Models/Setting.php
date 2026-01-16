<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        Cache::forget("setting.{$key}");
    }

    /**
     * Get an encrypted setting value
     */
    public static function getEncrypted(string $key, $default = null)
    {
        $value = self::get($key);

        if (!$value) {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Set an encrypted setting value
     */
    public static function setEncrypted(string $key, $value): void
    {
        $encrypted = Crypt::encryptString($value);
        self::set($key, $encrypted);
    }
}
