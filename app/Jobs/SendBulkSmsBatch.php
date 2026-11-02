<?php

namespace App\Jobs;

use App\Models\BulkSmsCampaign;
use App\Models\FinancialSmsAttempt;
use App\Models\FinancialSmsLog;
use App\Models\Member;
use App\Models\Setting;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendBulkSmsBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 100;

    public int $maxExceptions = 5;

    public int $timeout = 120;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $batchKey,
    ) {}

    public function middleware(): array
    {
        return [
            (new RateLimited('swalasms'))->releaseAfter(60),
            (new WithoutOverlapping('bulk-sms-batch-'.$this->batchKey))
                ->releaseAfter(60)
                ->expireAfter(180),
        ];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300, 900, 1800];
    }

    public function handle(SwalaSmsService $gateway): void
    {
        $campaign = BulkSmsCampaign::query()->findOrFail($this->campaignId);

        /** @var Collection<int, FinancialSmsLog> $logs */
        $logs = $this->claimLogs($campaign, $gateway);

        if ($logs->isEmpty()) {
            $campaign->refreshStatus();

            return;
        }

        $logs = FinancialSmsLog::query()
            ->with('member')
            ->whereKey($logs->pluck('id'))
            ->get();
        $churchName = (string) Setting::getValue('church_name', config('app.name'));
        $template = strtr($campaign->message, [
            '{{member_name}}' => '{member_name}',
            '{{member_number}}' => '{member_number}',
            '{{church_name}}' => '{church_name}',
        ]);
        $usesTags = $template !== $campaign->message;

        try {
            $recipients = $logs->map(fn (FinancialSmsLog $log): array => [
                'to' => $gateway->toE164($log->phone),
                'tags' => $usesTags ? [
                    'member_name' => trim(implode(' ', array_filter([
                        $log->member?->first_name,
                        $log->member?->middle_name,
                        $log->member?->last_name,
                    ]))),
                    'member_number' => (string) $log->member?->membership_number,
                    'church_name' => $churchName,
                ] : [],
            ])->values()->all();

            $result = $gateway->sendBulk($recipients, $template, $campaign->name, $this->batchKey);
        } catch (SwalaSmsException $exception) {
            $this->recordFailure($logs, $campaign, $exception);

            return;
        } catch (Throwable) {
            $this->recordFailure($logs, $campaign, new SwalaSmsException(
                'The SMS batch could not be prepared for sending.',
                errorCode: 'REQUEST_INVALID',
                acceptanceState: SwalaSmsException::NOT_ACCEPTED,
            ));

            return;
        }

        $claimed = [];

        foreach ($logs as $log) {
            $digits = $log->phone;
            $attempt = FinancialSmsAttempt::query()
                ->where('financial_sms_log_id', $log->id)
                ->where('attempt_number', $log->attempts)
                ->first();

            if (isset($claimed[$digits])) {
                $this->markRecipientFailed($log, $attempt, 'DUPLICATE_RECIPIENT', 'Another recipient in this batch uses the same phone number.');

                continue;
            }

            $accepted = $result['messages'][$digits] ?? null;

            if ($accepted !== null) {
                $claimed[$digits] = true;
                $failedImmediately = $accepted['status'] === 'failed';
                $attempt?->update([
                    'status' => $failedImmediately ? 'failed' : 'accepted',
                    'provider_message_id' => $accepted['uid'],
                    'completed_at' => now(),
                ]);
                $log->forceFill([
                    'status' => $failedImmediately ? 'failed' : 'accepted',
                    'provider_message_id' => $accepted['uid'],
                    'provider_campaign_uid' => $result['campaign_uid'],
                    'provider_status' => $accepted['status'],
                    'delivery_status' => $accepted['status'],
                    'delivery_checked_at' => now(),
                    'provider_units' => $accepted['units'],
                    'sms_segments' => $accepted['units'] ?? $log->sms_segments,
                    'accepted_at' => now(),
                    'error_message' => $failedImmediately ? 'SwalaSMS reported the message as failed.' : null,
                    'provider_error_code' => null,
                    'provider_acceptance_state' => null,
                ])->save();

                continue;
            }

            if (isset($result['failures'][$digits])) {
                $this->markRecipientFailed($log, $attempt, 'RECIPIENT_REJECTED', 'Recipient rejected: '.$result['failures'][$digits]);

                continue;
            }

            $this->markRecipientFailed($log, $attempt, 'RECIPIENT_NOT_CONFIRMED', 'SwalaSMS did not confirm this recipient.', SwalaSmsException::UNKNOWN);
        }

        $campaign->refreshStatus();
    }

    public function failed(?Throwable $exception): void
    {
        FinancialSmsLog::query()
            ->where('bulk_sms_campaign_id', $this->campaignId)
            ->where('provider_batch_key', $this->batchKey)
            ->whereIn('status', ['queued', 'sending'])
            ->update([
                'status' => 'failed',
                'error_message' => 'SMS submission failed after repeated attempts. Review the SwalaSMS settings and retry.',
            ]);

        BulkSmsCampaign::query()->find($this->campaignId)?->refreshStatus();
    }

    /**
     * Locks the batch's still-eligible recipients and marks them as sending.
     *
     * @return Collection<int, FinancialSmsLog>
     */
    private function claimLogs(BulkSmsCampaign $campaign, SwalaSmsService $gateway): Collection
    {
        return DB::transaction(function () use ($campaign, $gateway): Collection {
            $lockedCampaign = BulkSmsCampaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if ($lockedCampaign->status === 'cancelled') {
                return new Collection;
            }

            $claimed = new Collection;
            $logs = FinancialSmsLog::query()
                ->where('bulk_sms_campaign_id', $campaign->id)
                ->where('provider_batch_key', $this->batchKey)
                ->where('status', 'queued')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($logs as $log) {
                $member = Member::query()->lockForUpdate()->find($log->member_id);

                try {
                    $currentPhone = $member === null ? null : $gateway->normalizePhone((string) $member->phone);
                } catch (\RuntimeException) {
                    $currentPhone = null;
                }

                if ($member === null || ! $member->sms_opt_in || $currentPhone !== $log->phone) {
                    $log->forceFill([
                        'status' => 'cancelled',
                        'error_message' => __('finance.sms_campaign_recipient_no_longer_eligible'),
                    ])->save();

                    continue;
                }

                $attemptNumber = $log->attempts + 1;
                $log->forceFill([
                    'status' => 'sending',
                    'attempts' => $attemptNumber,
                    'error_message' => null,
                    'provider' => 'swalasms',
                ])->save();
                FinancialSmsAttempt::query()->create([
                    'financial_sms_log_id' => $log->id,
                    'attempt_number' => $attemptNumber,
                    'status' => 'sending',
                    'started_at' => now(),
                ]);
                $claimed->push($log);
            }

            return $claimed;
        });
    }

    private function markRecipientFailed(
        FinancialSmsLog $log,
        ?FinancialSmsAttempt $attempt,
        string $code,
        string $message,
        string $acceptanceState = SwalaSmsException::NOT_ACCEPTED,
    ): void {
        $attempt?->update([
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ]);
        $log->forceFill([
            'status' => 'failed',
            'error_message' => $message,
            'provider_error_code' => $code,
            'provider_acceptance_state' => $acceptanceState,
        ])->save();
    }

    /**
     * @param  Collection<int, FinancialSmsLog>  $logs
     */
    private function recordFailure(Collection $logs, BulkSmsCampaign $campaign, SwalaSmsException $exception): void
    {
        $message = 'SwalaSMS'.($exception->errorCode !== null ? ' ['.$exception->errorCode.']' : '').': '.$exception->getMessage();
        $attempts = (int) $logs->max('attempts');
        $canRetry = $exception->retryable
            && $attempts < SendFinancialSms::MAX_SUBMISSION_ATTEMPTS
            && $this->job !== null
            && $this->job->getConnectionName() !== 'sync';

        foreach ($logs as $log) {
            FinancialSmsAttempt::query()
                ->where('financial_sms_log_id', $log->id)
                ->where('attempt_number', $log->attempts)
                ->update(['status' => 'failed', 'error_message' => $message, 'completed_at' => now()]);
            $log->forceFill([
                'status' => $canRetry ? 'queued' : 'failed',
                'error_message' => $message,
                'provider_error_code' => $exception->errorCode,
                'provider_acceptance_state' => $exception->acceptanceState,
            ])->save();
        }

        if ($canRetry) {
            $backoff = $this->backoff();
            $this->release($exception->retryAfterSeconds ?? $backoff[min($attempts - 1, count($backoff) - 1)]);

            return;
        }

        $campaign->refreshStatus();
    }
}
