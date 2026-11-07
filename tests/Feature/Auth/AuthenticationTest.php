<?php

use App\Jobs\SendLoginOtp;
use App\Models\FinancialSetting;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('ordinary users must complete otp after password authentication', function () {
    config(['queue.default' => 'database']);
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '0');
    FinancialSetting::setValue('swala_api_key', 'swl_live_test_key');
    FinancialSetting::setValue('swala_sender_id', 'KKKT');
    Queue::fake([SendLoginOtp::class]);
    $user = User::factory()->create([
        'phone' => '+255712345678',
        'phone_verified_at' => now(),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login.otp.show'));
    Queue::assertPushed(SendLoginOtp::class, fn (SendLoginOtp $job): bool => $job->queue === 'otp');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
