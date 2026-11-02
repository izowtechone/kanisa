<?php

use App\Jobs\SendFinancialSms;
use App\Models\FinanceCategory;
use App\Models\FinancialCampaign;
use App\Models\FinancialImport;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsAttempt;
use App\Models\FinancialSmsLog;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionAudit;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberSpreadsheet;
use App\Services\SwalaSmsService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function financeModuleTestUser(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function financeModuleTestCategory(User $creator, string $type = 'offering'): FinanceCategory
{
    return FinanceCategory::query()->create([
        'type' => $type,
        'code' => strtoupper($type).'-GENERAL',
        'name' => ucfirst(str_replace('_', ' ', $type)).' General',
        'created_by' => $creator->id,
    ]);
}

function financeModuleTestMember(array $overrides = []): Member
{
    return Member::query()->create(array_merge([
        'membership_number' => 'KKKT-FIN-0001',
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
    ], $overrides));
}

function financeModuleEntryPayload(int $categoryId, array $overrides = []): array
{
    return array_merge([
        'idempotency_key' => (string) Str::uuid(),
        'category_id' => $categoryId,
        'amount' => '125.00',
        'payment_method' => 'cash',
        'transaction_date' => '2026-11-02',
        'description' => 'Sunday offering',
    ], $overrides);
}

function financeModuleSmsSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'swala_enabled' => true,
        'swala_test_mode' => false,
        'sms_language' => 'sw',
        'swala_sender_id' => 'KKKT',
        'swala_api_key' => 'swl_live_abc123',
        'sms_auto_offering' => true,
        'sms_auto_contribution' => true,
        'sms_auto_other_income' => false,
        'sms_template_offering_en' => __('finance.sms_template_offering', [], 'en'),
        'sms_template_offering_sw' => __('finance.sms_template_offering', [], 'sw'),
        'sms_template_contribution_en' => __('finance.sms_template_contribution', [], 'en'),
        'sms_template_contribution_sw' => __('finance.sms_template_contribution', [], 'sw'),
        'sms_template_sunday_offering_en' => __('finance.sms_template_sunday_offering', [], 'en'),
        'sms_template_sunday_offering_sw' => __('finance.sms_template_sunday_offering', [], 'sw'),
    ], $overrides);
}

test('finance transaction creation is authorized and rejects unpermitted users', function () {
    $user = financeModuleTestUser(['view finances']);
    $category = financeModuleTestCategory($user);

    $this->actingAs($user)
        ->post(route('finance.transactions.store', ['type' => 'offering']), financeModuleEntryPayload($category->id))
        ->assertForbidden();

    $this->assertDatabaseCount('financial_transactions', 0);
});

test('financial entries are numbered sequentially and idempotent submissions do not duplicate them', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $user = financeModuleTestUser(['view finances', 'manage offerings']);
    $category = financeModuleTestCategory($user);
    $payload = financeModuleEntryPayload($category->id);

    $this->actingAs($user)
        ->post(route('finance.transactions.store', ['type' => 'offering']), $payload)
        ->assertRedirect();

    $transaction = FinancialTransaction::query()->firstOrFail();

    expect($transaction->transaction_number)->toBe('KKKT-TXN-2026-000001')
        ->and($transaction->status)->toBe('pending');

    $this->post(route('finance.transactions.store', ['type' => 'offering']), $payload)
        ->assertRedirect(route('finance.transactions.show', $transaction));

    $this->assertDatabaseCount('financial_transactions', 1);
    $this->assertDatabaseCount('financial_transaction_audits', 1);

    $this->from(route('finance.transactions.create', ['type' => 'offering']))
        ->post(route('finance.transactions.store', ['type' => 'offering']), [
            ...$payload,
            'amount' => '200.00',
        ])
        ->assertSessionHasErrors('idempotency_key');

    $this->post(route('finance.transactions.store', ['type' => 'offering']), financeModuleEntryPayload(
        $category->id,
        ['amount' => '25.00'],
    ))->assertRedirect();

    expect(FinancialTransaction::query()->orderBy('id')->value('transaction_number'))
        ->toBe('KKKT-TXN-2026-000001');
    expect(FinancialTransaction::query()->orderByDesc('id')->value('transaction_number'))
        ->toBe('KKKT-TXN-2026-000002');
    $this->assertDatabaseCount('financial_transactions', 2);
});

