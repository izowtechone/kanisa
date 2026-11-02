<?php

use App\Models\Community;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function communityModuleTestUser(array $permissions = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function communityModuleTestMember(array $overrides = []): Member
{
    return Member::query()->create(array_merge([
        'membership_number' => 'KKKT-COMMUNITY-0001',
        'envelope_number' => 'COMMUNITY-ENV-0001',
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
    ], $overrides));
}

it('denies community pages and creation to users without the required permissions', function () {
    $user = communityModuleTestUser();

    $this->actingAs($user)
        ->get(route('communities.index'))
        ->assertForbidden();

    $this->get(route('communities.create'))
        ->assertForbidden();

    $this->post(route('communities.store'), ['name' => 'Unauthorized'])
        ->assertForbidden();

    $this->assertDatabaseCount('communities', 0);
});

it('creates communities with active member leaders and sequential configured codes', function () {
    Setting::setValue('system_language', 'en');
    Setting::setValue('community_code_prefix', 'jum');
    $leader = communityModuleTestMember();
    $user = communityModuleTestUser(['create communities']);

    $this->actingAs($user)
        ->post(route('communities.store'), [
            'name' => 'Kisasa Jumuiya',
            'description' => 'Members in the Kisasa area.',
            'leader_member_id' => $leader->id,
        ])
        ->assertRedirect();

    $firstCommunity = Community::query()->firstOrFail();

    expect($firstCommunity->code)->toBe('JUM-001')
        ->and($firstCommunity->leader_member_id)->toBe($leader->id);

    $this->post(route('communities.store'), ['name' => 'Mlimani Jumuiya'])
        ->assertRedirect();

    expect(Community::query()->where('name', 'Mlimani Jumuiya')->value('code'))
        ->toBe('JUM-002');
});

it('rejects inactive and soft-deleted members as community leaders', function () {
    Setting::setValue('system_language', 'en');
    $inactiveLeader = communityModuleTestMember([
        'membership_number' => 'KKKT-COMMUNITY-0002',
        'envelope_number' => 'COMMUNITY-ENV-0002',
        'status' => 'inactive',
    ]);
    $user = communityModuleTestUser(['create communities']);

    $this->actingAs($user)
        ->from(route('communities.create'))
        ->post(route('communities.store'), [
            'name' => 'Inactive Leader Community',
            'leader_member_id' => $inactiveLeader->id,
        ])
        ->assertSessionHasErrors([
            'leader_member_id' => __('communities.leader_not_active'),
        ]);

    $deletedLeader = communityModuleTestMember([
        'membership_number' => 'KKKT-COMMUNITY-0003',
        'envelope_number' => 'COMMUNITY-ENV-0003',
    ]);
    $deletedLeader->delete();

    $this->post(route('communities.store'), [
        'name' => 'Deleted Leader Community',
        'leader_member_id' => $deletedLeader->id,
    ])->assertSessionHasErrors('leader_member_id');

    $this->assertDatabaseCount('communities', 0);
});

it('searches and filters communities and escapes user-provided names', function () {
    Setting::setValue('system_language', 'en');
    Community::factory()->create([
        'code' => 'JUM-101',
        'name' => '<script>alert(1)</script>',
        'status' => 'inactive',
    ]);
    Community::factory()->create([
        'code' => 'JUM-102',
        'name' => 'Kisasa North',
        'status' => 'active',
    ]);
    $user = communityModuleTestUser(['view communities']);

    $this->actingAs($user)
        ->get(route('communities.index', ['search' => 'JUM-101', 'status' => 'inactive']))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('Kisasa North');
});

it('blocks community deletion while any linked member record exists and allows safe deletion', function () {
    Setting::setValue('system_language', 'en');
    $community = Community::factory()->create(['code' => 'JUM-201']);
    $linkedMember = communityModuleTestMember(['community_id' => $community->id]);
    $linkedMember->delete();
    $user = communityModuleTestUser(['delete communities']);

    $this->actingAs($user)
        ->from(route('communities.index'))
        ->delete(route('communities.destroy', $community))
        ->assertSessionHas('error', __('communities.delete_blocked'));

    $this->assertDatabaseHas('communities', [
        'id' => $community->id,
        'deleted_at' => null,
    ]);

    $emptyCommunity = Community::factory()->create(['code' => 'JUM-202']);

    $this->delete(route('communities.destroy', $emptyCommunity))
        ->assertRedirect(route('communities.index'));

    $this->assertSoftDeleted('communities', ['id' => $emptyCommunity->id]);
});

it('assigns members to active communities and preserves an explicitly cleared selection after validation fails', function () {
    Setting::setValue('system_language', 'en');
    Setting::setValue('church_short_name', 'DODOMA');
    $community = Community::factory()->create(['code' => 'JUM-301']);
    $user = communityModuleTestUser(['create members', 'edit members', 'assign members to communities']);

    $this->actingAs($user)
        ->post(route('members.store'), [
            'envelope_number' => 'COMMUNITY-ENV-301',
            'first_name' => 'Neema',
            'last_name' => 'Mushi',
            'gender' => 'Female',
            'status' => 'active',
            'community_id' => $community->id,
        ])
        ->assertRedirect(route('members.index'));

    $member = Member::query()->where('envelope_number', 'COMMUNITY-ENV-301')->firstOrFail();

    expect($member->community_id)->toBe($community->id);

    $this->from(route('members.edit', $member))
        ->put(route('members.update', $member), [
            'envelope_number' => $member->envelope_number,
            'first_name' => '',
            'last_name' => $member->last_name,
            'gender' => $member->gender,
            'status' => $member->status,
            'community_id' => '',
        ])
        ->assertSessionHasErrors('first_name');

    $this->get(route('members.edit', $member))
        ->assertOk()
        ->assertViewHas('selectedCommunity', fn (?Community $selectedCommunity): bool => $selectedCommunity === null);

    expect($member->fresh()->community_id)->toBe($community->id);
});
