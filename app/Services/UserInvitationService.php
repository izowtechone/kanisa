<?php

namespace App\Services;

use App\Jobs\SendFinancialSms;
use App\Models\FinancialSmsLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserInvitationService
{
    public function __construct(private AppSettings $settings) {}

    /**
     * @throws ValidationException when the invitation tracking migration has not been applied.
     */
    public function ensureReady(): void
    {
        if (! User::supportsInvitationTracking()) {
            throw ValidationException::withMessages([
                'invitation' => __('users.invitation_schema_pending'),
            ]);
        }
    }

    public function send(User $user, User $actor): FinancialSmsLog
    {
        $this->ensureReady();

        $log = DB::transaction(function () use ($user, $actor): FinancialSmsLog {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $token = Password::createToken($user);
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);
            $phone = trim((string) $user->phone);
            $testMode = SmsSettings::testMode();
            $smsIsAvailable = SmsSettings::enabled() && ($testMode || SmsSettings::hasCredentials());
            $status = $phone === ''
                ? 'no_phone'
                : ($smsIsAvailable ? 'queued' : 'not_configured');
            $identity = Setting::getValue('church_name', $this->settings->systemName());
            $message = __('users.invitation_sms', [
                'church_name' => $identity,
                'system_name' => $this->settings->systemName(),
                'name' => $user->name,
                'login' => $user->email,
                'url' => $url,
                'minutes' => (int) config('auth.passwords.users.expire'),
            ], SmsSettings::shared('language', $this->settings->locale()));

            FinancialSmsLog::query()
                ->where('user_id', $user->id)
                ->where('notification_type', 'user_invitation')
                ->where('status', 'queued')
                ->update([
                    'status' => 'cancelled',
                    'error_message' => __('users.invitation_superseded'),
                    'updated_at' => now(),
                ]);

            $user->forceFill([
                'password_reset_requested_by' => $actor->id,
                'password_reset_requested_at' => now(),
            ])->save();

            $log = FinancialSmsLog::query()->create([
                'user_id' => $user->id,
                'notification_type' => 'user_invitation',
                'message' => $message,
                'created_by' => $actor->id,
                'phone' => $phone,
                'status' => $status,
                'is_test' => $testMode,
                'error_message' => match ($status) {
                    'no_phone' => __('users.invitation_no_phone'),
                    'not_configured' => __('users.invitation_not_configured'),
                    default => null,
                },
            ]);

            if ($log->status === 'queued') {
                try {
                    SendFinancialSms::dispatch($log->id)->afterCommit();
                } catch (Throwable $exception) {
                    report($exception);
                    $log->forceFill([
                        'status' => 'failed',
                        'error_message' => __('users.invitation_queue_failed'),
                    ])->save();
                }
            }

            return $log;
        });

        return $log->fresh();
    }
}
