<?php

use App\Jobs\SendLoginOtp;
use App\Models\FinancialSetting;
use App\Models\LoginOtpChallenge;
use App\Models\LoginSecurityEvent;
use App\Models\User;
use App\Services\SwalaSmsService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

function loginOtpConfigure(): void
{
    config(['queue.default' => 'database']);
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '0');
    FinancialSetting::setValue('swala_api_key', 'swl_live_otp_test_key');
    FinancialSetting::setValue('swala_sender_id', 'KKKT');
}

function loginOtpBegin(User $user, TestCase $testCase): LoginOtpChallenge
{
    Queue::fake([SendLoginOtp::class]);

    $testCase->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => '1',
    ])->assertRedirect(route('login.otp.show'));

    return LoginOtpChallenge::query()
        ->where('challenge_id', session('login_otp.challenge_id'))
        ->firstOrFail();
}

it('requires a valid otp before authenticating and regenerates the session', function () {
    loginOtpConfigure();
    $this->travelTo('2026-11-06 01:00:00');
    $user = User::factory()->create([
        'phone' => '+255712345678',
        'phone_verified_at' => now(),
    ]);
    $challenge = loginOtpBegin($user, $this);
    $challenge->forceFill([
        'code_hash' => Hash::make($challenge->challenge_id.'|123456'),
    ])->save();
    $pendingSessionId = session()->getId();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post(route('login.otp.verify'), ['code' => '123456'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    $this->assertNotSame($pendingSessionId, session()->getId());
    $this->assertSame($user->id, session('otp_verified_user_id'));
    expect($challenge->fresh()->consumed_at)->not->toBeNull()
        ->and(Hash::check($challenge->challenge_id.'|123456', $challenge->fresh()->code_hash))->toBeTrue()
        ->and($user->fresh()->last_login_at->toDateTimeString())->toBe('2026-11-06 01:00:00');

    $this->post(route('login.otp.verify'), ['code' => '123456'])->assertForbidden();
});

it('does not authenticate users with unverified phone numbers', function () {
    loginOtpConfigure();
    Queue::fake([SendLoginOtp::class]);
    $user = User::factory()->create(['phone' => '+255712345678']);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertDatabaseCount('login_otp_challenges', 0);
    $this->assertDatabaseHas('login_security_events', [
        'user_id' => $user->id,
        'event' => 'otp_unavailable',
    ]);
    Queue::assertNothingPushed();
});

it('blocks ordinary administrator login until otp verification', function () {
    loginOtpConfigure();
    $user = User::factory()->create([
        'phone' => '+255712345678',
        'phone_verified_at' => now(),
    ]);
    $user->assignRole(Role::findOrCreate('Mchungaji', 'web'));

    loginOtpBegin($user, $this);

    $this->assertGuest();
    $this->assertDatabaseHas('login_otp_challenges', ['user_id' => $user->id]);
});

it('allows only the Super Admin role to bypass sms otp', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate(User::SUPER_ADMIN_ROLE, 'web'));

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('login_otp_challenges', 0);
});

it('rejects invalid codes and consumes the challenge after five failures', function () {
    loginOtpConfigure();
    $user = User::factory()->create([
        'phone' => '+255712345678',
        'phone_verified_at' => now(),
    ]);
    $challenge = loginOtpBegin($user, $this);
    $challenge->forceFill([
        'code_hash' => Hash::make($challenge->challenge_id.'|123456'),
    ])->save();

    for ($attempt = 1; $attempt < 5; $attempt++) {
        $this->post(route('login.otp.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    $this->post(route('login.otp.verify'), ['code' => '000000'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertDatabaseHas('login_otp_challenges', [
        'id' => $challenge->id,
        'attempts' => 5,
    ]);
    $this->assertDatabaseHas('login_security_events', [
        'user_id' => $user->id,
        'event' => 'otp_locked',
    ]);
});

it('rejects expired codes and prevents immediate resend', function () {
    loginOtpConfigure();
    $user = User::factory()->create([
        'phone' => '+255712345678',
        'phone_verified_at' => now(),
    ]);
    $challenge = loginOtpBegin($user, $this);

    $this->post(route('login.otp.resend'))
        ->assertRedirect(route('login.otp.show'))
        ->assertSessionHasErrors('code');

    expect($challenge->fresh()->send_version)->toBe(1);

    $this->travel(6)->minutes();

    $this->get(route('login.otp.show'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect($challenge->fresh()->consumed_at)->not->toBeNull();
});

it('resends a fresh code after the cooldown and invalidates the previously queued version', function () {
    loginOtpConfigure();
    $user = User::factory()->create([
        'phone' => '+255712345678',
        'phone_verified_at' => now(),
    ]);
    $challenge = loginOtpBegin($user, $this);

    $this->travel(61)->seconds();
    $this->post(route('login.otp.resend'))
        ->assertRedirect(route('login.otp.show'));

    expect($challenge->fresh()->send_version)->toBe(2)
        ->and($challenge->fresh()->last_sent_at->timestamp)->toBeGreaterThan($challenge->last_sent_at->timestamp);
    Queue::assertPushed(SendLoginOtp::class, 2);
});

it('sends an encrypted queued code through the bulk endpoint', function () {
    loginOtpConfigure();
    $user = User::factory()->create([
        'phone' => '+255712345678',
        'phone_verified_at' => now(),
    ]);
    $challenge = loginOtpBegin($user, $this);
    $code = '135790';
    $challenge->forceFill([
        'code_hash' => Hash::make($challenge->challenge_id.'|'.$code),
    ])->save();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.swalasms.com/v2/sms/send-bulk' => Http::response([
            'success' => true,
            'data' => [
                'uid' => 'otp-campaign-1',
                'messages' => [['to' => '+255712345678', 'message_uid' => 'otp-message-1', 'status' => 'queued', 'units' => 1]],
                'failures' => [],
            ],
        ], 202),
    ]);

    (new SendLoginOtp($challenge->id, $challenge->send_version, (int) $challenge->expires_at->timestamp, Crypt::encryptString($code)))
        ->handle(app(SwalaSmsService::class));

    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
        && Str::isUuid($request->header('Idempotency-Key')[0] ?? '')
        && $request['recipients'] === ['+255712345678']
        && str_contains($request['message'], $code));
    $this->assertDatabaseHas('login_security_events', [
        'user_id' => $user->id,
        'event' => 'otp_sms_submitted',
    ]);
});

it('logs out a remembered session that has no server-side otp verification marker', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['otp_verified_user_id' => null])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('restricts login security events to authorized roles', function () {
    $permission = Permission::findOrCreate('view login security events', 'web');
    $role = Role::findOrCreate(User::SUPER_ADMIN_ROLE, 'web');
    $role->givePermissionTo($permission);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($role);
    LoginSecurityEvent::query()->create([
        'user_id' => $superAdmin->id,
        'event' => 'otp_verified',
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('security.login-events.index'))
        ->assertOk()
        ->assertSee(__('security.title'))
        ->assertSee(__('security.events.otp_verified'));

    $this->actingAs(User::factory()->create())
        ->get(route('security.login-events.index'))
        ->assertForbidden();
});
