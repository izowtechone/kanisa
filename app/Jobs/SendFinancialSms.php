<?php

namespace App\Jobs;

use App\Models\FinancialSmsAttempt;
use App\Models\FinancialSmsLog;
use App\Services\BeemSmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendFinancialSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly int $smsLogId) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('financial-sms-'.$this->smsLogId))
                ->releaseAfter(60)
                ->expireAfter(90),
        ];
    }

    public function handle(BeemSmsGateway $gateway): void
    {
        $log = FinancialSmsLog::query()->with('transaction')->findOrFail($this->smsLogId);

        if (in_array($log->status, ['accepted', 'test_only'], true)) {
            return;
        }

        $attempt = DB::transaction(function () use ($log): FinancialSmsAttempt {
            $lockedLog = FinancialSmsLog::query()->lockForUpdate()->findOrFail($log->id);
            $attemptNumber = $lockedLog->attempts + 1;
            $lockedLog->forceFill([
                'status' => 'sending',
                'attempts' => $attemptNumber,
                'error_message' => null,
            ])->save();

            return FinancialSmsAttempt::query()->create([
                'financial_sms_log_id' => $lockedLog->id,
                'attempt_number' => $attemptNumber,
                'status' => 'sending',
                'started_at' => now(),
            ]);
        });

        if ($log->is_test) {
            $attempt->update([
                'status' => 'test_only',
                'completed_at' => now(),
            ]);
            $log->forceFill([
                'status' => 'test_only',
                'provider_message_id' => null,
                'error_message' => null,
            ])->save();

            return;
        }

        try {
            $requestId = $gateway->send($log);
        } catch (Throwable $exception) {
            $attempt->update([
                'status' => 'failed',
                'error_message' => 'SMS submission failed. Check the Beem configuration and retry.',
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $attempt->update([
            'status' => 'accepted',
            'provider_message_id' => $requestId,
            'completed_at' => now(),
        ]);
        $log->forceFill([
            'status' => 'accepted',
            'provider_message_id' => $requestId,
            'accepted_at' => now(),
            'error_message' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        FinancialSmsLog::query()
            ->whereKey($this->smsLogId)
            ->whereNotIn('status', ['accepted', 'test_only'])
            ->update([
                'status' => 'failed',
                'error_message' => 'SMS submission failed. Check the Beem configuration and retry.',
                'updated_at' => now(),
            ]);
    }
}
