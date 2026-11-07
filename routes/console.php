<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:sync-pending-swala-sms-statuses')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('app:prune-expired-login-otp-data')
    ->hourly()
    ->withoutOverlapping();
