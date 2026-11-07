<?php

use App\Jobs\SendFinancialSms;
use App\Jobs\SendLoginOtp;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\SmsSegmentEstimator;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function userManagementTestActor(array $permissions): User
{
    $user = User::factory()->create();
    $superAdmin = Role::findOrCreate(User::SUPER_ADMIN_ROLE, 'web');
    $user->assignRole($superAdmin);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function userManagementTestMember(): Member
{
    return Member::query()->create([
        'membership_number' => 'KKKT-USER-0001',
        'envelope_number' => '02001',
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Mushi',
        'gender' => 'Female',
        'date_of_birth' => '1990-05-10',
        'phone' => '0712345678',
        'email' => null,
        'address' => null,
        'membership_date' => '2020-01-01',
        'status' => 'active',
    ]);
}

it('shows user management only to Super Admin users', function () {
    $viewer = userManagementTestActor(['view users', 'create users']);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('users.index'))
        ->assertSee(__('users.navigation'));

    $this->get(route('users.create'))
        ->assertOk()
        ->assertSee(__('users.create_title'));

    $unauthorized = User::factory()->create();
    $unauthorized->givePermissionTo(Permission::findOrCreate('view users', 'web'));

    $this->actingAs($unauthorized)
        ->get(route('users.index'))
        ->assertForbidden();
});

it('creates an active account with an allowed role and queues a setup SMS to its registered phone', function () {
    FinancialSetting::setValue('swala_enabled', '1');
    Queue::fake([SendFinancialSms::class]);

    $creator = userManagementTestActor([
        'view users',
        'create users',
        'assign roles',
        'view members',
    ]);
    app(RoleSeeder::class)->run();
    $member = userManagementTestMember();
    $role = Role::findByName('Mwinjilisti', 'web');

    $this->actingAs($creator)
        ->post(route('users.store'), [
            'name' => 'Amina Mushi',
            'email' => 'amina@example.test',
            'phone' => '0712345678',
            'member_id' => $member->id,
            'status' => 'active',
            'role_ids' => [$role->id],
        ])
        ->assertRedirect();

    $managedUser = User::query()->where('email', 'amina@example.test')->firstOrFail();
    $smsLog = FinancialSmsLog::query()
        ->where('user_id', $managedUser->id)
        ->where('notification_type', 'user_invitation')
        ->firstOrFail();
    preg_match('/https?:\/\/\S+/', $smsLog->message, $setupUrlMatch);

    expect($managedUser->status)->toBe('active')
        ->and($managedUser->member_id)->toBe($member->id)
        ->and($managedUser->hasRole($role))->toBeTrue()
        ->and($managedUser->isSuperAdmin())->toBeFalse()
        ->and($managedUser->hasPermissionTo('create users'))->toBeFalse()
        ->and($managedUser->hasVerifiedEmail())->toBeTrue()
        ->and($smsLog->phone)->toBe('0712345678')
        ->and($smsLog->status)->toBe('queued')
        ->and($smsLog->message)->toContain('amina@example.test')
        ->and($smsLog->message)->toContain('Amina Mushi')
        ->and($setupUrlMatch[0] ?? '')->toContain('/reset-password/');
    $this->get(route('users.show', $managedUser))
        ->assertOk()
        ->assertSee($managedUser->name)
        ->assertSee(__('users.statuses.active'))
        ->assertSee(__('users.role_names.Mwinjilisti'))
        ->assertSee(__('users.invitation_sms_statuses.queued'));
    $this->actingAs($managedUser)
        ->get(route('users.index'))
        ->assertForbidden();
    Queue::assertPushed(
        SendFinancialSms::class,
        fn (SendFinancialSms $job): bool => $job->smsLogId === $smsLog->id,
    );
});

