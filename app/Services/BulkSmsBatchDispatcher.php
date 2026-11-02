<?php

namespace App\Services;

use App\Jobs\SendBulkSmsBatch;
use App\Models\BulkSmsCampaign;
use App\Models\FinancialSmsLog;
use Illuminate\Support\Str;
use Throwable;

class BulkSmsBatchDispatcher
{
    /**
     * Assigns queued recipient logs to bounded provider batches and queues one job per batch.
     * A batch whose previous submission outcome was unknown keeps its idempotency key so the
     * provider can reuse its message IDs instead of sending twice.
     *
     * @param  list<int>  $logIds
     */
    public function dispatch(BulkSmsCampaign $campaign, array $logIds, bool $reuseUnknownKeys = false): int
    {
        if ($logIds === []) {
            return 0;
        }

        $logs = FinancialSmsLog::query()
            ->where('bulk_sms_campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->whereIn('id', $logIds)
            ->orderBy('id')
            ->get(['id', 'provider_batch_key', 'provider_acceptance_state']);

        $batches = [];
        $pooled = [];

        if ($reuseUnknownKeys) {
            foreach ($logs->whereNotNull('provider_batch_key')->groupBy('provider_batch_key') as $key => $group) {
                $stillUnknown = $group->every(fn (FinancialSmsLog $log): bool => $log->provider_acceptance_state === SwalaSmsException::UNKNOWN);

                if ($stillUnknown && $group->count() <= SwalaSmsService::MAX_BULK_RECIPIENTS) {
                    $batches[(string) $key] = $group->modelKeys();
                } else {
                    $pooled = array_merge($pooled, $group->modelKeys());
                }
            }

            $pooled = array_merge($pooled, $logs->whereNull('provider_batch_key')->modelKeys());
            sort($pooled);
        } else {
            $pooled = $logs->modelKeys();
        }

        $batchSize = max(1, min(SwalaSmsService::MAX_BULK_RECIPIENTS, (int) config('services.swalasms.bulk_batch_size', 500)));

        foreach (array_chunk($pooled, $batchSize) as $chunk) {
            $batches[(string) Str::uuid()] = $chunk;
        }

        foreach ($batches as $key => $ids) {
            FinancialSmsLog::query()->whereIn('id', $ids)->update([
                'provider' => 'swalasms',
                'provider_batch_key' => $key,
                'idempotency_key' => $key,
            ]);

            try {
                SendBulkSmsBatch::dispatch($campaign->id, (string) $key);
            } catch (Throwable) {
                FinancialSmsLog::query()->whereIn('id', $ids)->where('status', 'queued')->update([
                    'status' => 'failed',
                    'error_message' => 'The SMS could not be added to the delivery queue.',
                ]);
            }
        }

        return count($logs);
    }
}
