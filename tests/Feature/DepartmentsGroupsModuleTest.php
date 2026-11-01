<?php

use App\Models\Department;
use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function departmentsGroupsTestUser(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function departmentsGroupsPermissions(): array
{
    return [
        'view departments',
        'create departments',
        'edit departments',
        'delete departments',
        'view groups',
        'create groups',
        'edit groups',
        'delete groups',
        'assign group members',
    ];
}

function departmentTestAttributes(array $overrides = []): array
{
    return array_merge([
        'code' => 'DPT-001',
        'name' => 'Youth Ministry',
        'description' => 'Young members and their activities.',
        'leader_member_id' => null,
        'is_active' => true,
    ], $overrides);
}

function groupTestAttributes(int $departmentId, array $overrides = []): array
{
    return array_merge([
        'department_id' => $departmentId,
        'code' => 'GRP-001',
        'name' => 'Choir',
        'description' => 'Church choir.',
        'leader_member_id' => null,
        'is_active' => true,
    ], $overrides);
}

function departmentModuleMember(array $overrides = []): Member
{
    return Member::query()->create(array_merge([
        'membership_number' => 'KKKT-DEPT-0001',
        'envelope_number' => 'DEPT-ENV-0001',
        'first_name' => 'Neema',
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

test('department routes deny users without the matching permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('departments.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('departments.store'), departmentTestAttributes())
        ->assertForbidden();

    $this->get(route('groups.index'))
        ->assertForbidden();
});

test('department users can create view edit and delete an unlinked department', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());

    $this->actingAs($user)
        ->get(route('departments.index'))
        ->assertOk();

    $this->post(route('departments.store'), departmentTestAttributes())
        ->assertRedirect(route('departments.show', Department::query()->first()));

    $department = Department::query()->firstOrFail();
    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'code' => 'DPT-001',
        'name' => 'Youth Ministry',
    ]);

    $this->get(route('departments.show', $department))
        ->assertOk()
        ->assertSee('Youth Ministry');

    $this->put(route('departments.update', $department), departmentTestAttributes([
        'name' => 'Youth and Families',
        'is_active' => false,
    ]))->assertRedirect(route('departments.show', $department));

    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'name' => 'Youth and Families',
        'is_active' => false,
    ]);

    $this->delete(route('departments.destroy', $department))
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseMissing('departments', ['id' => $department->id]);
});

test('department codes and names cannot be duplicated', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    Department::query()->create(departmentTestAttributes());

    $this->actingAs($user)
        ->from(route('departments.create'))
        ->post(route('departments.store'), departmentTestAttributes([
            'code' => 'DPT-002',
        ]))
        ->assertSessionHasErrors('name');

    $this->from(route('departments.create'))
        ->post(route('departments.store'), departmentTestAttributes([
            'code' => 'DPT-001',
            'name' => 'Children Ministry',
        ]))
        ->assertSessionHasErrors('code');
});

test('department search status filtering and pagination use stored records', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    Department::factory()->count(16)->create();
    Department::query()->create(departmentTestAttributes([
        'code' => 'DPT-SEARCH',
        'name' => 'Searchable Department',
        'is_active' => true,
    ]));

    $this->actingAs($user)
        ->get(route('departments.index', ['search' => 'DPT-SEARCH', 'status' => 'active']))
        ->assertOk()
        ->assertSee('Searchable Department');

    $this->get(route('departments.index'))
        ->assertOk()
        ->assertSee('page=2', false);
});

test('department and group labels are available in English and Kiswahili', function () {
    expect(__('departments.title', [], 'en'))->toBe('Departments');
    expect(__('departments.title', [], 'sw'))->toBe('Idara');
    expect(__('groups.title', [], 'en'))->toBe('Groups');
    expect(__('groups.title', [], 'sw'))->toBe('Vikundi');
});

test('department deletion is blocked while it has groups', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $department = Department::query()->create(departmentTestAttributes());
    Group::query()->create(groupTestAttributes($department->id));

    $this->actingAs($user)
        ->delete(route('departments.destroy', $department))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertModelExists($department);
});

test('group users can create view edit and filter groups by department and status', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $department = Department::query()->create(departmentTestAttributes());
    $otherDepartment = Department::query()->create(departmentTestAttributes([
        'code' => 'DPT-002',
        'name' => 'Music Ministry',
    ]));

    $this->actingAs($user)
        ->post(route('groups.store'), groupTestAttributes($department->id))
        ->assertRedirect();

    $group = Group::query()->where('code', 'GRP-001')->firstOrFail();
    $this->assertSame($department->id, $group->department_id);

    $this->get(route('groups.show', $group))
        ->assertOk()
        ->assertSee('Youth Ministry')
        ->assertSee('Choir');

    $this->put(route('groups.update', $group), groupTestAttributes($department->id, [
        'name' => 'Senior Choir',
        'is_active' => false,
    ]))->assertRedirect(route('groups.show', $group));

    Group::query()->create(groupTestAttributes($otherDepartment->id, [
        'code' => 'GRP-002',
        'name' => 'Praise Team',
    ]));
    Group::factory()->count(16)->create();

    $this->get(route('groups.index', [
        'department_id' => $department->id,
        'status' => 'inactive',
        'search' => 'Senior',
    ]))->assertOk()
        ->assertSee('Senior Choir')
        ->assertDontSee('Praise Team');

    $this->assertDatabaseHas('groups', [
        'id' => $group->id,
        'name' => 'Senior Choir',
        'is_active' => false,
    ]);

    $this->get(route('groups.index'))
        ->assertOk()
        ->assertSee('page=2', false);
});

