<?php

namespace App\Services;

use App\Models\FinancialSetting;

class SmsSettings
{
    /**
     * Shared SMS settings use the `sms_` prefix and fall back to the legacy `beem_` rows
     * so templates, language, and automatic-notification choices survive the provider change.
     */
    public static function shared(string $name, ?string $default = null): ?string
    {
        return FinancialSetting::getValue('sms_'.$name)
            ?? FinancialSetting::getValue('beem_'.$name, $default);
    }

    public static function setShared(string $name, ?string $value): void
    {
        FinancialSetting::setValue('sms_'.$name, $value);
    }

    public static function enabled(): bool
    {
        return FinancialSetting::getValue('swala_enabled', '0') === '1';
    }

    public static function testMode(): bool
    {
        return FinancialSetting::getValue('swala_test_mode', '1') === '1';
    }

    public static function apiKey(): string
    {
        return trim((string) FinancialSetting::getValue('swala_api_key'));
    }

    public static function webhookSecret(): string
    {
        return trim((string) FinancialSetting::getValue('swala_webhook_secret'));
    }

    public static function senderId(): string
    {
        return trim((string) FinancialSetting::getValue('swala_sender_id'));
    }

    public static function hasCredentials(): bool
    {
        return self::apiKey() !== '' && self::senderId() !== '';
    }

    /**
     * Whether the saved key is a sandbox ("test") or production ("live") key, derived from its prefix only.
     */
    public static function keyEnvironment(): ?string
    {
        $key = self::apiKey();

        return match (true) {
            str_starts_with($key, 'swl_test_') => 'test',
            str_starts_with($key, 'swl_live_') => 'live',
            $key !== '' => 'unknown',
            default => null,
        };
    }
}
