<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * W1: website text, photos and shop settings that management edits without a developer.
 */
class SiteSetting extends Model
{
    use Auditable;

    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'site_settings';

    /**
     * Stored values over the defaults in config/shop.php.
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->pluck('value', 'key')->all());

        return [...config('shop.content_defaults'), ...array_filter($stored, fn ($v) => $v !== null && $v !== '')];
    }

    public static function get(string $key): ?string
    {
        return self::values()[$key] ?? null;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            $setting = self::firstOrNew(['key' => $key]);
            $setting->value = $value;
            $setting->save();
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function deliveryFeePesewas(): int
    {
        return (int) self::get('delivery_fee_pesewas');
    }
}