test('only a different authorized user can approve an entry and approved reversals net to zero', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $creator = financeModuleTestUser(['view finances', 'manage offerings', 'approve financial transactions']);
    $approver = financeModuleTestUser(['view finances', 'approve financial transactions']);
    $reverser = financeModuleTestUser(['view finances', 'reverse financial transactions']);
    $reversalApprover = financeModuleTestUser(['view finances', 'approve financial transactions', 'view financial reports']);
    $category = financeModuleTestCategory($creator);
    $payload = financeModuleEntryPayload($category->id);

    $this->actingAs($creator)
        ->post(route('finance.transactions.store', ['type' => 'offering']), $payload)
        ->assertRedirect();
    $transaction = FinancialTransaction::query()->firstOrFail();

    $this->post(route('finance.transactions.approve', $transaction))
        ->assertSessionHasErrors('transaction');
    expect($transaction->fresh()->status)->toBe('pending');

    $this->actingAs($approver)
        ->post(route('finance.transactions.approve', $transaction))
        ->assertRedirect();
    expect($transaction->fresh()->status)->toBe('approved');

    $this->actingAs($reverser)
        ->post(route('finance.transactions.reverse', $transaction), ['reason' => 'Recorded against the wrong account'])
        ->assertRedirect();
    $reversal = FinancialTransaction::query()
        ->where('reversal_of_id', $transaction->id)
        ->firstOrFail();
    expect($reversal->status)->toBe('pending')
        ->and($reversal->ledger_sign)->toBe(-1);
    expect(FinancialTransactionAudit::query()
        ->where('financial_transaction_id', $transaction->id)
        ->where('action', 'reversal_requested')
        ->firstOrFail()
        ->details['reason'])->toBe('Recorded against the wrong account');

    $this->actingAs($reversalApprover)
        ->post(route('finance.transactions.approve', $reversal))
        ->assertRedirect();

    expect($transaction->fresh()->status)->toBe('reversed')
        ->and($reversal->fresh()->status)->toBe('approved');

    $this->get(route('finance.reports.index', [
        'from' => '2026-11-02',
        'to' => '2026-11-02',
    ]))->assertOk()->assertViewHas('net', fn (string $net): bool => (float) $net === 0.0);
});

test('envelope lookup preserves leading zeroes and excludes soft deleted members', function () {
    $user = financeModuleTestUser(['view finances', 'record envelope offerings']);
    $member = financeModuleTestMember();
    $deletedMember = financeModuleTestMember([
        'membership_number' => 'KKKT-FIN-0002',
        'envelope_number' => '00018',
    ]);
    $deletedMember->delete();

    $this->actingAs($user)
        ->getJson(route('finance.envelopes.lookup', ['envelope_code' => '00017']))
        ->assertOk()
        ->assertJsonPath('membership_number', 'KKKT-FIN-0001');

    $this->getJson(route('finance.envelopes.lookup', ['envelope_code' => '00018']))
        ->assertNotFound();

    $this->assertModelExists($member);
});