test('group codes cannot be duplicated', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $department = Department::query()->create(departmentTestAttributes());
    Group::query()->create(groupTestAttributes($department->id));

    $this->actingAs($user)
        ->from(route('groups.create'))
        ->post(route('groups.store'), groupTestAttributes($department->id, [
            'code' => 'GRP-001',
            'name' => 'Different Choir',
        ]))
        ->assertSessionHasErrors('code');
});

test('active members can be department and group leaders and leaders can be cleared', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $leader = departmentModuleMember();
    $department = Department::query()->create(departmentTestAttributes([
        'leader_member_id' => $leader->id,
    ]));

    $this->assertSame($leader->id, $department->leader_member_id);

    $this->actingAs($user)
        ->put(route('departments.update', $department), departmentTestAttributes([
            'leader_member_id' => null,
        ]))
        ->assertRedirect(route('departments.show', $department));

    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'leader_member_id' => null,
    ]);

    $this->post(route('groups.store'), groupTestAttributes($department->id, [
        'leader_member_id' => $leader->id,
    ]))
        ->assertRedirect();

    $group = Group::query()->where('department_id', $department->id)->firstOrFail();
    $this->assertSame($leader->id, $group->leader_member_id);

    $this->put(route('groups.update', $group), groupTestAttributes($department->id, [
        'leader_member_id' => null,
    ]))->assertRedirect(route('groups.show', $group));

    $this->assertDatabaseHas('groups', [
        'id' => $group->id,
        'leader_member_id' => null,
    ]);
});

test('soft deleted members cannot be assigned as leaders', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $deletedLeader = departmentModuleMember([
        'membership_number' => 'KKKT-DEPT-0002',
        'envelope_number' => 'DEPT-ENV-0002',
    ]);
    $deletedLeader->delete();

    $this->actingAs($user)
        ->from(route('departments.create'))
        ->post(route('departments.store'), departmentTestAttributes([
            'leader_member_id' => $deletedLeader->id,
        ]))
        ->assertSessionHasErrors('leader_member_id');

    $department = Department::query()->create(departmentTestAttributes([
        'code' => 'DPT-002',
        'name' => 'Family Ministry',
    ]));

    $this->from(route('groups.create'))
        ->post(route('groups.store'), groupTestAttributes($department->id, [
            'code' => 'GRP-002',
            'leader_member_id' => $deletedLeader->id,
        ]))
        ->assertSessionHasErrors('leader_member_id');
});

test('members can be assigned to multiple groups without duplicate assignments', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $department = Department::query()->create(departmentTestAttributes());
    $firstGroup = Group::query()->create(groupTestAttributes($department->id));
    $secondGroup = Group::query()->create(groupTestAttributes($department->id, [
        'code' => 'GRP-002',
        'name' => 'Bible Study',
    ]));
    $member = departmentModuleMember();

    $this->actingAs($user)
        ->post(route('groups.members.assign', $firstGroup), [
            'member_ids' => [$member->id],
        ])->assertRedirect();

    $this->post(route('groups.members.assign', $firstGroup), [
        'member_ids' => [$member->id],
    ])->assertRedirect();

    $this->post(route('groups.members.assign', $secondGroup), [
        'member_ids' => [$member->id],
    ])->assertRedirect();

    $this->assertDatabaseCount('group_member', 2);

    $this->get(route('groups.members.index', $firstGroup))
        ->assertOk()
        ->assertSee('KKKT-DEPT-0001')
        ->assertSee('Neema Mushi')
        ->assertSee('0712345678')
        ->assertSee('active');
});

test('removing a member from a group preserves the member record', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $department = Department::query()->create(departmentTestAttributes());
    $group = Group::query()->create(groupTestAttributes($department->id));
    $member = departmentModuleMember();
    $group->members()->attach($member->id);

    $this->actingAs($user)
        ->delete(route('groups.members.remove', [$group, $member]))
        ->assertRedirect();

    $this->assertModelExists($member);
    $this->assertDatabaseMissing('group_member', [
        'group_id' => $group->id,
        'member_id' => $member->id,
    ]);
});

test('a member cannot be removed through a different group route', function () {
    $user = departmentsGroupsTestUser(['assign group members']);
    $department = Department::query()->create(departmentTestAttributes());
    $firstGroup = Group::query()->create(groupTestAttributes($department->id));
    $secondGroup = Group::query()->create(groupTestAttributes($department->id, [
        'code' => 'GRP-002',
        'name' => 'Bible Study',
    ]));
    $member = departmentModuleMember();
    $secondGroup->members()->attach($member->id);

    $this->actingAs($user)
        ->delete(route('groups.members.remove', [$firstGroup, $member]))
        ->assertNotFound();

    $this->assertDatabaseHas('group_member', [
        'group_id' => $secondGroup->id,
        'member_id' => $member->id,
    ]);
});

