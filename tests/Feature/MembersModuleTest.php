<?php

use App\Models\Member;
use App\Models\MemberAuditLog;
use App\Models\MemberImport;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberSpreadsheet;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function makeMembersTestUser(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function membersTestAttributes(array $overrides = []): array
{
    return array_merge([
        'membership_number' => 'KKKT-TEST-0001',
        'envelope_number' => 'ENV-0001',
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
    ], $overrides);
}

function membersTestCsv(array $rows): string
{
    $stream = fopen('php://temp', 'r+');

    fputcsv($stream, MemberSpreadsheet::HEADERS, ',', '"', '');

    foreach ($rows as $row) {
        fputcsv(
            $stream,
            array_map(
                static fn (string $header): string => (string) ($row[$header] ?? ''),
                MemberSpreadsheet::HEADERS,
            ),
            ',',
            '"',
            '',
        );
    }

    rewind($stream);
    $contents = stream_get_contents($stream);
    fclose($stream);

    return $contents;
}

test('member routes require permissions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('members.index'))
        ->assertForbidden();

    $this->actingAs(makeMembersTestUser(['view members']))
        ->get(route('members.import.create'))
        ->assertForbidden();
});

test('members can be registered with persistent per-church sequences', function () {
    $user = makeMembersTestUser(['view members', 'create members', 'edit members']);
    Setting::setValue('church_short_name', 'dodoma', 'general');

    $payload = [
        'envelope_number' => 'ENV-1001',
        'first_name' => 'Amina',
        'last_name' => 'Mushi',
        'gender' => 'Female',
        'status' => 'active',
        'membership_number' => 'USER-SUPPLIED-NUMBER',
    ];

    $this->actingAs($user)
        ->post(route('members.store'), $payload)
        ->assertRedirect(route('members.index'))
        ->assertSessionHas('success', __('members.success_created'));

    $firstMember = Member::query()->where('envelope_number', 'ENV-1001')->firstOrFail();
    expect($firstMember->membership_number)->toBe('KKKT-DODOMA-0001');

    $this->actingAs($user)
        ->put(route('members.update', $firstMember), [
            ...$payload,
            'envelope_number' => 'ENV-1001',
            'first_name' => 'Amina Edited',
        ])
        ->assertRedirect(route('members.index'));

    expect($firstMember->refresh()->membership_number)->toBe('KKKT-DODOMA-0001');

    $firstMember->delete();

    Setting::setValue('church_short_name', 'Makuyu', 'general');
    $this->actingAs($user)->post(route('members.store'), [
        ...$payload,
        'envelope_number' => 'ENV-1002',
    ]);

    $this->assertDatabaseHas('members', [
        'envelope_number' => 'ENV-1002',
        'membership_number' => 'KKKT-MAKUYU-0001',
    ]);

    Setting::setValue('church_short_name', 'DODOMA', 'general');
    $this->actingAs($user)->post(route('members.store'), [
        ...$payload,
        'envelope_number' => 'ENV-1003',
    ]);

    $this->assertDatabaseHas('members', [
        'envelope_number' => 'ENV-1003',
        'membership_number' => 'KKKT-DODOMA-0002',
    ]);
});

test('soft deleted membership numbers and envelope numbers remain reserved', function () {
    Setting::setValue('church_short_name', 'ARUSHA', 'general');
    $previousMember = Member::query()->create(membersTestAttributes([
        'membership_number' => 'KKKT-ARUSHA-0001',
        'envelope_number' => 'HISTORIC-ENVELOPE',
    ]));
    $previousMember->delete();

    $registrar = makeMembersTestUser(['create members']);
    $this->actingAs($registrar)
        ->get(route('members.create'))
        ->assertOk()
        ->assertSee('KKKT-ARUSHA-0002');

    $this->post(route('members.store'), [
        'envelope_number' => 'NEW-ENVELOPE',
        'first_name' => 'Neema',
        'last_name' => 'Joseph',
        'gender' => 'Female',
        'status' => 'active',
    ])->assertRedirect(route('members.index'));

    $this->assertDatabaseHas('members', [
        'envelope_number' => 'NEW-ENVELOPE',
        'membership_number' => 'KKKT-ARUSHA-0002',
    ]);

    Storage::fake('local');
    $importer = makeMembersTestUser(['import members']);
    $file = UploadedFile::fake()->createWithContent('members.csv', membersTestCsv([
        [
            'first_name' => 'Duplicate',
            'last_name' => 'Envelope',
            'gender' => 'Male',
            'envelope_number' => 'HISTORIC-ENVELOPE',
        ],
    ]));

    $this->actingAs($importer)
        ->post(route('members.import.store'), ['file' => $file])
        ->assertSessionHasNoErrors();

    $memberImport = MemberImport::query()->firstOrFail();
    expect($memberImport->valid_rows)->toBe(0)
        ->and($memberImport->duplicate_rows)->toBe(1);
});