test('CSV import previews duplicate and invalid rows then imports only valid envelopes', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $user = financeModuleTestUser(['import envelope offerings']);
    $category = financeModuleTestCategory($user);
    financeModuleTestMember();
    $csv = implode("\n", [
        'envelope_code,amount,payment_method,transaction_date,reference_number,description,notes',
        '00017,250.00,cash,2026-11-02,REF-1,Sunday offering,First row',
        '00017,300.00,cash,2026-11-02,REF-2,Duplicate envelope,Second row',
        '99999,100.00,cash,2026-11-02,REF-3,Unknown envelope,Third row',
    ]);

    $this->actingAs($user)
        ->post(route('finance.imports.store'), [
            'category_id' => $category->id,
            'file' => UploadedFile::fake()->createWithContent('offerings.csv', $csv),
        ])
        ->assertRedirect();

    $financialImport = FinancialImport::query()->firstOrFail();
    expect($financialImport->total_rows)->toBe(3)
        ->and($financialImport->valid_rows)->toBe(1)
        ->and($financialImport->invalid_rows)->toBe(2);

    $this->post(route('finance.imports.confirm', $financialImport))
        ->assertRedirect(route('finance.imports.show', $financialImport));

    expect($financialImport->fresh()->status)->toBe('completed');
    $this->assertDatabaseCount('financial_transactions', 1);
    $this->assertDatabaseHas('financial_transactions', [
        'envelope_code' => '00017',
        'amount' => '250.00',
        'member_id' => Member::query()->where('envelope_number', '00017')->value('id'),
    ]);
});

test('XLSX import converts Excel date serials before row validation', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $user = financeModuleTestUser(['import envelope offerings']);
    $category = financeModuleTestCategory($user);
    financeModuleTestMember();
    $xlsx = app(MemberSpreadsheet::class)->generateXlsx([
        ['envelope_code', 'amount', 'payment_method', 'transaction_date'],
        ['00017', '150.00', 'cash', '46328'],
    ]);

    $this->actingAs($user)
        ->post(route('finance.imports.store'), [
            'category_id' => $category->id,
            'file' => UploadedFile::fake()->createWithContent('offerings.xlsx', $xlsx),
        ])
        ->assertRedirect();

    $financialImport = FinancialImport::query()->firstOrFail();
    expect($financialImport->rows[0]['data']['transaction_date'])->toBe('2026-11-02')
        ->and($financialImport->rows[0]['is_valid'])->toBeTrue();
});

test('receipts are unavailable until an entry is approved and after it is reversed', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $creator = financeModuleTestUser(['view finances', 'manage offerings']);
    $approver = financeModuleTestUser(['view finances', 'approve financial transactions']);
    $reverser = financeModuleTestUser(['view finances', 'reverse financial transactions']);
    $reversalApprover = financeModuleTestUser(['view finances', 'approve financial transactions']);
    $receiptViewer = financeModuleTestUser(['view receipts']);
    $category = financeModuleTestCategory($creator);

    $this->actingAs($creator)
        ->post(route('finance.transactions.store', ['type' => 'offering']), financeModuleEntryPayload($category->id))
        ->assertRedirect();
    $transaction = FinancialTransaction::query()->firstOrFail();

    $this->actingAs($receiptViewer)
        ->get(route('finance.receipts.show', $transaction->receipt_number))
        ->assertNotFound();

    $this->actingAs($approver)
        ->post(route('finance.transactions.approve', $transaction))
        ->assertRedirect();
    $this->actingAs($receiptViewer)
        ->get(route('finance.receipts.show', $transaction->receipt_number))
        ->assertOk();

    $this->actingAs($reverser)
        ->post(route('finance.transactions.reverse', $transaction), ['reason' => 'Entry was recorded in error'])
        ->assertRedirect();
    $reversal = FinancialTransaction::query()
        ->where('reversal_of_id', $transaction->id)
        ->firstOrFail();
    $this->actingAs($reversalApprover)
        ->post(route('finance.transactions.approve', $reversal))
        ->assertRedirect();

    $this->actingAs($receiptViewer)
        ->get(route('finance.receipts.show', $transaction->receipt_number))
        ->assertNotFound();
});

