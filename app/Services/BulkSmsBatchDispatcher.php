<?php

namespace App\Services;

use App\Jobs\SendBulkSmsBatch;
use App\Models\BulkSmsCampaign;
use App\Models\FinancialSmsLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BulkSmsBatchDispatcher
{
    /**
     * Assigns queued recipient logs to bounded provider batches and queues one job per batch.
     * Unknown submission outcomes must be reconciled before dispatch.
     *
     * @param  list<int>  $logIds
     */
    public function dispatch(BulkSmsCampaign $campaign, array $logIds): int
    {
        if ($logIds === []) {
            return 0;
        }

        $batches = DB::transaction(function () use ($campaign, $logIds): array {
            BulkSmsCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $logs = FinancialSmsLog::query()
                ->where('bulk_sms_campaign_id', $campaign->id)
                ->where('status', 'queued')
                ->whereIn('id', $logIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'provider_batch_key', 'provider_acceptance_state']);

            if ($logs->contains('provider_acceptance_state', SwalaSmsException::UNKNOWN)) {
                throw new RuntimeException('An SMS batch has an unknown submission outcome and must be reconciled before dispatch.');
            }

            $batches = [];
            $batchSize = max(1, min(SwalaSmsService::MAX_BULK_RECIPIENTS, (int) config('services.swalasms.bulk_batch_size', 500)));

            foreach (array_chunk($logs->modelKeys(), $batchSize) as $ids) {
                $key = (string) Str::uuid();
                FinancialSmsLog::query()->whereIn('id', $ids)->update([
                    'provider' => 'swalasms',
                    'provider_batch_key' => $key,
                    'idempotency_key' => $key,
                ]);
                $batches[$key] = $ids;
            }

            return $batches;
        });

        foreach ($batches as $key => $ids) {
            try {
                SendBulkSmsBatch::dispatch($campaign->id, (string) $key)->onQueue('bulk');
            } catch (Throwable) {
                FinancialSmsLog::query()->whereIn('id', $ids)
                    ->where('status', 'queued')
                    ->where('provider_batch_key', $key)
                    ->update([
                        'status' => 'failed',
                        'error_message' => 'The SMS could not be added to the delivery queue.',
                    ]);
            }
        }

        return array_sum(array_map('count', $batches));
    }
}