test('member registration requires a configured church prefix and rejects duplicate envelopes', function () {
    $user = makeMembersTestUser(['create members']);
    $payload = [
        'envelope_number' => 'ENV-2001',
        'first_name' => 'Neema',
        'last_name' => 'Joseph',
        'gender' => 'Female',
        'status' => 'active',
    ];

    $this->actingAs($user)
        ->post(route('members.store'), $payload)
        ->assertSessionHasErrors('church_short_name');

    expect(Member::query()->count())->toBe(0);

    Setting::setValue('church_short_name', 'ARUSHA', 'general');

    $this->actingAs($user)->post(route('members.store'), $payload);
    $this->actingAs($user)
        ->post(route('members.store'), $payload)
        ->assertSessionHasErrors('envelope_number');

    $this->actingAs($user)->post(route('members.store'), [
        ...$payload,
        'envelope_number' => 'ENV-2002',
    ]);

    $this->assertDatabaseHas('members', [
        'envelope_number' => 'ENV-2002',
        'membership_number' => 'KKKT-ARUSHA-0002',
    ]);
});

test('member registration rejects invalid gender and future dates', function () {
    $user = makeMembersTestUser(['create members']);
    Setting::setValue('church_short_name', 'ARUSHA', 'general');

    $this->actingAs($user)
        ->post(route('members.store'), [
            'envelope_number' => 'ENV-3001',
            'first_name' => 'Neema',
            'last_name' => 'Joseph',
            'gender' => 'Other',
            'date_of_birth' => now()->addDay()->toDateString(),
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['gender', 'date_of_birth']);

    expect(Member::query()->count())->toBe(0);
});

test('member listing searches names and filters with stable pagination', function () {
    $user = makeMembersTestUser(['view members']);

    foreach (range(1, 16) as $number) {
        Member::query()->create(membersTestAttributes([
            'membership_number' => sprintf('KKKT-TEST-%04d', $number),
            'envelope_number' => sprintf('ENV-%04d', $number),
            'first_name' => $number === 1 ? 'Amina' : 'Member',
            'last_name' => $number === 1 ? 'Mushi' : 'Test',
            'gender' => $number === 1 ? 'Female' : 'Male',
            'status' => $number === 1 ? 'active' : 'inactive',
        ]));
    }

    $this->actingAs($user)
        ->get(route('members.index', [
            'search' => 'Amina Mushi',
            'gender' => 'Female',
            'status' => 'active',
        ]))
        ->assertOk()
        ->assertSee('Amina')
        ->assertSee('Mushi')
        ->assertDontSee('Member Test')
        ->assertViewHas('members', fn ($members): bool => $members->total() === 1);

    $this->actingAs($user)
        ->get(route('members.index'))
        ->assertViewHas('members', fn ($members): bool => $members->total() === 16
            && $members->perPage() === 15
            && $members->lastPage() === 2);
});

test('system settings require authorization and save the uppercase church prefix and language', function () {
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)
        ->get(route('settings.edit'))
        ->assertForbidden();

    $administrator = makeMembersTestUser(['manage settings']);
    $this->actingAs($administrator)
        ->put(route('settings.update'), [
            'church_short_name' => 'Dodoma North',
            'system_language' => 'sw',
        ])
        ->assertSessionHasErrors('church_short_name');

    $this->actingAs($administrator)
        ->put(route('settings.update'), [
            'church_short_name' => 'Dodoma-North',
            'system_language' => 'sw',
        ])
        ->assertRedirect(route('settings.edit'));

    expect(Setting::getValue('church_short_name'))->toBe('DODOMA-NORTH')
        ->and(Setting::getValue('system_language'))->toBe('sw');

    $this->actingAs($administrator)
        ->get(route('settings.edit'))
        ->assertSee('Kifupisho cha kanisa');
});

