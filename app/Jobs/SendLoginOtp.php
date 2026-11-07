<?php

namespace App\Jobs;

use App\Models\LoginOtpChallenge;
use App\Models\LoginSecurityEvent;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

class SendLoginOtp implements ShouldQueue
{
    use Queueable;

    public int $tries = 100;

    public int $timeout = 30;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15];
    }

    public function middleware(): array
    {
        return [(new RateLimited('swalasms'))->releaseAfter(5)];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->setTimestamp($this->expiresAtTimestamp);
    }

    public function __construct(
        public readonly int $challengeRecordId,
        public readonly int $sendVersion,
        public readonly int $expiresAtTimestamp,
        public readonly string $encryptedCode,
    ) {}

    public function handle(SwalaSmsService $gateway): void
    {
        $challenge = LoginOtpChallenge::query()->with('user')->find($this->challengeRecordId);

        if ($challenge === null
            || $challenge->consumed_at !== null
            || $challenge->expires_at->isPast()
            || $challenge->send_version !== $this->sendVersion
            || $challenge->user->phone_verified_at === null) {
            return;
        }

        $phone = $gateway->toE164((string) $challenge->user->phone);
        $currentPhoneHash = hash_hmac('sha256', $phone, (string) config('app.key'));

        if (! hash_equals($challenge->phone_hash, $currentPhoneHash)) {
            return;
        }

        $code = Crypt::decryptString($this->encryptedCode);

        if (! Hash::check($challenge->challenge_id.'|'.$code, $challenge->code_hash)) {
            return;
        }

        $message = "KKKT CMS Login Verification\n\nYour login verification code is: {$code}\n\nThis code expires in ".(int) config('login_security.otp_expiration_minutes').' minutes. Do not share it with anyone.';
        $idempotencyKey = Uuid::uuid5(Uuid::fromString($challenge->challenge_id), 'login-otp:'.$this->sendVersion)->toString();

        try {
            $result = $gateway->sendMessage($phone, $message, $idempotencyKey);
        } catch (SwalaSmsException $exception) {
            LoginSecurityEvent::query()->create([
                'user_id' => $challenge->user_id,
                'event' => 'otp_sms_submission_failed',
                'metadata' => [
                    'http_status' => $exception->httpStatus,
                    'retryable' => $exception->retryable,
                ],
            ]);

            if (! $exception->retryable || $exception->acceptanceIsUnknown()) {
                return;
            }

            throw new RuntimeException('SwalaSMS OTP submission failed.');
        }

        LoginSecurityEvent::query()->create([
            'user_id' => $challenge->user_id,
            'event' => 'otp_sms_submitted',
            'metadata' => ['provider_status' => $result['status']],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $challenge = LoginOtpChallenge::query()->with('user')->find($this->challengeRecordId);

        if ($challenge === null) {
            return;
        }

        LoginSecurityEvent::query()->create([
            'user_id' => $challenge->user_id,
            'event' => 'otp_delivery_failed',
            'metadata' => ['exception' => $exception === null ? null : $exception::class],
        ]);
    }
}
