<?php

use App\Jobs\SendFinancialSms;
use App\Models\Community;
use App\Models\FinanceCategory;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionAudit;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\SmsSegmentEstimator;
use App\Services\SwalaSmsService;
use Illuminate\Support\Facades\Http;
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

it('automatically approves Sunday Offerings and queues consented SMS after saving', function () {
    Setting::setValue('system_language', 'en');
    Setting::setValue('church_name', 'KKKT');
    FinancialSetting::setValue('swala_enabled', '1');
    $creator = sundayOfferingTestUser(['create Sunday offerings', 'view Sunday offerings']);
    $category = sundayOfferingTestCategory($creator);
    $community = Community::factory()->create(['code' => 'JUM-401']);
    $member = sundayOfferingTestMember([
        'community_id' => $community->id,
        'sms_opt_in' => true,
        'preferred_language' => 'sw',
    ]);
    Queue::fake([SendFinancialSms::class]);

    $this->actingAs($creator)
        ->post(route('finance.sunday-offerings.store'), [
            ...sundayOfferingTestPayload($category, $member),
            'status' => 'voided',
            'approved_by' => null,
        ])
        ->assertRedirect(route('finance.sunday-offerings.create'))
        ->assertSessionHas('sunday_offering_saved');

    $transaction = FinancialTransaction::query()->firstOrFail();

    expect($transaction->source)->toBe(FinancialTransaction::SOURCE_SUNDAY_OFFERING)
        ->and($transaction->status)->toBe('approved')
        ->and($transaction->created_by)->toBe($creator->id)
        ->and($transaction->approved_by)->toBe($creator->id)
        ->and($transaction->approved_at)->not->toBeNull()
        ->and($transaction->payment_method)->toBe('cash')
        ->and($transaction->transaction_date->toDateString())->toBe(now()->toDateString())
        ->and($transaction->reference_number)->toBeNull()
        ->and($transaction->member_number)->toBe($member->membership_number)
        ->and($transaction->envelope_code)->toBe('00017')
        ->and($transaction->member_name)->toBe('Neema Mushi');

    $smsLog = FinancialSmsLog::query()
        ->where('financial_transaction_id', $transaction->id)
        ->where('notification_type', 'sunday_offering')
        ->firstOrFail();

    expect($smsLog->status)->toBe('queued')
        ->and($smsLog->phone)->toBe($member->phone)
        ->and($smsLog->message)->toContain('Habari Neema')
        ->and($smsLog->message)->toContain('imepokelewa')
        ->and($smsLog->message)->toContain('125.00')
        ->and($smsLog->message)->toContain('KKKT');

    Queue::assertPushed(
        SendFinancialSms::class,
        fn (SendFinancialSms $job): bool => $job->smsLogId === $smsLog->id,
    );

    $this->get(route('finance.sunday-offerings.create'))
        ->assertOk()
        ->assertDontSee('value="125.00"')
        ->assertDontSee('Neema Mushi');

    $this->get(route('finance.sunday-offerings.index', [
        'search' => '00017',
        'status' => 'approved',
    ]))
        ->assertOk()
        ->assertSee($transaction->receipt_number)
        ->assertSee('Neema Mushi');

    expect(FinancialTransactionAudit::query()
        ->where('financial_transaction_id', $transaction->id)
        ->where('action', 'approved')
        ->where('user_id', $creator->id)
        ->exists())->toBeTrue()
        ->and($smsLog->status)->toBe('queued')
        ->and($smsLog->message)->toContain('Habari Neema');

    Queue::assertPushed(SendFinancialSms::class, 1);
});