it('forbids ordinary users from creating accounts and blocks administrative roles for new users', function () {
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)
        ->post(route('users.store'), [
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@example.test',
            'status' => 'active',
        ])
        ->assertForbidden();

    $creator = userManagementTestActor(['create users', 'assign roles']);
    $elevatedPermission = Permission::findOrCreate('manage permissions', 'web');
    $elevatedRole = Role::query()->create([
        'name' => 'Permission Manager',
        'guard_name' => 'web',
    ]);
    $elevatedRole->givePermissionTo($elevatedPermission);

    $this->actingAs($creator)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Escalated User',
            'email' => 'escalated@example.test',
            'phone' => '0712345678',
            'status' => 'active',
            'role_ids' => [$elevatedRole->id],
        ])
        ->assertSessionHasErrors('role_ids');

    $this->assertDatabaseMissing('users', ['email' => 'escalated@example.test']);

    $superAdminRole = Role::findOrCreate(User::SUPER_ADMIN_ROLE, 'web');
    $this->actingAs($creator)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'New Administrator',
            'email' => 'new-admin@example.test',
            'phone' => '0712345678',
            'status' => 'active',
            'role_ids' => [$superAdminRole->id],
        ])
        ->assertSessionHasErrors('role_ids');

    $this->assertDatabaseMissing('users', ['email' => 'new-admin@example.test']);
});

it('prevents direct permission escalation and allows authorized users to clear all direct permissions', function () {
    $manager = userManagementTestActor(['manage permissions']);
    $restrictedPermission = Permission::findOrCreate('view users', 'web');
    $managedUser = User::factory()->create();
    $managedUser->givePermissionTo($restrictedPermission);

    $this->actingAs($manager)
        ->from(route('users.show', $managedUser))
        ->put(route('users.permissions.update', $managedUser), [
            'permission_ids' => [$restrictedPermission->id],
        ])
        ->assertSessionHasErrors('permission_ids');

    expect($managedUser->fresh()->hasDirectPermission($restrictedPermission))->toBeTrue();

    $this->put(route('users.permissions.update', $managedUser))
        ->assertRedirect(route('users.show', $managedUser));

    expect($managedUser->fresh()->directPermissions)->toBeEmpty();
});

it('blocks suspended accounts and requires otp for active accounts before recording the login', function () {
    config(['queue.default' => 'database']);
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '0');
    FinancialSetting::setValue('swala_api_key', 'swl_live_login_test');
    FinancialSetting::setValue('swala_sender_id', 'KKKT');
    Queue::fake([SendLoginOtp::class]);
    $suspendedUser = User::factory()->create([
        'email' => 'suspended@example.test',
        'status' => 'suspended',
    ]);

    $this->post(route('login'), [
        'email' => $suspendedUser->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    expect(Auth::check())->toBeFalse();

    $this->travelTo('2026-11-02 12:00:00');
    $activeUser = User::factory()->create([
        'email' => 'active@example.test',
        'status' => 'active',
        'phone' => '+255712345678',
        'phone_verified_at' => now(),
    ]);

    $this->post(route('login'), [
        'email' => $activeUser->email,
        'password' => 'password',
    ])->assertRedirect(route('login.otp.show'));

    $this->assertGuest();
    expect($activeUser->fresh()->last_login_at)->toBeNull();
});

it('prevents deactivating the last active super administrator', function () {
    $managedUser = userManagementTestActor(['deactivate users']);

    $this->actingAs($managedUser)
        ->from(route('users.show', $managedUser))
        ->patch(route('users.status', $managedUser), ['status' => 'inactive'])
        ->assertSessionHasErrors('status');

    expect($managedUser->fresh()->status)->toBe('active');
});

it('prevents demoting the last active super administrator', function () {
    $managedUser = userManagementTestActor([]);
    $role = Role::findOrCreate('Mchungaji', 'web');

    $this->actingAs($managedUser)
        ->from(route('users.edit', $managedUser))
        ->put(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'phone' => $managedUser->phone,
            'member_id' => null,
            'role_ids' => [$role->id],
        ])
        ->assertSessionHasErrors('status');

    expect($managedUser->fresh()->isSuperAdmin())->toBeTrue();
});

