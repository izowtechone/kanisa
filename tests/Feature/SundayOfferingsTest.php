<?php

use App\Jobs\SendFinancialSms;
use App\Models\Community;
use App\Models\FinanceCategory;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function sundayOfferingTestUser(array $permissions = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function sundayOfferingTestMember(array $overrides = []): Member
{
    return Member::query()->create(array_merge([
        'membership_number' => 'KKKT-SUNDAY-0001',
        'envelope_number' => '00017',
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
        'sms_opt_in' => false,
        'preferred_language' => null,
    ], $overrides));
}

function sundayOfferingTestCategory(User $creator): FinanceCategory
{
    return FinanceCategory::query()->create([
        'type' => 'offering',
        'code' => 'SUNDAY-GENERAL',
        'name' => 'Sunday General',
        'created_by' => $creator->id,
    ]);
}

function sundayOfferingTestPayload(FinanceCategory $category, Member $member): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'member_id' => $member->id,
        'category_id' => $category->id,
        'amount' => '125.00',
        'payment_method' => 'cash',
        'transaction_date' => now()->toDateString(),
        'description' => 'Sunday service offering',
    ];
}

it('denies Sunday Offering creation without permission and leaves the ledger unchanged', function () {
    Setting::setValue('system_language', 'en');
    $user = sundayOfferingTestUser();
    $category = sundayOfferingTestCategory($user);
    $member = sundayOfferingTestMember();

    $this->actingAs($user)
        ->post(route('finance.sunday-offerings.store'), sundayOfferingTestPayload($category, $member))
        ->assertForbidden();

    $this->assertDatabaseCount('financial_transactions', 0);
});

it('loads the Sunday Offering page when no Sunday Offering transactions exist', function () {
    Setting::setValue('system_language', 'en');
    $user = sundayOfferingTestUser(['view Sunday offerings']);

    $this->actingAs($user)
        ->get(route('finance.sunday-offerings.index'))
        ->assertOk()
        ->assertViewHas('transactions', fn ($transactions): bool => $transactions->total() === 0
            && $transactions->isEmpty());
});

it('rejects inactive members when recording a Sunday Offering', function () {
    Setting::setValue('system_language', 'en');
    $user = sundayOfferingTestUser(['create Sunday offerings']);
    $category = sundayOfferingTestCategory($user);
    $member = sundayOfferingTestMember([
        'membership_number' => 'KKKT-SUNDAY-0002',
        'envelope_number' => '00018',
        'status' => 'inactive',
    ]);

    $this->actingAs($user)
        ->from(route('finance.sunday-offerings.create'))
        ->post(route('finance.sunday-offerings.store'), sundayOfferingTestPayload($category, $member))
        ->assertSessionHasErrors([
            'member_id' => __('finance.active_member_required'),
        ]);

    $this->assertDatabaseCount('financial_transactions', 0);
});

it('counts and lists Sunday Offerings without including ordinary offerings', function () {
    Setting::setValue('system_language', 'en');
    $user = sundayOfferingTestUser([
        'view finances',
        'view Sunday offerings',
        'manage offerings',
        'create Sunday offerings',
    ]);
    $category = sundayOfferingTestCategory($user);

    $this->actingAs($user)
        ->post(route('finance.transactions.store', ['type' => 'offering']), [
            'idempotency_key' => (string) Str::uuid(),
            'category_id' => $category->id,
            'amount' => '300.00',
            'payment_method' => 'cash',
            'transaction_date' => now()->toDateString(),
            'description' => 'General offering',
        ])
        ->assertRedirect();

    $ordinaryOffering = FinancialTransaction::query()->firstOrFail();

    expect($ordinaryOffering->source)->toBe(FinancialTransaction::SOURCE_FINANCE);

    $member = sundayOfferingTestMember();

    $this->post(
        route('finance.sunday-offerings.store'),
        sundayOfferingTestPayload($category, $member),
    )->assertRedirect();

    $sundayOffering = FinancialTransaction::query()
        ->where('source', FinancialTransaction::SOURCE_SUNDAY_OFFERING)
        ->firstOrFail();

    $this->get(route('finance.sunday-offerings.index'))
        ->assertOk()
        ->assertSee($sundayOffering->receipt_number)
        ->assertDontSee($ordinaryOffering->receipt_number)
        ->assertViewHas('transactions', fn ($transactions): bool => $transactions->total() === 1
            && $transactions->contains('id', $sundayOffering->id));

    $this->assertDatabaseCount('financial_transactions', 2);
});