test('SwalaSMS test mode records a test attempt without sending an SMS', function () {
    $user = User::factory()->create();
    $category = financeModuleTestCategory($user);
    $transaction = FinancialTransaction::query()->create([
        'transaction_number' => 'KKKT-TXN-2026-000001',
        'receipt_number' => 'KKKT-RCPT-2026-000001',
        'idempotency_key' => (string) Str::uuid(),
        'payload_hash' => hash('sha256', 'sms-test'),
        'type' => 'offering',
        'category_id' => $category->id,
        'member_id' => financeModuleTestMember()->id,
        'member_number' => 'KKKT-FIN-0001',
        'member_name' => 'Neema Mushi',
        'created_by' => $user->id,
        'amount' => '125.00',
        'currency' => 'TZS',
        'payment_method' => 'cash',
        'status' => 'approved',
        'transaction_date' => '2026-11-02',
    ]);
    $smsLog = FinancialSmsLog::query()->create([
        'financial_transaction_id' => $transaction->id,
        'phone' => '0712345678',
        'is_test' => true,
        'status' => 'queued',
    ]);

    (new SendFinancialSms($smsLog->id))->handle(app(SwalaSmsService::class));

    expect($smsLog->fresh()->status)->toBe('test_only')
        ->and(FinancialSmsAttempt::query()->firstOrFail()->status)->toBe('test_only');
});

test('financial settings encrypt SwalaSMS credentials at rest', function () {
    FinancialSetting::setValue('swala_webhook_secret', 'sensitive-swala-secret');

    expect(FinancialSetting::getValue('swala_webhook_secret'))->toBe('sensitive-swala-secret');

    $this->assertDatabaseMissing('financial_settings', [
        'value' => 'sensitive-swala-secret',
    ]);
});

test('authorized SMS settings save encrypted credentials and reject unauthorized updates', function () {
    $unauthorized = financeModuleTestUser(['view finances']);
    $manager = financeModuleTestUser(['manage SMS settings']);
    $payload = financeModuleSmsSettingsPayload();

    $this->actingAs($unauthorized)
        ->put(route('finance.sms.settings.update'), $payload)
        ->assertForbidden();

    $this->actingAs($manager)
        ->put(route('finance.sms.settings.update'), $payload)
        ->assertRedirect(route('settings.edit').'#sms-notifications');

    expect(FinancialSetting::getValue('swala_api_key'))->toBe('swl_live_abc123')
        ->and(FinancialSetting::getValue('swala_test_mode'))->toBe('0')
        ->and(FinancialSetting::getValue('swala_enabled'))->toBe('1')
        ->and(FinancialSetting::getValue('sms_auto_offering'))->toBe('1');

    $this->assertDatabaseMissing('financial_settings', [
        'key' => 'swala_api_key',
        'value' => 'swl_live_abc123',
    ]);
    $this->assertDatabaseMissing('financial_settings', [
        'value' => 'swl_live_abc123',
    ]);
});

test('authorized system settings store the church logo on the public disk', function () {
    Storage::fake('public');
    $manager = financeModuleTestUser(['manage settings']);

    $this->actingAs($manager)
        ->put(route('settings.update'), [
            'church_short_name' => 'dodoma',
            'system_language' => 'sw',
            'church_logo' => UploadedFile::fake()->image('church.png'),
        ])
        ->assertRedirect(route('settings.edit'));

    $logoPath = Setting::getValue('church_logo');

    expect($logoPath)->toStartWith('church-logos/');
    Storage::disk('public')->assertExists($logoPath);
});

test('SMS managers can use unified settings without viewing church settings or saved credentials', function () {
    $manager = financeModuleTestUser(['manage SMS settings', 'send test SMS']);
    FinancialSetting::setValue('swala_api_key', 'swl_live_privatekey');

    $this->actingAs($manager)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('sms-notifications')
        ->assertSee('name="swala_api_key"', false)
        ->assertDontSee('name="church_short_name"', false)
        ->assertDontSee('swl_live_privatekey');

    $unauthorized = financeModuleTestUser(['view finances']);
    $this->actingAs($unauthorized)
        ->get(route('settings.edit'))
        ->assertForbidden();
});

