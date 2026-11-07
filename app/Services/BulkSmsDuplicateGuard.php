<?php

namespace App\Services;

use App\Models\FinancialSmsLog;
use RuntimeException;

class BulkSmsDuplicateGuard
{
    /**
     * The caller must hold the campaign row lock until recipients are claimed.
     *
     * @return array<string, int>
     */
    public function winners(int $campaignId, SwalaSmsService $gateway): array
    {
        $winners = [];
        $attempted = [];

        foreach (FinancialSmsLog::query()
            ->where('bulk_sms_campaign_id', $campaignId)
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->cursor() as $log) {
            try {
                $phone = $gateway->normalizePhone($log->phone);
            } catch (RuntimeException) {
                continue;
            }

            $hasAttempt = $log->attempts > 0 || $log->provider_message_id !== null
                || $log->provider_acceptance_state === SwalaSmsException::UNKNOWN
                || in_array($log->status, ['sending', 'accepted'], true);

            if (! isset($winners[$phone]) || ($hasAttempt && ! $attempted[$phone])) {
                $winners[$phone] = $log->id;
                $attempted[$phone] = $hasAttempt;
            }
        }

        return $winners;
    }
}