it('allows a Super Admin to attest to an unchanged phone and clears verification when it changes', function () {
    $administrator = userManagementTestActor([]);
    $managedUser = User::factory()->create([
        'phone' => '+255712345678',
        'phone_verified_at' => null,
    ]);

    $this->actingAs($administrator)
        ->put(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'phone' => '+255712345678',
            'phone_verified' => '1',
        ])
        ->assertRedirect(route('users.show', $managedUser));

    expect($managedUser->fresh()->phone_verified_at)->not->toBeNull();

    $this->actingAs($administrator)
        ->put(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'phone' => '+255798765432',
            'phone_verified' => '1',
        ])
        ->assertRedirect(route('users.show', $managedUser));

    expect($managedUser->fresh()->phone_verified_at)->toBeNull();
    $this->assertDatabaseHas('login_security_events', [
        'user_id' => $managedUser->id,
        'event' => 'phone_verification_changed',
    ]);
});

it('prevents a Super Admin from deleting their own account', function () {
    $administrator = userManagementTestActor([]);

    $this->actingAs($administrator)
        ->delete(route('users.destroy', $administrator))
        ->assertForbidden();

    $this->assertModelExists($administrator);
});

it('sends an expiring password reset link by SMS without exposing the token or password', function () {
    Setting::setValue('church_name', 'KKKT Dodoma');
    FinancialSetting::setValue('swala_enabled', '1');
    Queue::fake([SendFinancialSms::class]);
    $administrator = userManagementTestActor(['edit users']);
    $managedUser = User::factory()->create([
        'name' => 'Neema Mushi',
        'email' => 'neema@example.test',
        'phone' => '0712345678',
    ]);

    $this->actingAs($administrator)
        ->post(route('users.password-reset', $managedUser))
        ->assertRedirect(route('users.show', $managedUser))
        ->assertSessionHas('success', __('users.password_reset_requested'));

    $smsLog = FinancialSmsLog::query()
        ->where('user_id', $managedUser->id)
        ->where('notification_type', 'user_invitation')
        ->firstOrFail();
    preg_match('/reset-password\/([^?\s]+)/', $smsLog->message, $tokenMatch);
    $token = $tokenMatch[1] ?? '';

    expect($smsLog->phone)->toBe($managedUser->phone)
        ->and($smsLog->message)->toContain('KKKT Dodoma')
        ->and($smsLog->message)->toContain($managedUser->email)
        ->and($token)->not->toBe('')
        ->and(Password::tokenExists($managedUser, $token))->toBeTrue()
        ->and($managedUser->fresh()->password_reset_requested_by)->toBe($administrator->id)
        ->and($managedUser->fresh()->password_reset_requested_at)->not->toBeNull();

    $this->assertDatabaseMissing('password_reset_tokens', ['token' => $token]);
    Queue::assertPushed(
        SendFinancialSms::class,
        fn (SendFinancialSms $job): bool => $job->smsLogId === $smsLog->id,
    );

    $this->travel(61)->minutes();
    expect(Password::tokenExists($managedUser, $token))->toBeFalse();
});

it('keeps the account when invitation SMS delivery fails and retries without duplicating the account', function () {
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '0');
    FinancialSetting::setValue('swala_api_key', 'swl_test_invitation');
    FinancialSetting::setValue('swala_sender_id', 'KKKT');
    Queue::fake([SendFinancialSms::class]);
    $administrator = userManagementTestActor(['create users', 'assign roles']);
    $role = Role::query()->create(['name' => 'Invitation User', 'guard_name' => 'web']);

    $this->actingAs($administrator)
        ->post(route('users.store'), [
            'name' => 'Amina Mushi',
            'email' => 'invited@example.test',
            'phone' => '0712345678',
            'status' => 'active',
            'role_ids' => [$role->id],
        ])
        ->assertRedirect();

    $managedUser = User::query()->where('email', 'invited@example.test')->firstOrFail();
    $smsLog = FinancialSmsLog::query()->where('user_id', $managedUser->id)->firstOrFail();
    $gateway = Mockery::mock(SwalaSmsService::class);
    $gateway->shouldReceive('normalizePhone')
        ->twice()
        ->with('0712345678')
        ->andReturn('255712345678');
    $gateway->shouldReceive('sendMessage')
        ->once()
        ->andThrow(new SwalaSmsException(
            'Provider unavailable.',
            errorCode: 'TEMPORARY',
            acceptanceState: SwalaSmsException::NOT_ACCEPTED,
        ));

    (new SendFinancialSms($smsLog->id))->handle($gateway, app(SmsSegmentEstimator::class));

    expect(User::query()->whereKey($managedUser->id)->exists())->toBeTrue()
        ->and($smsLog->fresh()->status)->toBe('failed');

    FinancialSetting::setValue('swala_enabled', '0');
    $this->post(route('users.invitation.retry', $managedUser))->assertRedirect();

    expect(User::query()->where('email', 'invited@example.test')->count())->toBe(1)
        ->and(FinancialSmsLog::query()->where('user_id', $managedUser->id)->count())->toBe(2)
        ->and(FinancialSmsLog::query()->where('user_id', $managedUser->id)->orderByDesc('id')->value('status'))
        ->toBe('not_configured');
});