test('SMS setting updates preserve blank credentials and reject unsupported template placeholders', function () {
    $manager = financeModuleTestUser(['manage SMS settings']);
    FinancialSetting::setValue('swala_api_key', 'swl_live_existing');

    $this->actingAs($manager)
        ->put(route('finance.sms.settings.update'), financeModuleSmsSettingsPayload([
            'swala_api_key' => '',
        ]))
        ->assertRedirect(route('settings.edit').'#sms-notifications');

    expect(FinancialSetting::getValue('swala_api_key'))->toBe('swl_live_existing');

    $this->from(route('settings.edit'))
        ->put(route('finance.sms.settings.update'), financeModuleSmsSettingsPayload([
            'sms_template_offering_en' => 'Unsupported value: {member_name}',
        ]))
        ->assertSessionHasErrors('sms_template_offering_en')
        ->assertSessionMissing('_old_input.swala_api_key');
});

test('a confirmed test SMS requires live credentials and does not create a log when they are missing', function () {
    Http::preventStrayRequests();

    $user = financeModuleTestUser(['send test SMS']);
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '1');

    $this->actingAs($user)
        ->post(route('finance.sms.test'), [
            'phone' => '0712345678',
            'type' => 'offering',
            'language' => 'sw',
            'confirmation' => '1',
        ])
        ->assertRedirect(route('settings.edit').'#sms-notifications')
        ->assertSessionHasErrors('sms');

    $this->assertDatabaseCount('financial_sms_logs', 0);
    Http::assertNothingSent();

    $unauthorized = financeModuleTestUser(['manage SMS settings']);
    $this->actingAs($unauthorized)
        ->post(route('finance.sms.test'), [
            'phone' => '0712345678',
            'type' => 'offering',
            'language' => 'sw',
            'confirmation' => '1',
        ])
        ->assertForbidden();
});

test('live test SMS is queued only after the user confirms and credentials are configured', function () {
    Queue::fake();
    Http::preventStrayRequests();

    $user = financeModuleTestUser(['send test SMS']);
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '0');
    FinancialSetting::setValue('swala_api_key', 'swl_live_livekey');
    FinancialSetting::setValue('swala_sender_id', 'KKKT');

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->post(route('finance.sms.test'), [
            'phone' => '0712345678',
            'type' => 'contribution',
            'language' => 'en',
            'confirmation' => '1',
        ])
        ->assertRedirect(route('settings.edit').'#sms-notifications')
        ->assertSessionHas('success', __('finance.test_sms_queued'));

    $log = FinancialSmsLog::query()->firstOrFail();
    expect($log->status)->toBe('queued')
        ->and($log->message)->toContain('We have received your contribution');
    Queue::assertPushed(SendFinancialSms::class, fn (SendFinancialSms $job): bool => $job->smsLogId === $log->id);

    $this->from(route('settings.edit'))
        ->post(route('finance.sms.test'), [
            'phone' => '0712345678',
            'type' => 'offering',
            'language' => 'en',
            'confirmation' => '0',
        ])
        ->assertSessionHasErrors('confirmation');
});

test('SMS settings show the webhook URL and never expose saved secrets', function () {
    Http::preventStrayRequests();
    FinancialSetting::setValue('swala_webhook_secret', 'whsec_hidden_value');

    $manager = financeModuleTestUser(['manage SMS settings']);
    $this->actingAs($manager)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee(route('webhooks.swalasms'))
        ->assertDontSee('whsec_hidden_value');

    Http::assertNothingSent();
});

