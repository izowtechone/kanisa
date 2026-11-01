<?php

use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function makeDashboardTestMember(array $overrides = []): Member
{
    return Member::query()->create(array_merge([
        'membership_number' => 'KKKT-DODOMA-0001',
        'envelope_number' => 'ENV-0001',
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Mushi',
        'gender' => 'Female',
        'date_of_birth' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
        'membership_date' => null,
        'status' => 'active',
    ], $overrides));
}

test('dashboard shows current member metrics only to authorized users', function () {
    $this->travelTo(now()->startOfMonth()->addDays(10)->setTime(12, 0));
    Setting::setValue('system_language', 'en');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('view members', 'web'));

    makeDashboardTestMember([
        'membership_number' => 'KKKT-DODOMA-0001',
        'envelope_number' => 'ENV-0001',
        'first_name' => 'Amina',
        'gender' => 'Female',
        'status' => 'active',
    ]);

    makeDashboardTestMember([
        'membership_number' => 'KKKT-DODOMA-0002',
        'envelope_number' => 'ENV-0002',
        'first_name' => 'Baraka',
        'gender' => 'Male',
        'status' => 'active',
    ]);

    $olderMember = makeDashboardTestMember([
        'membership_number' => 'KKKT-DODOMA-0003',
        'envelope_number' => 'ENV-0003',
        'first_name' => 'Neema',
        'gender' => 'Female',
        'status' => 'inactive',
    ]);
    $olderMember->forceFill(['created_at' => now()->subMonth()])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('Total members')
        ->assertSee('KKKT-DODOMA-0001')
        ->assertViewHas('memberStatistics', function ($statistics): bool {
            return (int) $statistics->total_members === 3
                && (int) $statistics->active_members === 2
                && (int) $statistics->inactive_members === 1
                && (int) $statistics->male_members === 1
                && (int) $statistics->female_members === 2
                && (int) $statistics->new_members_this_month === 2;
        });
});

test('dashboard does not disclose member data to users without member permission', function () {
    Setting::setValue('system_language', 'sw');
    $user = User::factory()->create();

    makeDashboardTestMember([
        'membership_number' => 'KKKT-PRIVATE-0001',
        'envelope_number' => 'PRIVATE-ENVELOPE',
        'first_name' => 'Private',
        'last_name' => 'Member',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('lang="sw"', false)
        ->assertDontSee('KKKT-PRIVATE-0001')
        ->assertDontSee('PRIVATE-ENVELOPE')
        ->assertDontSee('Jumla ya wanachama')
        ->assertViewHas('memberStatistics', fn ($statistics): bool => $statistics === null);
});

test('implemented authenticated pages share the application navigation', function () {
    Setting::setValue('system_language', 'en');
    $user = User::factory()->create();
    $user->givePermissionTo(
        ...collect(['view members', 'create members', 'import members', 'manage settings'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission, 'web'))
            ->all()
    );

    $this->actingAs($user);

    foreach ([
        route('dashboard'),
        route('members.index'),
        route('members.create'),
        route('members.import.create'),
        route('settings.edit'),
        route('profile.edit'),
    ] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('id="app-sidebar"', false)
            ->assertSee('aria-label="Main menu"', false)
            ->assertSee(route('dashboard'), false)
            ->assertDontSee('href="#"', false);
    }

    $this->get(route('members.create'))
        ->assertOk()
        ->assertSee(__('members.form_create_title'));
});

test('sidebar highlights the current route and hides links without permission', function () {
    Setting::setValue('system_language', 'en');
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('view members', 'web'));

    $membersResponse = $this->actingAs($user)->get(route('members.index'));

    $membersResponse
        ->assertOk()
        ->assertSee(route('members.index'), false)
        ->assertSee('nav-link-active', false)
        ->assertSee('aria-current="page"', false)
        ->assertDontSee(route('settings.edit'), false)
        ->assertDontSee('href="#"', false)
        ->assertDontSee('Finance')
        ->assertDontSee('Departments');
});

test('dashboard navigation hides member and settings links from unauthorized users', function () {
    Setting::setValue('system_language', 'sw');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="app-sidebar"', false)
        ->assertSee('Menyu kuu')
        ->assertSee('Dashibodi')
        ->assertSee('nav-link-active', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee(__('dashboard.no_member_access'))
        ->assertDontSee(route('members.index'), false)
        ->assertDontSee(route('settings.edit'), false)
        ->assertDontSee(__('dashboard.settings'));
});

test('sidebar highlights settings for users with settings permission', function () {
    Setting::setValue('system_language', 'en');
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage settings', 'web'));

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee(route('settings.edit'), false)
        ->assertSee('nav-link-active', false)
        ->assertSee('aria-current="page"', false);
});
