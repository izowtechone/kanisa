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

    public static function timeout(): int
    {
        return max(1, min(60, (int) FinancialSetting::getValue(
            'swala_timeout',
            (string) config('services.swalasms.timeout', 20),
        )));
    }

    public static function apiKey(): string
    {
        return trim((string) FinancialSetting::getValue('swala_api_key'));
    }

    public static function senderId(): string
    {
        return trim((string) FinancialSetting::getValue('swala_sender_id'));
    }

    public static function hasCredentials(): bool
    {
        return self::apiKey() !== '' && self::senderId() !== '';
    }

    public static function senderVerificationFingerprint(string $apiKey): string
    {
        return hash_hmac('sha256', json_encode([
            $apiKey, config('services.swalasms.base_url'),
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    /**
     * @return array{senders: array, checked_at: ?string, success_at: ?string, error: ?string}
     */
    public static function senderList(): array
    {
        $empty = ['senders' => [], 'checked_at' => null, 'success_at' => null, 'error' => null];
        $stored = json_decode((string) FinancialSetting::getValue('swala_sender_ids_snapshot'), true);
        if (! is_array($stored) || ! is_string($stored['fingerprint'] ?? null)
            || ! hash_equals(self::senderVerificationFingerprint(self::apiKey()), $stored['fingerprint'])
            || ! is_array($stored['senders'] ?? null)
            || ! array_is_list($stored['senders'])
            || ! is_string($stored['checked_at'] ?? null)
            || (isset($stored['success_at']) && ! is_string($stored['success_at']))
            || (isset($stored['error']) && ! in_array($stored['error'], [
                'missing',
                'unauthorized', 'forbidden', 'endpoint_not_found', 'rate_limited',
                'provider_unavailable', 'connection', 'unavailable',
            ], true))) {
            return $empty;
        }

        return [
            'senders' => $stored['senders'],
            'checked_at' => $stored['checked_at'],
            'success_at' => $stored['success_at'] ?? null,
            'error' => $stored['error'] ?? null,
        ];
    }

    /**
     * @return array{status: string, reason: ?string, checked_at: ?string}
     */
    public static function senderVerification(): array
    {
        $list = self::senderList();
        $result = ['status' => 'unverified', 'reason' => $list['error'], 'checked_at' => $list['checked_at']];
        if ($list['error'] !== null || $list['success_at'] === null) {
            return $result;
        }
        if (self::senderId() === '') {
            $result['reason'] = 'missing_sender';

            return $result;
        }

        $matches = array_values(array_filter($list['senders'], fn (array $sender): bool => mb_strtolower($sender['sender_id']) === mb_strtolower(self::senderId())));
        foreach ($matches as $sender) {
            if ($sender['status'] === 'approved' && $sender['is_usable']) {
                return ['status' => 'approved', 'reason' => 'usable', 'checked_at' => $list['checked_at']];
            }
        }
        foreach (['processing', 'pending', 'rejected'] as $status) {
            foreach ($matches as $sender) {
                if ($sender['status'] === $status) {
                    return ['status' => $status, 'reason' => $status, 'checked_at' => $list['checked_at']];
                }
            }
        }

        $result['reason'] = $matches === [] ? 'not_found' : 'not_usable';

        return $result;
    }

    public static function isLiveConfigured(): bool
    {
        return self::enabled()
            && ! self::testMode()
            && self::hasCredentials()
            && self::keyEnvironment() === 'live';
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