test('approved financial entries queue encrypted notifications only when their category is enabled', function () {
    $this->travelTo('2026-11-02 12:00:00');
    Queue::fake();
    $creator = financeModuleTestUser(['view finances', 'manage offerings']);
    $approver = financeModuleTestUser(['approve financial transactions']);
    $member = financeModuleTestMember();
    $category = financeModuleTestCategory($creator);
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '1');
    FinancialSetting::setValue('sms_auto_offering', '1');

    $this->actingAs($creator)
        ->post(route('finance.transactions.store', ['type' => 'offering']), financeModuleEntryPayload(
            $category->id,
            ['member_number' => $member->membership_number],
        ))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $transaction = FinancialTransaction::query()->firstOrFail();

    $this->actingAs($approver)
        ->post(route('finance.transactions.approve', $transaction))
        ->assertRedirect();

    $smsLog = FinancialSmsLog::query()->firstOrFail();
    expect($smsLog->notification_type)->toBe('financial_receipt')
        ->and($smsLog->message)->toContain($transaction->receipt_number)
        ->and($smsLog->is_test)->toBeTrue();
    Queue::assertPushed(SendFinancialSms::class, fn (SendFinancialSms $job): bool => $job->smsLogId === $smsLog->id);

    FinancialSetting::setValue('sms_auto_offering', '0');
    $secondMember = financeModuleTestMember([
        'membership_number' => 'KKKT-FIN-0002',
        'envelope_number' => '00018',
    ]);
    $secondTransaction = $transaction->replicate();
    $secondTransaction->transaction_number = 'KKKT-TXN-2026-000002';
    $secondTransaction->receipt_number = 'KKKT-RCPT-2026-000002';
    $secondTransaction->idempotency_key = (string) Str::uuid();
    $secondTransaction->payload_hash = hash('sha256', 'sms-auto-disabled');
    $secondTransaction->member_id = $secondMember->id;
    $secondTransaction->member_number = $secondMember->membership_number;
    $secondTransaction->member_name = 'Neema Mushi';
    $secondTransaction->status = 'pending';
    $secondTransaction->approved_by = null;
    $secondTransaction->approved_at = null;
    $secondTransaction->save();

    $this->actingAs($approver)
        ->post(route('finance.transactions.approve', $secondTransaction))
        ->assertRedirect();

    expect(FinancialSmsLog::query()->count())->toBe(1);
});

test('SwalaSMS SMS submission uses the configured endpoint and marks accepted submissions', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.swalasms.com/v1/sms/messages' => Http::response(['success' => true, 'data' => ['uid' => 'swala-uid-123', 'recipient' => '+255712345678', 'sender_id' => 'KKKT', 'units' => 1, 'channel' => 'sms', 'status' => 'queued', 'idempotent_replay' => false]], 202),
    ]);

    FinancialSetting::setValue('swala_api_key', 'swl_live_testkey');
    FinancialSetting::setValue('swala_sender_id', 'KKKT');
    FinancialSetting::setValue('swala_test_mode', '0');

    $user = User::factory()->create();
    $member = financeModuleTestMember();
    $category = financeModuleTestCategory($user);
    $transaction = FinancialTransaction::query()->create([
        'transaction_number' => 'KKKT-TXN-2026-000001',
        'receipt_number' => 'KKKT-RCPT-2026-000001',
        'idempotency_key' => (string) Str::uuid(),
        'payload_hash' => hash('sha256', 'sms-live-test'),
        'type' => 'offering',
        'category_id' => $category->id,
        'member_id' => $member->id,
        'member_number' => $member->membership_number,
        'member_name' => 'Neema Mushi',
        'created_by' => $user->id,
        'amount' => '125.00',
        'currency' => 'TZS',
        'payment_method' => 'cash',
        'status' => 'approved',
        'transaction_date' => '2026-11-02',
    ]);
    $smsLog = FinancialSmsLog::query()->create([
        'financial_transaction_id' => $transaction->id,
        'phone' => '0712345678',
        'is_test' => false,
        'status' => 'queued',
    ]);

    (new SendFinancialSms($smsLog->id))->handle(app(SwalaSmsService::class));

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.swalasms.com/v1/sms/messages'
        && $request->hasHeader('Authorization', 'Bearer swl_live_testkey')
        && $request->hasHeader('Idempotency-Key')
        && $request['recipient'] === '+255712345678'
        && $request['sender_id'] === 'KKKT');
    expect($smsLog->fresh()->status)->toBe('accepted')
        ->and($smsLog->fresh()->provider_message_id)->toBe('swala-uid-123')
        ->and(FinancialSmsAttempt::query()->firstOrFail()->status)->toBe('accepted');
});

