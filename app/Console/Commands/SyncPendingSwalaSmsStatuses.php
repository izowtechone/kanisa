<?php

namespace App\Console\Commands;

use App\Models\FinancialSmsLog;
use App\Services\SmsSettings;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('app:sync-pending-swala-sms-statuses')]
#[Description('Refresh a bounded batch of pending SwalaSMS delivery statuses')]
class SyncPendingSwalaSmsStatuses extends Command
{
    public function handle(SwalaSmsService $swala): int
    {
        if (! SmsSettings::isLiveConfigured()) {
            $this->warn('SwalaSMS is not configured for live delivery; status synchronization was skipped.');

            return self::SUCCESS;
        }

        $limit = max(1, min(5, (int) config('services.swalasms.requests_per_minute', 30) - 1));
        $logs = FinancialSmsLog::query()
            ->where('provider', 'swalasms')
            ->whereNotNull('provider_message_id')
            ->where(function ($query): void {
                $query->whereNull('delivery_status')
                    ->orWhereIn('delivery_status', ['queued', 'processing', 'sent', 'scheduled']);
            })
            ->where(function ($query): void {
                $query->whereNull('delivery_checked_at')
                    ->orWhere('delivery_checked_at', '<=', now()->subMinutes(5));
            })
            ->orderBy('delivery_checked_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $updated = 0;

        foreach ($logs as $smsLog) {
            try {
                $result = $swala->messageStatus($smsLog->provider_message_id);
            } catch (SwalaSmsException $exception) {
                $this->warn("SMS log {$smsLog->id}: status refresh failed ({$exception->getMessage()})");

                continue;
            }

            DB::transaction(function () use ($smsLog, $result, &$updated): void {
                $locked = FinancialSmsLog::query()->lockForUpdate()->findOrFail($smsLog->id);
                $changed = $locked->applyProviderStatus(
                    $result['status'],
                    $result['delivered_at'] !== null
                        ? Carbon::parse($result['delivered_at'])
                        : ($result['sent_at'] === null ? null : Carbon::parse($result['sent_at'])),
                    $result['failure_reason'],
                    $result['delivered_at'] === null ? null : Carbon::parse($result['delivered_at']),
                    'automatic',
                );
                $locked->forceFill(['delivery_checked_at' => now()])->save();
                $updated += (int) $changed;
            });
        }

        $this->info("Checked {$logs->count()} pending SMS messages; {$updated} status updates applied.");

        return self::SUCCESS;
    }
}