test('group deletion is blocked while members are assigned', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $department = Department::query()->create(departmentTestAttributes());
    $group = Group::query()->create(groupTestAttributes($department->id));
    $member = departmentModuleMember();
    $group->members()->attach($member->id);

    $this->actingAs($user)
        ->delete(route('groups.destroy', $group))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertModelExists($group);
    $this->assertModelExists($member);
});

test('department and group text is escaped when rendered', function () {
    $user = departmentsGroupsTestUser(['view departments', 'view groups']);
    $unsafeText = '<script>alert(1)</script>';
    $department = Department::query()->create(departmentTestAttributes([
        'name' => 'Youth Ministry '.$unsafeText,
        'description' => $unsafeText,
    ]));
    $group = Group::query()->create(groupTestAttributes($department->id, [
        'name' => 'Choir '.$unsafeText,
        'description' => $unsafeText,
    ]));

    $this->actingAs($user)
        ->get(route('departments.show', $department))
        ->assertOk()
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);

    $this->get(route('groups.show', $group))
        ->assertOk()
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('soft deleted members cannot be assigned to a group', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $department = Department::query()->create(departmentTestAttributes());
    $group = Group::query()->create(groupTestAttributes($department->id));
    $member = departmentModuleMember();
    $member->delete();

    $this->actingAs($user)
        ->from(route('groups.members.index', $group))
        ->post(route('groups.members.assign', $group), [
            'member_ids' => [$member->id],
        ])
        ->assertSessionHasErrors('member_ids.0');

    $this->assertDatabaseCount('group_member', 0);
});

test('group member assignment rejects empty duplicate and unavailable selections', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $department = Department::query()->create(departmentTestAttributes());
    $group = Group::query()->create(groupTestAttributes($department->id));
    $member = departmentModuleMember();

    $this->actingAs($user)
        ->from(route('groups.members.index', $group))
        ->post(route('groups.members.assign', $group), ['member_ids' => []])
        ->assertSessionHasErrors('member_ids');

    $this->post(route('groups.members.assign', $group), [
        'member_ids' => [$member->id, $member->id],
    ])->assertSessionHasErrors('member_ids.1');

    $this->post(route('groups.members.assign', $group), [
        'member_ids' => [999999],
    ])->assertSessionHasErrors('member_ids.0');

    $this->assertDatabaseCount('group_member', 0);
});

test('unlinked groups can be deleted without deleting their department', function () {
    $user = departmentsGroupsTestUser(departmentsGroupsPermissions());
    $department = Department::query()->create(departmentTestAttributes());
    $group = Group::query()->create(groupTestAttributes($department->id));

    $this->actingAs($user)
        ->delete(route('groups.destroy', $group))
        ->assertRedirect(route('groups.index'));

    $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    $this->assertModelExists($department);
});

test('each protected department and group action requires its dedicated permission', function () {
    $user = departmentsGroupsTestUser(['view departments', 'view groups']);
    $department = Department::query()->create(departmentTestAttributes());
    $group = Group::query()->create(groupTestAttributes($department->id));
    $member = departmentModuleMember();

    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertOk();

    $this->get(route('departments.create'))->assertForbidden();
    $this->get(route('groups.create'))->assertForbidden();
    $this->post(route('groups.store'), groupTestAttributes($department->id))->assertForbidden();
    $this->put(route('departments.update', $department), departmentTestAttributes())->assertForbidden();
    $this->delete(route('departments.destroy', $department))->assertForbidden();
    $this->put(route('groups.update', $group), groupTestAttributes($department->id))->assertForbidden();
    $this->delete(route('groups.destroy', $group))->assertForbidden();
    $this->post(route('groups.members.assign', $group), ['member_ids' => [$member->id]])->assertForbidden();
    $group->members()->attach($member->id);
    $this->delete(route('groups.members.remove', [$group, $member]))->assertForbidden();
});

test('department and group navigation is shown only for authorized users', function () {
    $user = departmentsGroupsTestUser(['view departments']);

    $this->actingAs($user)
        ->get(route('departments.index'))
        ->assertOk()
        ->assertSee(route('departments.index'))
        ->assertDontSee(route('groups.index'));
});

test('role seeding grants proportionate department and group permissions', function () {
    $this->seed(RoleSeeder::class);

    $superAdmin = Role::findByName('Super Admin', 'web');
    $pastor = Role::findByName('Mchungaji', 'web');
    $secretary = Role::findByName('Katibu', 'web');

    expect($superAdmin->hasPermissionTo('delete groups'))->toBeTrue();
    expect($pastor->hasPermissionTo('view groups'))->toBeTrue();
    expect($pastor->hasPermissionTo('create groups'))->toBeFalse();
    expect($secretary->hasPermissionTo('assign group members'))->toBeTrue();
    expect($secretary->hasPermissionTo('delete departments'))->toBeFalse();
    expect($secretary->hasPermissionTo('manage departments'))->toBeTrue();
});
