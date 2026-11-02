<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const CACHE_KEY = 'settings.all';

    /** @var array<string, string|null>|null */
    private static ?array $memo = null;

    private static ?object $memoOwner = null;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    /**
     * @return array<string, string|null>
     */
    public static function allCached(): array
    {
        if (self::$memoOwner !== app()) {
            self::$memo = null;
            self::$memoOwner = app();
        }

        return self::$memo ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => static::query()->pluck('value', 'key')->all(),
        );
    }

    public static function flushCache(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function getValue(string $key, $default = null)
    {
        return static::allCached()[$key] ?? $default;
    }

    public static function setValue(
        string $key,
        $value,
        string $group = 'general'
    ): void {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
            ]
        );
    }
}
