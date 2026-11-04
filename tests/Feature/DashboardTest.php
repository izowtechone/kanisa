<?php

use App\Models\Community;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
    $user->assignRole(Role::findOrCreate(User::SUPER_ADMIN_ROLE, 'web'));

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

test('community permission shows community aggregates without exposing member records', function () {
    Setting::setValue('system_language', 'en');
    Community::factory()->create([
        'code' => 'JUM-DASH-001',
        'name' => 'Kisasa Jumuiya',
    ]);
    makeDashboardTestMember([
        'membership_number' => 'KKKT-PRIVATE-DASH-0001',
        'envelope_number' => 'PRIVATE-DASH-ENV',
        'first_name' => 'Private',
        'last_name' => 'Member',
    ]);
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('view communities', 'web'));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Active Jumuiya communities')
        ->assertDontSee('KKKT-PRIVATE-DASH-0001')
        ->assertDontSee('PRIVATE-DASH-ENV')
        ->assertViewHas('memberStatistics', fn ($statistics): bool => $statistics === null)
        ->assertViewHas('communityStatistics', function (array $statistics): bool {
            return $statistics['active_communities'] === 1
                && $statistics['unassigned_active_members'] === 1;
        });
});

test('Sunday Offering permission shows only its monthly aggregate and currency', function () {
    Setting::setValue('system_language', 'en');
    Setting::setValue('currency', 'TZS');
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('view Sunday offerings', 'web'));

    FinancialTransaction::query()->create([
        'transaction_number' => 'KKKT-DASH-TXN-0001',
        'receipt_number' => 'KKKT-DASH-RCPT-0001',
        'source' => FinancialTransaction::SOURCE_SUNDAY_OFFERING,
        'idempotency_key' => 'dashboard-offering-approved',
        'payload_hash' => hash('sha256', 'approved'),
        'type' => 'offering',
        'member_name' => 'Private Sunday Member',
        'amount' => '125.00',
        'currency' => 'TZS',
        'payment_method' => 'cash',
        'status' => 'approved',
        'transaction_date' => now()->startOfMonth()->toDateString(),
    ]);
    FinancialTransaction::query()->create([
        'transaction_number' => 'KKKT-DASH-TXN-0002',
        'receipt_number' => 'KKKT-DASH-RCPT-0002',
        'source' => FinancialTransaction::SOURCE_SUNDAY_OFFERING,
        'idempotency_key' => 'dashboard-offering-pending',
        'payload_hash' => hash('sha256', 'pending'),
        'type' => 'offering',
        'member_name' => 'Private Pending Member',
        'amount' => '50.00',
        'currency' => 'TZS',
        'payment_method' => 'cash',
        'status' => 'pending',
        'transaction_date' => now()->startOfMonth()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Approved Sunday offerings')
        ->assertSee('TZS 125.00')
        ->assertDontSee('Private Sunday Member')
        ->assertDontSee('KKKT-DASH-TXN-0001')
        ->assertViewHas('memberStatistics', fn ($statistics): bool => $statistics === null)
        ->assertViewHas('sundayOfferingStatistics', function ($statistics): bool {
            return (int) $statistics->approved_count === 1
                && (int) $statistics->pending_count === 1
                && (float) $statistics->approved_amount === 125.0;
        });
});

test('sidebar highlights settings only for Super Admin users', function () {
    Setting::setValue('system_language', 'en');
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('manage settings', 'web'));
    $user->assignRole(Role::findOrCreate(User::SUPER_ADMIN_ROLE, 'web'));

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee(route('settings.edit'), false)
        ->assertSee('nav-link-active', false)
        ->assertSee('aria-current="page"', false);
});
