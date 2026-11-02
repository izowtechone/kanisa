<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Request-scoped access to the church-wide settings stored in the settings table.
 */
class AppSettings
{
    public const LOCALES = ['sw', 'en'];

    public const DATE_FORMATS = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'j M Y'];

    public const TIME_FORMATS = ['H:i', 'h:i A'];

    public const CURRENCIES = ['TZS', 'USD', 'KES', 'UGX', 'EUR', 'GBP'];

    public const TIMEZONES = ['Africa/Dar_es_Salaam', 'Africa/Nairobi', 'Africa/Kampala', 'UTC'];

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            $this->values = Setting::allCached();
        } catch (Throwable) {
            $this->values = [];
        }

        return $this->values;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public function forget(): void
    {
        Setting::flushCache();
        $this->values = null;
    }

    public function systemName(): string
    {
        return $this->get('system_name') ?? config('app.name', 'KKKT CMS');
    }

    public function churchName(): ?string
    {
        return $this->get('church_name');
    }

    public function primaryColor(): string
    {
        $color = $this->get('brand_primary_color');

        return BrandPalette::isValidHex($color) ? $color : BrandPalette::DEFAULT_PRIMARY;
    }

    public function secondaryColor(): string
    {
        $color = $this->get('brand_secondary_color');

        return BrandPalette::isValidHex($color) ? $color : BrandPalette::DEFAULT_SECONDARY;
    }

    public function logoUrl(): ?string
    {
        return $this->publicFileUrl($this->get('church_logo'), 'church-logos/');
    }

    public function faviconUrl(): ?string
    {
        return $this->publicFileUrl($this->get('church_favicon'), 'church-favicons/');
    }

    public function showLogoInSidebar(): bool
    {
        return $this->get('logo_display', 'sidebar') !== 'hidden';
    }

    public function locale(): string
    {
        $locale = $this->get('system_language', config('app.locale'));

        return in_array($locale, self::LOCALES, true) ? $locale : 'sw';
    }

    public function timezone(): string
    {
        $timezone = $this->get('timezone', 'Africa/Dar_es_Salaam');

        return in_array($timezone, self::TIMEZONES, true) ? $timezone : 'Africa/Dar_es_Salaam';
    }

    public function dateFormat(): string
    {
        $format = $this->get('date_format', 'd/m/Y');

        return in_array($format, self::DATE_FORMATS, true) ? $format : 'd/m/Y';
    }

    public function timeFormat(): string
    {
        $format = $this->get('time_format', 'H:i');

        return in_array($format, self::TIME_FORMATS, true) ? $format : 'H:i';
    }

    public function currency(): string
    {
        $currency = $this->get('currency', 'TZS');

        return in_array($currency, self::CURRENCIES, true) ? $currency : 'TZS';
    }

    private function publicFileUrl(?string $path, string $directory): ?string
    {
        if ($path === null || ! str_starts_with($path, $directory)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