it('records searchable pending offerings and queues consented SMS only after separate approval', function () {
    Setting::setValue('system_language', 'en');
    Setting::setValue('church_name', 'KKKT');
    FinancialSetting::setValue('swala_enabled', '1');
    $creator = sundayOfferingTestUser(['create Sunday offerings', 'view Sunday offerings']);
    $approver = sundayOfferingTestUser(['approve financial transactions']);
    $category = sundayOfferingTestCategory($creator);
    $community = Community::factory()->create(['code' => 'JUM-401']);
    $member = sundayOfferingTestMember([
        'community_id' => $community->id,
        'sms_opt_in' => true,
        'preferred_language' => 'sw',
    ]);
    Queue::fake([SendFinancialSms::class]);

    $this->actingAs($creator)
        ->post(route('finance.sunday-offerings.store'), sundayOfferingTestPayload($category, $member))
        ->assertRedirect();

    $transaction = FinancialTransaction::query()->firstOrFail();

    expect($transaction->source)->toBe(FinancialTransaction::SOURCE_SUNDAY_OFFERING)
        ->and($transaction->status)->toBe('pending')
        ->and($transaction->member_number)->toBe($member->membership_number)
        ->and($transaction->envelope_code)->toBe('00017')
        ->and($transaction->member_name)->toBe('Neema Mushi');

    Queue::assertNotPushed(SendFinancialSms::class);

    $this->get(route('finance.sunday-offerings.index', [
        'search' => '00017',
        'status' => 'pending',
    ]))
        ->assertOk()
        ->assertSee($transaction->receipt_number)
        ->assertSee('Neema Mushi');

    $this->actingAs($approver)
        ->from(route('finance.sunday-offerings.show', $transaction))
        ->post(route('finance.transactions.approve', $transaction))
        ->assertRedirect();

    $smsLog = FinancialSmsLog::query()
        ->where('financial_transaction_id', $transaction->id)
        ->where('notification_type', 'sunday_offering')
        ->firstOrFail();

    expect($smsLog->status)->toBe('queued')
        ->and($smsLog->message)->toContain('Habari Neema');

    Queue::assertPushed(
        SendFinancialSms::class,
        fn (SendFinancialSms $job): bool => $job->smsLogId === $smsLog->id,
    );
});

it('does not queue Sunday Offering SMS for members without recorded consent', function () {
    Setting::setValue('system_language', 'en');
    FinancialSetting::setValue('swala_enabled', '1');
    $creator = sundayOfferingTestUser(['create Sunday offerings']);
    $approver = sundayOfferingTestUser(['approve financial transactions']);
    $category = sundayOfferingTestCategory($creator);
    $member = sundayOfferingTestMember();
    Queue::fake([SendFinancialSms::class]);

    $this->actingAs($creator)
        ->post(route('finance.sunday-offerings.store'), sundayOfferingTestPayload($category, $member))
        ->assertRedirect();

    $transaction = FinancialTransaction::query()->firstOrFail();

    $this->actingAs($approver)
        ->post(route('finance.transactions.approve', $transaction))
        ->assertRedirect();

    $smsLog = FinancialSmsLog::query()
        ->where('financial_transaction_id', $transaction->id)
        ->where('notification_type', 'sunday_offering')
        ->firstOrFail();

    expect($smsLog->status)->toBe('cancelled')
        ->and($smsLog->error_message)->toBe(__('finance.sms_sunday_consent_required'));

    Queue::assertNotPushed(SendFinancialSms::class);
});

it('searches active members without losing envelope zeroes or exposing deleted members', function () {
    Setting::setValue('system_language', 'en');
    $user = sundayOfferingTestUser(['create Sunday offerings']);
    $member = sundayOfferingTestMember();
    $deletedMember = sundayOfferingTestMember([
        'membership_number' => 'KKKT-SUNDAY-0003',
        'envelope_number' => '00019',
        'first_name' => 'Deleted',
    ]);
    $deletedMember->delete();

    $this->actingAs($user)
        ->getJson(route('finance.sunday-offerings.members.search', ['search' => '00017']))
        ->assertOk()
        ->assertJsonPath('data.0.envelope_number', '00017')
        ->assertJsonMissing(['name' => 'Deleted Mushi']);

    expect($member->fresh())->not->toBeNull();
});
