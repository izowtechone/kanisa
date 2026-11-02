<?php

use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function userManagementTestActor(array $permissions): User
{
    $user = User::factory()->create();

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

it('shows user management navigation only to users with permission', function () {
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

    $this->actingAs($unauthorized)
        ->get(route('users.index'))
        ->assertForbidden();
});

it('creates an active account with a member link, an allowed role, and setup notifications', function () {
    Notification::fake();

    $creator = userManagementTestActor([
        'view users',
        'create users',
        'assign roles',
        'view members',
    ]);
    $member = userManagementTestMember();
    $permission = Permission::findOrCreate('view members', 'web');
    $role = Role::query()->create([
        'name' => 'Member Clerk',
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($permission);

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

    expect($managedUser->status)->toBe('active')
        ->and($managedUser->member_id)->toBe($member->id)
        ->and($managedUser->hasRole($role))->toBeTrue();
    $this->get(route('users.show', $managedUser))
        ->assertOk()
        ->assertSee($managedUser->name)
        ->assertSee(__('users.statuses.active'));
    Notification::assertSentTo($managedUser, VerifyEmail::class);
    Notification::assertSentTo($managedUser, ResetPassword::class);
});

it('forbids account creation without permission and rejects role permission escalation', function () {
    $unauthorized = userManagementTestActor(['view users']);

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
            'status' => 'active',
            'role_ids' => [$elevatedRole->id],
        ])
        ->assertSessionHasErrors('role_ids');

    $this->assertDatabaseMissing('users', ['email' => 'escalated@example.test']);
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

it('blocks suspended accounts at login and records the last login for active accounts', function () {
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
    ]);

    $this->post(route('login'), [
        'email' => $activeUser->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    expect($activeUser->fresh()->last_login_at?->toDateTimeString())
        ->toBe('2026-11-02 12:00:00');
});

it('prevents deactivating the last active super administrator', function () {
    $actor = userManagementTestActor(['deactivate users']);
    $superAdminRole = Role::query()->create([
        'name' => User::SUPER_ADMIN_ROLE,
        'guard_name' => 'web',
    ]);
    $managedUser = User::factory()->create(['status' => 'active']);
    $managedUser->assignRole($superAdminRole);

    $this->actingAs($actor)
        ->from(route('users.show', $managedUser))
        ->patch(route('users.status', $managedUser), ['status' => 'inactive'])
        ->assertSessionHasErrors('status');

    expect($managedUser->fresh()->status)->toBe('active');
});

it('rejects login for accounts without an active status', function () {
    $user = User::factory()->create(['status' => 'inactive']);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    expect(Auth::check())->toBeFalse();
});