test('CSV member imports preview errors, skip duplicates, and assign membership numbers', function () {
    Storage::fake('local');
    $user = makeMembersTestUser(['view members', 'create members', 'import members']);
    Setting::setValue('church_short_name', 'DODOMA', 'general');
    Member::query()->create(membersTestAttributes([
        'membership_number' => 'KKKT-DODOMA-0090',
        'envelope_number' => 'EXISTING-ENVELOPE',
    ]));

    $file = UploadedFile::fake()->createWithContent('members.csv', membersTestCsv([
        [
            'first_name' => 'Asha',
            'last_name' => 'Massawe',
            'gender' => 'female',
            'envelope_number' => 'NEW-ENVELOPE',
        ],
        [
            'first_name' => 'Invalid',
            'last_name' => 'Row',
            'gender' => 'Unknown',
            'date_of_birth' => 'not-a-date',
            'envelope_number' => 'INVALID-ENVELOPE',
            'email' => 'not-an-email',
        ],
        [
            'first_name' => 'Duplicate',
            'last_name' => 'One',
            'gender' => 'Male',
            'envelope_number' => 'DUPLICATE-ENVELOPE',
        ],
        [
            'first_name' => 'Duplicate',
            'last_name' => 'Two',
            'gender' => 'Female',
            'envelope_number' => 'DUPLICATE-ENVELOPE',
        ],
        [
            'first_name' => 'Existing',
            'last_name' => 'Member',
            'gender' => 'Male',
            'envelope_number' => 'EXISTING-ENVELOPE',
        ],
    ]));

    $this->actingAs($user)
        ->post(route('members.import.store'), ['file' => $file])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $memberImport = MemberImport::query()->firstOrFail();
    expect($memberImport->total_rows)->toBe(5)
        ->and($memberImport->valid_rows)->toBe(1)
        ->and($memberImport->invalid_rows)->toBe(4)
        ->and($memberImport->duplicate_rows)->toBe(3);

    $this->actingAs($user)
        ->get(route('members.import.show', $memberImport))
        ->assertOk()
        ->assertSee(__('members.preview_title'))
        ->assertSee(__('members.duplicate_in_upload'));

    $this->actingAs($user)
        ->get(route('members.import.report', $memberImport))
        ->assertDownload('members-rejected-'.$memberImport->uuid.'.csv');

    $this->actingAs($user)
        ->post(route('members.import.confirm', $memberImport))
        ->assertRedirect(route('members.import.show', $memberImport));

    $memberImport->refresh();
    expect($memberImport->status)->toBe('completed')
        ->and($memberImport->imported_rows)->toBe(1)
        ->and($memberImport->failed_rows)->toBe(0)
        ->and($memberImport->skipped_rows)->toBe(4);

    $this->assertDatabaseHas('members', [
        'envelope_number' => 'NEW-ENVELOPE',
        'membership_number' => 'KKKT-DODOMA-0001',
    ]);
    $this->assertDatabaseCount('members', 2);
    Storage::disk('local')->assertExists($memberImport->report_path);
    Storage::disk('local')->assertMissing($memberImport->preview_path);
});

test('Excel member templates and uploads use the generated membership sequence', function () {
    Storage::fake('local');
    $user = makeMembersTestUser(['import members']);
    Setting::setValue('church_short_name', 'ARUSHA', 'general');

    $this->actingAs($user)
        ->get(route('members.import.template', 'xlsx'))
        ->assertDownload('members-template.xlsx');

    $spreadsheet = app(MemberSpreadsheet::class);
    $contents = $spreadsheet->generateXlsx([
        MemberSpreadsheet::HEADERS,
        [
            'Excel',
            '',
            'Member',
            'Male',
            '2001-02-03',
            '',
            '',
            '',
            'XLSX-0001',
            '',
            '',
        ],
    ]);

    $file = UploadedFile::fake()->createWithContent('members.xlsx', $contents);

    $this->actingAs($user)
        ->post(route('members.import.store'), ['file' => $file])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $memberImport = MemberImport::query()->firstOrFail();
    expect($memberImport->valid_rows)->toBe(1);

    $this->actingAs($user)->post(route('members.import.confirm', $memberImport));

    $this->assertDatabaseHas('members', [
        'envelope_number' => 'XLSX-0001',
        'membership_number' => 'KKKT-ARUSHA-0001',
    ]);

    expect(Member::query()
        ->where('envelope_number', 'XLSX-0001')
        ->firstOrFail()
        ->date_of_birth
        ->toDateString())->toBe('2001-02-03');
});