it('forbids administrators from resetting their own account through user management', function () {
    $administrator = userManagementTestActor([]);

    $this->actingAs($administrator)
        ->post(route('users.password-reset', $administrator))
        ->assertForbidden();

    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $administrator->email]);
});

it('rejects login for accounts without an active status', function () {
    $user = User::factory()->create(['status' => 'inactive']);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

it('shows the user details page without invitation history', function () {
    $administrator = userManagementTestActor([]);
    $managedUser = User::factory()->create(['phone' => '0712345678']);

    $this->actingAs($administrator)
        ->get(route('users.show', $managedUser))
        ->assertOk()
        ->assertSee(__('users.invitation_never_sent'))
        ->assertDontSee(__('users.invitation_schema_pending'));
});

it('shows only the invitation history that belongs to the viewed user', function () {
    $administrator = userManagementTestActor([]);
    $managedUser = User::factory()->create(['phone' => '0712345678']);
    $otherUser = User::factory()->create(['phone' => '0755555555']);

    FinancialSmsLog::query()->create([
        'user_id' => $managedUser->id,
        'notification_type' => 'user_invitation',
        'message' => 'Invitation for the managed user',
        'created_by' => $administrator->id,
        'phone' => '0712345678',
        'status' => 'failed',
    ]);
    FinancialSmsLog::query()->create([
        'user_id' => $otherUser->id,
        'notification_type' => 'user_invitation',
        'message' => 'Invitation for another user',
        'created_by' => $administrator->id,
        'phone' => '0755555555',
        'status' => 'queued',
    ]);

    $this->actingAs($administrator)
        ->get(route('users.show', $managedUser))
        ->assertOk()
        ->assertSee(__('users.invitation_sms_statuses.failed'))
        ->assertSee('071*****78')
        ->assertDontSee('075*****55')
        ->assertDontSee(__('users.invitation_sms_statuses.queued'));

    $this->get(route('users.show', $otherUser))
        ->assertOk()
        ->assertSee(__('users.invitation_sms_statuses.queued'))
        ->assertSee('075*****55');

    expect($managedUser->smsLogs()->count())->toBe(1)
        ->and($otherUser->smsLogs()->count())->toBe(1);
});

it('keeps the details page working and blocks invitations while the tracking migration is pending', function () {
    Queue::fake([SendFinancialSms::class]);
    $administrator = userManagementTestActor([]);
    $managedUser = User::factory()->create(['phone' => '0712345678']);

    Schema::table('financial_sms_logs', function (Blueprint $table): void {
        $table->dropIndex('financial_sms_logs_user_type_created_index');
        $table->dropConstrainedForeignId('user_id');
    });

    expect(User::supportsInvitationTracking())->toBeFalse();

    $this->actingAs($administrator)
        ->get(route('users.show', $managedUser))
        ->assertOk()
        ->assertSee(__('users.invitation_schema_pending'))
        ->assertSee(__('users.invitation_never_sent'));

    $this->post(route('users.password-reset', $managedUser))
        ->assertSessionHasErrors('invitation');

    $this->post(route('users.store'), [
        'name' => 'Pending Schema',
        'email' => 'pending-schema@example.test',
        'phone' => '0712345678',
        'status' => 'active',
        'role_ids' => [Role::findOrCreate('Katibu', 'web')->id],
    ])->assertSessionHasErrors('invitation');

    $this->assertDatabaseMissing('users', ['email' => 'pending-schema@example.test']);
    Queue::assertNothingPushed();
});
