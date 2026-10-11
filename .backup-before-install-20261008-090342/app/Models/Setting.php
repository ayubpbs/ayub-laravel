<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Website settings stored as key => value rows (one query, cached).
 * Every read is failure-safe: if the migration has not been run yet,
 * the site keeps working with the values from config/shop.php.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'shop_settings_v1';

    /** Settings that override config('shop.*') so the rest of the site picks them up automatically */
    private const CONFIG_MAP = [
        'shop_name'       => 'shop.name',
        'tagline'         => 'shop.tagline',
        'phone'           => 'shop.phone',
        'address'         => 'shop.address',
        'email'           => 'shop.email',
        'invoice_note'    => 'shop.invoice_note',
        'delivery_charge' => 'shop.delivery_charge',
    ];

    /** Social network => [label, icon] */
    public static function socials(): array
    {
        return [
            'facebook'  => ['Facebook',  '📘'],
            'messenger' => ['Messenger', '💬'],
            'instagram' => ['Instagram', '📸'],
            'youtube'   => ['YouTube',   '▶️'],
            'tiktok'    => ['TikTok',    '🎵'],
            'x'         => ['X (Twitter)', '✖️'],
        ];
    }

    public static function allValues(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function get(string $key, $default = null)
    {
        $v = self::allValues()[$key] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::allValues()[$key] ?? null;

        return $v === null || $v === '' ? $default : (bool) (int) $v;
    }

    public static function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value === null ? '' : (string) $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /** Copies saved settings into config('shop.*'). Call once from AppServiceProvider::boot() */
    public static function applyToConfig(): void
    {
        $all = self::allValues();
        foreach (self::CONFIG_MAP as $key => $cfg) {
            $v = $all[$key] ?? '';
            if ($v !== '') {
                config([$cfg => $key === 'delivery_charge' ? (int) $v : $v]);
            }
        }
    }

    public static function logoUrl(): ?string
    {
        $path = self::get('logo');

        return $path && is_file(public_path($path)) ? asset($path) : null;
    }

    /** https://wa.me/8801XXXXXXXXX link (Bangladesh numbers are fixed up automatically) */
    public static function whatsappLink(?string $message = null): ?string
    {
        $n = preg_replace('/\D+/', '', (string) self::get('whatsapp', ''));
        if ($n === '') {
            return null;
        }
        if (str_starts_with($n, '01')) {
            $n = '88' . $n;
        }

        return 'https://wa.me/' . $n . ($message ? '?text=' . rawurlencode($message) : '');
    }
}
