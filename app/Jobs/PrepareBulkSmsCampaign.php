<?php

namespace App\Jobs;

use App\Models\BulkSmsCampaign;
use App\Models\FinancialSmsLog;
use App\Models\Setting;
use App\Services\BulkSmsBatchDispatcher;
use App\Services\BulkSmsRecipientSelector;
use App\Services\FinancialSmsMessageBuilder;
use App\Services\SmsSegmentEstimator;
use App\Services\SwalaSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class PrepareBulkSmsCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $campaignId) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('bulk-sms-campaign-'.$this->campaignId))
                ->releaseAfter(60)
                ->expireAfter(1800),
        ];
    }

    public function handle(
        BulkSmsRecipientSelector $selector,
        SwalaSmsService $gateway,
        BulkSmsBatchDispatcher $dispatcher,
        FinancialSmsMessageBuilder $messages,
        SmsSegmentEstimator $segments,
    ): void {
        $campaign = BulkSmsCampaign::query()->findOrFail($this->campaignId);

        if ($campaign->status === 'cancelled' || $campaign->scheduled_at?->isFuture()) {
            return;
        }

        $campaign->forceFill([
            'status' => 'processing',
            'started_at' => $campaign->started_at ?? now(),
            'error_message' => null,
        ])->save();

        $invalidPhoneCount = 0;
        $pendingLogIds = [];
        $batchSize = max(1, min(SwalaSmsService::MAX_BULK_RECIPIENTS, (int) config('services.swalasms.bulk_batch_size', 500)));
        $criteria = $campaign->selection_criteria ?? [];

        try {
            $selector->query($criteria)
                ->orderBy('id')
                ->chunkById(200, function ($members) use (
                    $campaign,
                    $gateway,
                    $segments,
                    $dispatcher,
                    $batchSize,
                    &$invalidPhoneCount,
                    &$pendingLogIds,
                ): bool {
                    if ($campaign->fresh()->status === 'cancelled') {
                        return false;
                    }

                    foreach ($members as $member) {
                        try {
                            $phone = $gateway->normalizePhone($member->phone);
                        } catch (\RuntimeException) {
                            $invalidPhoneCount++;

                            continue;
                        }

                        $memberName = trim(implode(' ', array_filter([
                            $member->first_name,
                            $member->middle_name,
                            $member->last_name,
                        ])));
                        $message = strtr($campaign->message, [
                            '{{member_name}}' => $memberName,
                            '{{member_number}}' => $member->membership_number,
                            '{{church_name}}' => (string) Setting::getValue(
                                'church_name',
                                config('app.name'),
                            ),
                        ]);
                        $segmentCount = $segments->count($message);

                        $log = FinancialSmsLog::query()->firstOrCreate(
                            [
                                'bulk_sms_campaign_id' => $campaign->id,
                                'member_id' => $member->id,
                            ],
                            [
                                'notification_type' => 'bulk_campaign',
                                'message' => $message,
                                'created_by' => $campaign->created_by,
                                'phone' => $phone,
                                'status' => 'queued',
                                'is_test' => false,
                                'sms_segments' => $segmentCount,
                            ],
                        );

                        if ($log->status !== 'queued' || $log->provider_batch_key !== null) {
                            continue;
                        }

                        $pendingLogIds[] = $log->id;

                        if (count($pendingLogIds) >= $batchSize) {
                            $dispatcher->dispatch($campaign, $pendingLogIds);
                            $pendingLogIds = [];
                        }
                    }

                    return true;
                });

            $dispatcher->dispatch($campaign, $pendingLogIds);
        } catch (Throwable $exception) {
            $campaign->refresh()->forceFill([
                'status' => 'queued',
                'error_message' => 'Campaign recipient preparation failed. Retry the campaign preparation.',
            ])->save();

            throw $exception;
        }

        $campaign->refresh();
        $recipientCount = $campaign->recipients()->count();
        $segmentTotal = $campaign->recipients()->sum('sms_segments');

        $campaign->forceFill([
            'recipient_count' => $recipientCount,
            'invalid_phone_count' => $invalidPhoneCount,
            'sms_segments' => $segmentTotal,
            'error_message' => $recipientCount === 0
                ? 'No opted-in members with valid phone numbers were available.'
                : null,
        ])->save();

        $campaign->refreshStatus();
    }
}
