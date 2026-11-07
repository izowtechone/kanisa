<?php

namespace App\Console\Commands;

use App\Models\LoginOtpChallenge;
use App\Models\LoginSecurityEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:prune-expired-login-otp-data')]
#[Description('Delete expired OTP challenges and aged login security events')]
class PruneExpiredLoginOtpData extends Command
{
    public function handle(): int
    {
        $expiredChallenges = LoginOtpChallenge::query()
            ->where(function ($query): void {
                $query->where('expires_at', '<', now()->subDay())
                    ->orWhere('consumed_at', '<', now()->subDay());
            })
            ->delete();

        $oldEvents = LoginSecurityEvent::query()
            ->where('created_at', '<', now()->subDays((int) config('login_security.events_retention_days')))
            ->delete();

        $this->info("Deleted {$expiredChallenges} OTP challenges and {$oldEvents} aged security events.");

        return self::SUCCESS;
    }
}
