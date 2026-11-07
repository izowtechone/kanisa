<?php

namespace App\Services;

use App\Jobs\SendLoginOtp;
use App\Models\LoginOtpChallenge;
use App\Models\LoginSecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

class LoginOtpService
{
    public function start(User $user, Request $request): LoginOtpChallenge
    {
        if (config('queue.default') === 'sync') {
            throw new RuntimeException('The queue connection must be asynchronous for OTP delivery.');
        }

        $this->ensureSmsIsConfigured();
        $this->ensureSendLimitsAllow($user, $request);

        $code = $this->generateCode();
        $challengeId = (string) Str::uuid();
        $challenge = DB::transaction(function () use ($user, $code, $challengeId): LoginOtpChallenge {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->status !== 'active') {
                throw new RuntimeException('This account is unavailable.');
            }

            $phone = $this->verifiedPhone($lockedUser);
            LoginOtpChallenge::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return LoginOtpChallenge::query()->create([
                'user_id' => $lockedUser->id,
                'challenge_id' => $challengeId,
                'code_hash' => Hash::make($this->hashInput($challengeId, $code)),
                'phone_hash' => $this->phoneHash($phone),
                'send_version' => 1,
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('login_security.otp_expiration_minutes')),
                'last_sent_at' => now(),
            ]);
        }, 3);

        $this->queueCode($challenge, $code);
        $this->recordEvent('otp_requested', $user, $request);

        return $challenge;
    }

    public function resend(string $challengeId, Request $request): LoginOtpChallenge
    {
        $existingChallenge = LoginOtpChallenge::query()
            ->where('challenge_id', $challengeId)
            ->firstOrFail();
        $userId = $existingChallenge->user_id;

        if ($existingChallenge->consumed_at !== null || $existingChallenge->expires_at->isPast()) {
            throw new RuntimeException('This verification request has expired. Sign in again.');
        }

        if ($existingChallenge->last_sent_at
            ->addSeconds((int) config('login_security.otp_resend_cooldown_seconds'))
            ->isFuture()) {
            throw new RuntimeException('Please wait before requesting another verification code.');
        }

        $user = User::query()->findOrFail($userId);
        $this->ensureSmsIsConfigured();
        $this->ensureSendLimitsAllow($user, $request);

        $code = $this->generateCode();
        $challenge = DB::transaction(function () use ($challengeId, $userId, $code): LoginOtpChallenge {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($userId);
            $lockedChallenge = LoginOtpChallenge::query()
                ->where('challenge_id', $challengeId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedChallenge->consumed_at !== null || $lockedChallenge->expires_at->isPast()) {
                throw new RuntimeException('This verification request has expired. Sign in again.');
            }

            if ($lockedChallenge->last_sent_at
                ->addSeconds((int) config('login_security.otp_resend_cooldown_seconds'))
                ->isFuture()) {
                throw new RuntimeException('Please wait before requesting another verification code.');
            }

            $phone = $this->verifiedPhone($lockedUser);
            $lockedChallenge->forceFill([
                'code_hash' => Hash::make($this->hashInput($lockedChallenge->challenge_id, $code)),
                'phone_hash' => $this->phoneHash($phone),
                'send_version' => $lockedChallenge->send_version + 1,
                'attempts' => 0,
                'last_sent_at' => now(),
            ])->save();

            return $lockedChallenge;
        }, 3);

        $this->queueCode($challenge, $code);
        $this->recordEvent('otp_resent', $user, $request);

        return $challenge;
    }

    /**
     * @return 'verified'|'invalid'|'expired'|'locked'
     */
    public function verify(string $challengeId, string $code, Request $request): string
    {
        $userId = LoginOtpChallenge::query()->where('challenge_id', $challengeId)->value('user_id');

        if ($userId === null) {
            return 'expired';
        }

        return DB::transaction(function () use ($challengeId, $code, $request, $userId): string {
            $user = User::query()->lockForUpdate()->find($userId);

            if ($user === null) {
                return 'expired';
            }

            $challenge = LoginOtpChallenge::query()
                ->where('challenge_id', $challengeId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($challenge === null || $challenge->consumed_at !== null) {
                return 'expired';
            }

            if ($challenge->expires_at->isPast()
                || $user->status !== 'active'
                || $this->currentPhoneHash($user) !== $challenge->phone_hash) {
                $challenge->forceFill(['consumed_at' => now()])->save();
                $this->recordEvent('otp_expired', $user, $request);

                return 'expired';
            }

            if (! Hash::check($this->hashInput($challenge->challenge_id, $code), $challenge->code_hash)) {
                $attempts = $challenge->attempts + 1;
                $locked = $attempts >= (int) config('login_security.otp_max_attempts');

                $challenge->forceFill([
                    'attempts' => $attempts,
                    'consumed_at' => $locked ? now() : null,
                ])->save();
                $this->recordEvent($locked ? 'otp_locked' : 'otp_invalid', $user, $request);

                return $locked ? 'locked' : 'invalid';
            }

            $challenge->forceFill(['consumed_at' => now()])->save();
            $this->recordEvent('otp_verified', $user, $request);

            return 'verified';
        }, 3);
    }

    public function findPending(string $challengeId): ?LoginOtpChallenge
    {
        return LoginOtpChallenge::query()
            ->with('user')
            ->where('challenge_id', $challengeId)
            ->whereNull('consumed_at')
            ->first();
    }

    public function isPhoneVerifiedAndUsable(User $user): bool
    {
        if ($user->phone_verified_at === null || trim((string) $user->phone) === '') {
            return false;
        }

        try {
            app(SwalaSmsService::class)->toE164((string) $user->phone);
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    public function maskPhone(string $phone): string
    {
        $digits = app(SwalaSmsService::class)->normalizePhone($phone);

        return '+'.substr($digits, 0, 3).str_repeat('*', max(0, strlen($digits) - 5)).substr($digits, -2);
    }

    public function recordEvent(string $event, ?User $user, Request $request): void
    {
        LoginSecurityEvent::record($event, $user, $request);
    }

    private function ensureSmsIsConfigured(): void
    {
        if (! SmsSettings::isLiveConfigured()) {
            throw new RuntimeException('SMS verification is currently unavailable. Contact an administrator.');
        }
    }

    private function ensureSendLimitsAllow(User $user, Request $request): void
    {
        $accountKey = 'login-otp:account:'.$user->id;
        $ipKey = 'login-otp:ip:'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($accountKey, (int) config('login_security.otp_send_limit_per_hour'))
            || RateLimiter::tooManyAttempts($ipKey, (int) config('login_security.otp_ip_send_limit_per_hour'))) {
            throw new RuntimeException('Too many verification requests. Please try again later.');
        }

        RateLimiter::hit($accountKey, 3600);
        RateLimiter::hit($ipKey, 3600);
    }

    private function verifiedPhone(User $user): string
    {
        if (! $this->isPhoneVerifiedAndUsable($user)) {
            throw new RuntimeException('A verified phone number is required. Contact an administrator.');
        }

        return app(SwalaSmsService::class)->toE164((string) $user->phone);
    }

    private function currentPhoneHash(User $user): ?string
    {
        if (! $this->isPhoneVerifiedAndUsable($user)) {
            return null;
        }

        return $this->phoneHash(app(SwalaSmsService::class)->toE164((string) $user->phone));
    }

    private function phoneHash(string $phone): string
    {
        return hash_hmac('sha256', $phone, (string) config('app.key'));
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hashInput(string $challengeId, string $code): string
    {
        return $challengeId.'|'.$code;
    }

    private function queueCode(LoginOtpChallenge $challenge, string $code): void
    {
        SendLoginOtp::dispatch(
            $challenge->id,
            $challenge->send_version,
            (int) $challenge->expires_at->timestamp,
            Crypt::encryptString($code),
        )->onQueue('otp');
    }
}
