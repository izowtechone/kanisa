<?php

namespace App\Jobs;

use App\Models\BulkSmsCampaign;
use App\Models\FinancialSmsAttempt;
use App\Models\FinancialSmsLog;
use App\Models\Member;
use App\Models\User;
use App\Services\FinancialSmsMessageBuilder;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SendFinancialSms implements ShouldQueue
{
    use Queueable;

    public const MAX_SUBMISSION_ATTEMPTS = 6;

    /**
     * Rate-limit releases also count as attempts, so the job allows many releases
     * but is bounded by retryUntil() and MAX_SUBMISSION_ATTEMPTS.
     */
    public int $tries = 100;

    public int $maxExceptions = 5;

    public int $timeout = 60;

    public function __construct(public readonly int $smsLogId) {}

    public function middleware(): array
    {
        return [
            (new RateLimited('swalasms'))->releaseAfter(60),
            (new WithoutOverlapping('financial-sms-'.$this->smsLogId))
                ->releaseAfter(60)
                ->expireAfter(90),
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
        $log = FinancialSmsLog::query()
            ->with(['transaction', 'campaign', 'user'])
            ->findOrFail($this->smsLogId);

        if ($log->status !== 'queued' || $log->campaign?->status === 'cancelled') {
            return;
        }

        $attempt = DB::transaction(function () use ($log, $gateway): ?FinancialSmsAttempt {
            if ($log->bulk_sms_campaign_id !== null) {
                $campaign = BulkSmsCampaign::query()
                    ->lockForUpdate()
                    ->findOrFail($log->bulk_sms_campaign_id);

                if ($campaign->status === 'cancelled') {
                    return null;
                }
            }

            $lockedLog = FinancialSmsLog::query()->lockForUpdate()->findOrFail($log->id);

            if ($lockedLog->status !== 'queued') {
                return null;
            }

            if ($lockedLog->notification_type === 'sunday_offering') {
                $member = Member::query()
                    ->lockForUpdate()
                    ->find($lockedLog->member_id);

                if ($member === null || $member->status !== 'active' || ! $member->sms_opt_in) {
                    $lockedLog->forceFill([
                        'status' => 'cancelled',
                        'error_message' => __('finance.sms_sunday_recipient_no_longer_eligible'),
                    ])->save();

                    return null;
                }

                try {
                    $currentPhone = $gateway->normalizePhone((string) $member->phone);
                    $queuedPhone = $gateway->normalizePhone($lockedLog->phone);
                } catch (\RuntimeException) {
                    $currentPhone = null;
                    $queuedPhone = null;
                }

                if ($currentPhone === null || $currentPhone !== $queuedPhone) {
                    $lockedLog->forceFill([
                        'status' => 'cancelled',
                        'error_message' => __('finance.sms_sunday_recipient_no_longer_eligible'),
                    ])->save();

                    return null;
                }
            }

            if ($lockedLog->notification_type === 'user_invitation') {
                $user = User::query()->lockForUpdate()->find($lockedLog->user_id);

                try {
                    $currentPhone = $user === null ? null : $gateway->normalizePhone((string) $user->phone);
                    $queuedPhone = $gateway->normalizePhone($lockedLog->phone);
                } catch (\RuntimeException) {
                    $currentPhone = null;
                    $queuedPhone = null;
                }

                if ($currentPhone === null || $currentPhone !== $queuedPhone) {
                    $lockedLog->forceFill([
                        'status' => 'cancelled',
                        'error_message' => __('users.invitation_superseded'),
                    ])->save();

                    return null;
                }
            }

            if ($lockedLog->bulk_sms_campaign_id !== null) {
                $member = Member::query()
                    ->lockForUpdate()
                    ->find($lockedLog->member_id);

                if ($member === null || ! $member->sms_opt_in) {
                    $lockedLog->forceFill([
                        'status' => 'cancelled',
                        'error_message' => __('finance.sms_campaign_recipient_no_longer_eligible'),
                    ])->save();

                    return null;
                }

                try {
                    $currentPhone = $gateway->normalizePhone((string) $member->phone);
                } catch (\RuntimeException) {
                    $currentPhone = null;
                }

                if ($currentPhone === null || $currentPhone !== $lockedLog->phone) {
                    $lockedLog->forceFill([
                        'status' => 'cancelled',
                        'error_message' => __('finance.sms_campaign_recipient_no_longer_eligible'),
                    ])->save();

                    return null;
                }
            }

            $attemptNumber = $lockedLog->attempts + 1;
            $lockedLog->forceFill([
                'status' => 'sending',
                'attempts' => $attemptNumber,
                'error_message' => null,
                'provider' => 'swalasms',
                'idempotency_key' => $lockedLog->idempotency_key ?? (string) Str::uuid(),
            ])->save();

            return FinancialSmsAttempt::query()->create([
                'financial_sms_log_id' => $lockedLog->id,
                'attempt_number' => $attemptNumber,
                'status' => 'sending',
                'started_at' => now(),
            ]);
        });

        if ($attempt === null) {
            $this->refreshCampaign($log);

            return;
        }

        if ($log->is_test && $log->notification_type !== 'test_sms') {
            $attempt->update([
                'status' => 'test_only',
                'completed_at' => now(),
            ]);
            $log->forceFill([
                'status' => 'test_only',
                'provider_message_id' => null,
                'error_message' => null,
            ])->save();
            $this->refreshCampaign($log);

            return;
        }

        $log->refresh();

        try {
            $message = $log->message
                ?? ($log->transaction === null ? null : app(FinancialSmsMessageBuilder::class)->forTransaction($log->transaction));

            if ($message === null || trim($message) === '') {
                throw new SwalaSmsException('The SMS message content is missing.', errorCode: 'MESSAGE_MISSING', acceptanceState: SwalaSmsException::NOT_ACCEPTED);
            }

            $result = $gateway->sendMessage($log->phone, $message, (string) $log->idempotency_key);
        } catch (SwalaSmsException $exception) {
            $this->recordFailure($log, $attempt, $exception);

            return;
        } catch (Throwable) {
            $this->recordFailure($log, $attempt, new SwalaSmsException(
                'The SMS could not be prepared for sending.',
                errorCode: 'REQUEST_INVALID',
                acceptanceState: SwalaSmsException::NOT_ACCEPTED,
            ));

            return;
        }

        $failedImmediately = $result['status'] === 'failed';

        $attempt->update([
            'status' => $failedImmediately ? 'failed' : 'accepted',
            'provider_message_id' => $result['uid'],
            'error_message' => $failedImmediately ? $result['failure_reason'] : null,
            'completed_at' => now(),
        ]);
        $log->forceFill([
            'status' => $failedImmediately ? 'failed' : 'accepted',
            'provider_message_id' => $result['uid'],
            'accepted_at' => now(),
            'error_message' => $failedImmediately ? ($result['failure_reason'] ?? 'SwalaSMS reported the message as failed.') : null,
            'provider_status' => $result['status'],
            'provider_error_code' => null,
            'provider_acceptance_state' => null,
            'provider_units' => $result['units'],
            'provider_failure_reason' => $result['failure_reason'],
            'delivery_status' => $result['status'],
            'delivery_checked_at' => now(),
            'delivered_at' => $result['status'] === 'delivered' ? now() : null,
            'sms_segments' => $result['segments'] ?? $log->sms_segments,
        ])->save();
        $this->refreshCampaign($log);
    }

    public function failed(?Throwable $exception): void
    {
        $log = FinancialSmsLog::query()
            ->whereKey($this->smsLogId)
            ->whereNotIn('status', ['accepted', 'test_only', 'cancelled', 'failed'])
            ->first();

        if ($log === null) {
            return;
        }

        $log->forceFill([
            'status' => 'failed',
            'error_message' => 'SMS submission failed after repeated attempts. Review the SwalaSMS settings and retry.',
        ])->save();
        $this->refreshCampaign($log);
    }

    private function recordFailure(FinancialSmsLog $log, FinancialSmsAttempt $attempt, SwalaSmsException $exception): void
    {
        $message = 'SwalaSMS'.($exception->errorCode !== null ? ' ['.$exception->errorCode.']' : '').': '.$exception->getMessage();
        $canRetry = $exception->retryable
            && $log->attempts < self::MAX_SUBMISSION_ATTEMPTS
            && $this->job !== null
            && $this->job->getConnectionName() !== 'sync';
        $attempt->update([
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ]);
        $log->forceFill([
            'status' => $canRetry ? 'queued' : 'failed',
            'error_message' => $message,
            'provider_error_code' => $exception->errorCode,
            'provider_acceptance_state' => $exception->acceptanceState,
            'provider_status' => null,
        ])->save();

        if ($canRetry) {
            $backoff = $this->backoff();
            $this->release($exception->retryAfterSeconds ?? $backoff[min($log->attempts - 1, count($backoff) - 1)]);

            return;
        }

        $this->refreshCampaign($log);
    }

    private function refreshCampaign(FinancialSmsLog $log): void
    {
        if ($log->bulk_sms_campaign_id === null) {
            return;
        }

        BulkSmsCampaign::query()->find($log->bulk_sms_campaign_id)?->refreshStatus();
    }
}