test('voiding a pending transaction requires and audits a reason', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $creator = financeModuleTestUser(['view finances', 'manage offerings']);
    $voider = financeModuleTestUser(['void financial transactions']);
    $category = financeModuleTestCategory($creator);

    $this->actingAs($creator)
        ->post(route('finance.transactions.store', ['type' => 'offering']), financeModuleEntryPayload($category->id))
        ->assertRedirect();
    $transaction = FinancialTransaction::query()->firstOrFail();

    $this->actingAs($voider)
        ->post(route('finance.transactions.void', $transaction), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->post(route('finance.transactions.void', $transaction), [
        'reason' => 'Duplicate entry recorded by mistake',
    ])->assertRedirect();

    expect($transaction->fresh()->status)->toBe('voided');
    expect(FinancialTransactionAudit::query()
        ->where('financial_transaction_id', $transaction->id)
        ->where('action', 'voided')
        ->firstOrFail()
        ->details['reason'])->toBe('Duplicate entry recorded by mistake');
});

test('financial report export is restricted to users with the export permission', function () {
    $reporter = financeModuleTestUser(['view financial reports']);
    $exporter = financeModuleTestUser(['export financial reports']);
    $filters = [
        'from' => '2026-11-01',
        'to' => '2026-11-30',
    ];

    $this->actingAs($reporter)
        ->get(route('finance.reports.export', $filters))
        ->assertForbidden();

    $this->actingAs($exporter)
        ->get(route('finance.reports.export', $filters))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('authorized users manage campaigns and closed campaigns reject new contributions', function () {
    $user = financeModuleTestUser(['manage contributions', 'view finances']);

    $this->actingAs($user)
        ->post(route('finance.campaigns.store'), [
            'code' => 'BUILD-001',
            'name' => 'Church Hall Project',
            'description' => 'Construction fund',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ])
        ->assertRedirect();

    $campaign = FinancialCampaign::query()->firstOrFail();
    $this->get(route('finance.campaigns.show', $campaign))
        ->assertOk()
        ->assertSee('Church Hall Project');

    $this->patch(route('finance.campaigns.toggle', $campaign))
        ->assertRedirect();
    expect($campaign->fresh()->status)->toBe('closed');

    $category = financeModuleTestCategory($user, 'contribution');
    $member = financeModuleTestMember();

    $this->post(route('finance.transactions.store', ['type' => 'contribution']), financeModuleEntryPayload(
        $category->id,
        [
            'campaign_id' => $campaign->id,
            'member_number' => $member->membership_number,
        ],
    ))->assertSessionHasErrors('campaign_id');

    $this->assertDatabaseCount('financial_transactions', 0);
});

test('campaign contributions are required and included in campaign reports', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $creator = financeModuleTestUser(['view finances', 'manage contributions']);
    $approver = financeModuleTestUser(['view finances', 'approve financial transactions']);
    $reporter = financeModuleTestUser(['view financial reports']);
    $campaign = FinancialCampaign::query()->create([
        'code' => 'PROJECT-02',
        'name' => 'Roof Repair',
        'status' => 'active',
    ]);
    $category = financeModuleTestCategory($creator, 'contribution');
    $member = financeModuleTestMember();
    $payload = financeModuleEntryPayload($category->id, [
        'campaign_id' => $campaign->id,
        'member_number' => $member->membership_number,
        'amount' => '300.00',
    ]);

    $this->actingAs($creator)
        ->post(route('finance.transactions.store', ['type' => 'contribution']), $payload)
        ->assertRedirect();
    $transaction = FinancialTransaction::query()->firstOrFail();

    $this->actingAs($approver)
        ->post(route('finance.transactions.approve', $transaction))
        ->assertRedirect();

    $this->actingAs($reporter)
        ->get(route('finance.reports.index', [
            'period' => 'custom',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'campaign_id' => $campaign->id,
        ]))
        ->assertOk()
        ->assertViewHas('byCampaign', fn ($campaigns): bool => $campaigns->count() === 1
            && number_format((float) $campaigns->first()->total, 2, '.', '') === '300.00');
});

test('contribution transaction dates must fall within the campaign dates', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $creator = financeModuleTestUser(['view finances', 'manage contributions']);
    $category = financeModuleTestCategory($creator, 'contribution');
    $campaigns = [
        FinancialCampaign::query()->create([
            'code' => 'FUTURE-01',
            'name' => 'Future campaign',
            'starts_on' => '2026-11-03',
            'ends_on' => '2026-12-31',
            'status' => 'active',
        ]),
        FinancialCampaign::query()->create([
            'code' => 'ENDED-01',
            'name' => 'Ended campaign',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-11-01',
            'status' => 'active',
        ]),
    ];

    foreach ($campaigns as $campaign) {
        $this->actingAs($creator)
            ->from(route('finance.transactions.create', ['type' => 'contribution']))
            ->post(route('finance.transactions.store', ['type' => 'contribution']), financeModuleEntryPayload(
                $category->id,
                [
                    'campaign_id' => $campaign->id,
                    'is_anonymous' => true,
                ],
            ))
            ->assertSessionHasErrors('transaction_date');
    }

    $this->assertDatabaseCount('financial_transactions', 0);
});