test('rerunning role setup adds member permissions without removing custom permissions', function () {
    $secretary = Role::findOrCreate('Katibu', 'web');
    $existingPermission = Permission::findOrCreate('custom members permission', 'web');
    $secretary->givePermissionTo($existingPermission);

    $this->seed(RoleSeeder::class);

    $secretary->refresh();
    $superAdmin = Role::findByName('Super Admin', 'web');
    expect($secretary->hasPermissionTo('custom members permission'))->toBeTrue()
        ->and($secretary->hasPermissionTo('import members'))->toBeTrue()
        ->and($secretary->hasPermissionTo('export members'))->toBeTrue()
        ->and($superAdmin->hasPermissionTo('restore members'))->toBeTrue();
});

test('authorized users can soft delete one member and the action is audited', function () {
    $user = makeMembersTestUser(['view members', 'delete members']);
    $member = Member::query()->create(membersTestAttributes());

    $this->actingAs($user)
        ->get(route('members.index'))
        ->assertOk()
        ->assertSee(__('members.delete'))
        ->assertSee(__('members.confirm_single_delete', [
            'name' => 'Amina Mushi',
            'membership_number' => 'KKKT-TEST-0001',
        ]));

    $this->delete(route('members.destroy', $member))
        ->assertRedirect(route('members.index'))
        ->assertSessionHas('success', __('members.member_deleted'));

    $this->assertSoftDeleted($member);
    $this->assertDatabaseHas('member_audit_logs', [
        'member_id' => $member->id,
        'membership_number' => 'KKKT-TEST-0001',
        'member_name' => 'Amina Mushi',
        'action' => 'deleted',
        'user_id' => $user->id,
        'actor_name' => $user->name,
    ]);
    expect(MemberAuditLog::query()
        ->where('member_id', $member->id)
        ->where('action', 'deleted')
        ->firstOrFail()
        ->created_at)->not->toBeNull();
});

test('users without delete permission cannot delete a member', function () {
    $user = makeMembersTestUser(['view members']);
    $member = Member::query()->create(membersTestAttributes());

    $this->actingAs($user)
        ->get(route('members.index'))
        ->assertOk()
        ->assertDontSee(__('members.delete'))
        ->assertDontSee('name="member_ids[]"', false);

    $this->get(route('members.deleted.index'))
        ->assertForbidden();

    $this->delete(route('members.destroy', $member))
        ->assertForbidden();

    $this->assertDatabaseHas('members', [
        'id' => $member->id,
        'deleted_at' => null,
    ]);
    $this->assertDatabaseCount('member_audit_logs', 0);
});

test('users without delete permission cannot bulk delete members', function () {
    $user = makeMembersTestUser(['view members']);
    $member = Member::query()->create(membersTestAttributes());

    $this->actingAs($user)
        ->post(route('members.bulk-destroy'), ['member_ids' => [$member->id]])
        ->assertForbidden();

    $this->assertDatabaseHas('members', [
        'id' => $member->id,
        'deleted_at' => null,
    ]);
    $this->assertDatabaseCount('member_audit_logs', 0);
});

test('empty invalid and duplicate bulk delete selections are rejected without changing members', function () {
    $user = makeMembersTestUser(['delete members']);
    $member = Member::query()->create(membersTestAttributes());

    $this->actingAs($user)
        ->post(route('members.bulk-destroy'), ['member_ids' => []])
        ->assertSessionHasErrors('member_ids');

    $this->post(route('members.bulk-destroy'), [
        'member_ids' => [$member->id, 999999],
    ])->assertSessionHasErrors('member_ids.1');

    $this->post(route('members.bulk-destroy'), [
        'member_ids' => [$member->id, $member->id],
    ])->assertSessionHasErrors('member_ids.1');

    $this->assertDatabaseHas('members', [
        'id' => $member->id,
        'deleted_at' => null,
    ]);
    $this->assertDatabaseCount('member_audit_logs', 0);
});