it('does not queue Sunday Offering SMS for members without recorded consent', function () {
    Setting::setValue('system_language', 'en');
    FinancialSetting::setValue('swala_enabled', '1');
    $creator = sundayOfferingTestUser(['create Sunday offerings']);
    $category = sundayOfferingTestCategory($creator);
    $member = sundayOfferingTestMember();
    Queue::fake([SendFinancialSms::class]);

    $this->actingAs($creator)
        ->post(route('finance.sunday-offerings.store'), sundayOfferingTestPayload($category, $member))
        ->assertRedirect();

    $transaction = FinancialTransaction::query()->firstOrFail();

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
        ->assertJsonPath('data.0.id', $member->id)
        ->assertJsonMissing(['name' => 'Deleted Mushi'])
        ->assertJsonMissingPath('data.0.phone')
        ->assertJsonMissingPath('data.0.community_name');

    $this->getJson(route('finance.sunday-offerings.members.search', ['search' => '071234']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $member->id);

    $this->getJson(route('finance.sunday-offerings.members.search', ['search' => 'N']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('search');

    expect($member->fresh())->not->toBeNull();

    $unauthorized = sundayOfferingTestUser(['view Sunday offerings']);
    $this->actingAs($unauthorized)
        ->getJson(route('finance.sunday-offerings.members.search', ['search' => '00017']))
        ->assertForbidden();
});

it('finds an E.164 member phone by a local phone prefix', function () {
    $user = sundayOfferingTestUser(['create Sunday offerings']);
    $member = sundayOfferingTestMember(['phone' => '+255712345678']);

    $this->actingAs($user)
        ->getJson(route('finance.sunday-offerings.members.search', ['search' => '071234']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $member->id);
});

it('omits payment, date, and reference fields and denies access without create permission', function () {
    $user = sundayOfferingTestUser(['create Sunday offerings']);

    $this->actingAs($user)
        ->get(route('finance.sunday-offerings.create'))
        ->assertOk()
        ->assertDontSee('name="payment_method"', false)
        ->assertDontSee('name="transaction_date"', false)
        ->assertDontSee('name="reference_number"', false);

    $unauthorized = sundayOfferingTestUser();
    $this->actingAs($unauthorized)
        ->get(route('finance.sunday-offerings.create'))
        ->assertForbidden();
});

it('rejects client supplied payment method date and reference values', function () {
    $user = sundayOfferingTestUser(['create Sunday offerings']);
    $category = sundayOfferingTestCategory($user);
    $member = sundayOfferingTestMember();

    $this->actingAs($user)
        ->from(route('finance.sunday-offerings.create'))
        ->post(route('finance.sunday-offerings.store'), [
            ...sundayOfferingTestPayload($category, $member),
            'payment_method' => 'bank_transfer',
            'transaction_date' => '2000-01-01',
            'reference_number' => 'CLIENT-REFERENCE',
        ])
        ->assertSessionHasErrors(['payment_method', 'transaction_date', 'reference_number']);

    $this->assertDatabaseCount('financial_transactions', 0);
});

it('uses the configured local date and preserves the generated creation timestamp', function () {
    config(['app.timezone' => 'Africa/Dar_es_Salaam']);
    $this->travelTo('2026-11-04 23:30:00');
    $user = sundayOfferingTestUser(['create Sunday offerings']);
    $category = sundayOfferingTestCategory($user);
    $member = sundayOfferingTestMember();

    $this->actingAs($user)
        ->post(route('finance.sunday-offerings.store'), sundayOfferingTestPayload($category, $member))
        ->assertRedirect(route('finance.sunday-offerings.create'));

    $transaction = FinancialTransaction::query()->firstOrFail();

    expect($transaction->transaction_date->toDateString())->toBe('2026-11-04')
        ->and($transaction->created_at->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i:s'))->toBe('2026-11-04 23:30:00');
});

it('keeps the transaction when SMS is not configured and reports the SMS failure', function () {
    $user = sundayOfferingTestUser(['create Sunday offerings']);
    $category = sundayOfferingTestCategory($user);
    $member = sundayOfferingTestMember(['sms_opt_in' => true]);
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '0');
    Queue::fake([SendFinancialSms::class]);

    $this->actingAs($user)
        ->post(route('finance.sunday-offerings.store'), sundayOfferingTestPayload($category, $member))
        ->assertRedirect(route('finance.sunday-offerings.create'))
        ->assertSessionHas('sunday_offering_sms_status', 'not_configured');

    $transaction = FinancialTransaction::query()->firstOrFail();
    $smsLog = FinancialSmsLog::query()->where('financial_transaction_id', $transaction->id)->firstOrFail();

    expect($transaction->payment_method)->toBe('cash')
        ->and($smsLog->status)->toBe('not_configured');
    $this->assertDatabaseCount('financial_transactions', 1);
    Queue::assertNotPushed(SendFinancialSms::class);
});

it('keeps a saved transaction when the SMS provider rejects the confirmation', function () {
    $user = sundayOfferingTestUser(['create Sunday offerings']);
    $category = sundayOfferingTestCategory($user);
    $member = sundayOfferingTestMember(['sms_opt_in' => true]);
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '0');
    FinancialSetting::setValue('swala_api_key', 'swl_live_feature_test_key');
    FinancialSetting::setValue('swala_sender_id', 'KKKT');
    Queue::fake([SendFinancialSms::class]);

    $this->actingAs($user)
        ->post(route('finance.sunday-offerings.store'), sundayOfferingTestPayload($category, $member))
        ->assertRedirect(route('finance.sunday-offerings.create'))
        ->assertSessionHas('sunday_offering_sms_status', 'queued');

    $transaction = FinancialTransaction::query()->firstOrFail();
    $smsLog = FinancialSmsLog::query()->where('financial_transaction_id', $transaction->id)->firstOrFail();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.swalasms.com/v2/sms/send-bulk' => Http::response([
            'success' => false,
            'message' => 'Invalid recipient',
            'error_code' => 'INVALID_RECIPIENT',
            'acceptance_state' => 'DEFINITELY_NOT_ACCEPTED',
        ], 422),
    ]);

    (new SendFinancialSms($smsLog->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($smsLog->fresh()->status)->toBe('failed')
        ->and($transaction->fresh()->amount)->toBe('125.00');
    $this->assertDatabaseCount('financial_transactions', 1);
});

it('does not duplicate transactions or SMS when a successful submission is retried', function () {
    $user = sundayOfferingTestUser(['create Sunday offerings']);
    $category = sundayOfferingTestCategory($user);
    $member = sundayOfferingTestMember(['sms_opt_in' => true]);
    FinancialSetting::setValue('swala_enabled', '1');
    Queue::fake([SendFinancialSms::class]);
    $payload = sundayOfferingTestPayload($category, $member);

    $this->actingAs($user)
        ->post(route('finance.sunday-offerings.store'), $payload)
        ->assertRedirect(route('finance.sunday-offerings.create'));
    $this->post(route('finance.sunday-offerings.store'), $payload)
        ->assertRedirect(route('finance.sunday-offerings.create'));

    $transaction = FinancialTransaction::query()->firstOrFail();

    $this->assertDatabaseCount('financial_transactions', 1);
    $this->assertDatabaseCount('financial_sms_logs', 1);
    Queue::assertPushed(SendFinancialSms::class, 1);
    expect($transaction->smsLog->phone)->toBe($member->phone);
});
