<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginSecurityEvent;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');
        $user = User::query()->where('email', $this->input('email'))->first();

        if (! Auth::validate($credentials) || $user === null) {
            $this->recordFailedAttempt();
            LoginSecurityEvent::record('password_failed', $user, $this);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if ($user->status !== 'active') {
            $this->recordFailedAttempt();
            LoginSecurityEvent::record('password_failed', $user, $this);

            throw ValidationException::withMessages([
                'email' => __('users.account_unavailable'),
            ]);
        }

        $this->clearRateLimits();

        return $user;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $accountBlocked = $this->progressiveLockoutSeconds(
            $this->accountThrottleKey(),
            (int) config('login_security.login_account_failures_per_hour'),
        );
        $ipBlocked = $this->progressiveLockoutSeconds(
            $this->ipThrottleKey(),
            (int) config('login_security.login_ip_failures_per_hour'),
        );

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5) && $accountBlocked === 0 && $ipBlocked === 0) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            $accountBlocked,
            $ipBlocked,
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function recordFailedAttempt(): void
    {
        RateLimiter::hit($this->throttleKey());
        $this->hitProgressiveLimit($this->accountThrottleKey(), (int) config('login_security.login_account_failures_per_hour'));
        $this->hitProgressiveLimit($this->ipThrottleKey(), (int) config('login_security.login_ip_failures_per_hour'));
    }

    private function hitProgressiveLimit(string $key, int $threshold): void
    {
        RateLimiter::hit($key, 3600);
        $attempts = RateLimiter::attempts($key);

        if ($attempts < $threshold) {
            return;
        }

        $stage = intdiv($attempts - $threshold, 3);
        $lockKey = $key.':lock:'.$stage;

        if (! RateLimiter::tooManyAttempts($lockKey, 1)) {
            $base = (int) config('login_security.progressive_lockout_seconds');
            $maximum = (int) config('login_security.progressive_lockout_max_seconds');
            $delay = min($maximum, $base * (2 ** min($stage, 10)));
            RateLimiter::hit($lockKey, $delay);
        }
    }

    private function progressiveLockoutSeconds(string $key, int $threshold): int
    {
        $attempts = RateLimiter::attempts($key);

        if ($attempts < $threshold) {
            return 0;
        }

        $stage = intdiv($attempts - $threshold, 3);

        return RateLimiter::availableIn($key.':lock:'.$stage);
    }

    private function accountThrottleKey(): string
    {
        return 'login:account:'.hash('sha256', Str::lower($this->string('email')));
    }

    private function ipThrottleKey(): string
    {
        return 'login:ip:'.hash('sha256', (string) $this->ip());
    }

    private function clearRateLimits(): void
    {
        RateLimiter::clear($this->throttleKey());
        $this->clearProgressiveLimit($this->accountThrottleKey(), (int) config('login_security.login_account_failures_per_hour'));
        $this->clearProgressiveLimit($this->ipThrottleKey(), (int) config('login_security.login_ip_failures_per_hour'));
    }

    private function clearProgressiveLimit(string $key, int $threshold): void
    {
        $attempts = RateLimiter::attempts($key);
        $stage = $attempts < $threshold ? null : intdiv($attempts - $threshold, 3);

        RateLimiter::clear($key);

        if ($stage !== null) {
            RateLimiter::clear($key.':lock:'.$stage);
        }
    }
}