test('authorized users can restore deleted members and audit the restoration', function () {
    $user = makeMembersTestUser(['restore members']);
    $member = Member::query()->create(membersTestAttributes());
    $member->delete();

    $this->actingAs($user)
        ->get(route('members.deleted.index'))
        ->assertOk()
        ->assertSee(__('members.deleted_title'))
        ->assertSee('KKKT-TEST-0001');

    $this->post(route('members.restore', $member))
        ->assertRedirect(route('members.deleted.index'))
        ->assertSessionHas('success', __('members.member_restored'));

    $this->assertDatabaseHas('members', [
        'id' => $member->id,
        'deleted_at' => null,
    ]);
    $this->assertDatabaseHas('member_audit_logs', [
        'member_id' => $member->id,
        'membership_number' => 'KKKT-TEST-0001',
        'action' => 'restored',
        'user_id' => $user->id,
        'actor_name' => $user->name,
    ]);

    $this->post(route('members.restore', $member))
        ->assertNotFound();

    $this->assertDatabaseCount('member_audit_logs', 1);
});

test('users without restore permission cannot restore deleted members', function () {
    $user = makeMembersTestUser(['delete members']);
    $member = Member::query()->create(membersTestAttributes());
    $member->delete();

    $this->actingAs($user)
        ->post(route('members.restore', $member))
        ->assertForbidden();

    $this->post(route('members.deleted.bulk-restore'), [
        'member_ids' => [$member->id],
    ])->assertForbidden();

    $this->assertSoftDeleted($member);
    $this->assertDatabaseCount('member_audit_logs', 0);
});

test('deleted members stay out of the members list and reappear after restoration', function () {
    $user = makeMembersTestUser(['view members', 'delete members', 'restore members']);
    $member = Member::query()->create(membersTestAttributes());
    $this->actingAs($user)->delete(route('members.destroy', $member));

    $this->get(route('members.index'))
        ->assertOk()
        ->assertDontSee('KKKT-TEST-0001');

    $this->get(route('members.deleted.index'))
        ->assertOk()
        ->assertSee('KKKT-TEST-0001');

    $this->post(route('members.restore', $member));

    $this->get(route('members.index'))
        ->assertOk()
        ->assertSee('KKKT-TEST-0001');

    $this->get(route('members.deleted.index'))
        ->assertOk()
        ->assertSee(__('members.no_deleted_members'));
});

test('authorized users can bulk restore deleted members', function () {
    $user = makeMembersTestUser(['restore members']);
    $members = collect([
        Member::query()->create(membersTestAttributes([
            'membership_number' => 'KKKT-TEST-0001',
            'envelope_number' => 'ENV-0001',
        ])),
        Member::query()->create(membersTestAttributes([
            'membership_number' => 'KKKT-TEST-0002',
            'envelope_number' => 'ENV-0002',
            'first_name' => 'Neema',
        ])),
    ]);
    $members->each->delete();

    $this->actingAs($user)
        ->post(route('members.deleted.bulk-restore'), [
            'member_ids' => $members->pluck('id')->all(),
        ])
        ->assertRedirect(route('members.deleted.index'))
        ->assertSessionHas('success', __('members.members_restored', ['count' => 2]));

    foreach ($members as $member) {
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'deleted_at' => null,
        ]);
    }

    $this->assertDatabaseCount('member_audit_logs', 2);
});

test('invalid bulk restore selections do not restore any members', function () {
    $user = makeMembersTestUser(['restore members']);
    $activeMember = Member::query()->create(membersTestAttributes([
        'membership_number' => 'KKKT-TEST-0001',
        'envelope_number' => 'ENV-0001',
    ]));
    $deletedMember = Member::query()->create(membersTestAttributes([
        'membership_number' => 'KKKT-TEST-0002',
        'envelope_number' => 'ENV-0002',
    ]));
    $deletedMember->delete();

    $this->actingAs($user)
        ->post(route('members.deleted.bulk-restore'), ['member_ids' => []])
        ->assertSessionHasErrors('member_ids');

    $this->post(route('members.deleted.bulk-restore'), [
        'member_ids' => [$deletedMember->id, 999999],
    ])->assertSessionHasErrors('member_ids.1');

    $this->post(route('members.deleted.bulk-restore'), [
        'member_ids' => [$deletedMember->id, $activeMember->id],
    ])->assertSessionHasErrors('member_ids.1');

    $this->assertSoftDeleted($deletedMember);
    $this->assertDatabaseHas('members', [
        'id' => $activeMember->id,
        'deleted_at' => null,
    ]);
    $this->assertDatabaseCount('member_audit_logs', 0);
});