test('contributions are accepted on the inclusive campaign start and end dates', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $creator = financeModuleTestUser(['view finances', 'manage contributions']);
    $category = financeModuleTestCategory($creator, 'contribution');
    $campaign = FinancialCampaign::query()->create([
        'code' => 'ONE-DAY-01',
        'name' => 'One day campaign',
        'starts_on' => '2026-11-02',
        'ends_on' => '2026-11-02',
        'status' => 'active',
    ]);

    $this->actingAs($creator)
        ->post(route('finance.transactions.store', ['type' => 'contribution']), financeModuleEntryPayload(
            $category->id,
            [
                'campaign_id' => $campaign->id,
                'is_anonymous' => true,
            ],
        ))
        ->assertRedirect();

    $this->assertDatabaseHas('financial_transactions', [
        'campaign_id' => $campaign->id,
    ]);

    expect(FinancialTransaction::query()->firstOrFail()->transaction_date->toDateString())
        ->toBe('2026-11-02');
});

test('SMS history masks phone numbers and supports database filters without claiming handset delivery', function () {
    Http::preventStrayRequests();

    $user = financeModuleTestUser(['view SMS history']);
    $acceptedLog = FinancialSmsLog::query()->create([
        'notification_type' => 'financial_receipt',
        'phone' => '0712345678',
        'status' => 'accepted',
        'is_test' => false,
        'provider_message_id' => 'swala-report-123',
        'accepted_at' => now()->subMinutes(6),
    ]);
    FinancialSmsLog::query()->create([
        'notification_type' => 'test_sms',
        'phone' => '0755555555',
        'status' => 'test_only',
        'is_test' => true,
        'message' => 'A sample test message',
    ]);

    $this->actingAs($user)
        ->get(route('finance.sms.index', [
            'type' => 'financial_receipt',
            'status' => 'accepted',
            'search' => 'swala-report-123',
        ]))
        ->assertOk()
        ->assertSee('071*****78')
        ->assertDontSee('0712345678')
        ->assertSee($acceptedLog->provider_message_id)
        ->assertSee(__('finance.sms_delivery_tracking_note'));

    $this->post("/finance/sms/{$acceptedLog->id}/delivery-report")->assertNotFound();
    Http::assertNothingSent();
});

test('finance permissions are enforced separately for reports receipts and SMS settings', function () {
    $user = financeModuleTestUser(['view finances']);

    $this->actingAs($user)
        ->get(route('finance.reports.export'))
        ->assertForbidden();

    $this->get(route('finance.receipts.index'))
        ->assertForbidden();

    $this->get(route('finance.sms.settings.edit'))
        ->assertForbidden();

    $this->get(route('finance.sms.index'))
        ->assertForbidden();
});
