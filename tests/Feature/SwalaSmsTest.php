<?php

use App\Jobs\SendBulkSmsBatch;
use App\Jobs\SendFinancialSms;
use App\Models\BulkSmsCampaign;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use App\Models\Member;
use App\Models\User;
use App\Services\BulkSmsBatchDispatcher;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function swalaTestConfigure(): void
{
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '0');
    FinancialSetting::setValue('swala_api_key', 'swl_live_unit_test_key');
    FinancialSetting::setValue('swala_sender_id', 'KKKT');
}

function swalaTestUser(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function swalaTestLog(array $overrides = []): FinancialSmsLog
{
    return FinancialSmsLog::query()->create(array_merge([
        'notification_type' => 'test_sms',
        'message' => 'Hello there',
        'phone' => '0712345678',
        'status' => 'queued',
        'is_test' => false,
        'sms_segments' => 1,
    ], $overrides));
}

function swalaTestMessage(string $uid = 'msg-1', string $status = 'queued'): array
{
    return ['success' => true, 'data' => [
        'uid' => $uid, 'recipient' => '+255712345678', 'sender_id' => 'KKKT', 'units' => 1,
        'channel' => 'sms', 'status' => $status, 'queued_at' => '2026-11-02T10:00:00Z',
        'idempotent_replay' => false, 'encoding' => ['segments' => 1],
    ]];
}

function swalaSignedWebhook($test, array $payload, ?string $secret = 'whsec_test')
{
    $body = json_encode($payload);

    return $test->call('POST', route('webhooks.swalasms'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SWALASMS_SIGNATURE' => $secret === null ? 'bad' : hash_hmac('sha256', $body, $secret),
    ], $body);
}

it('sends one SMS with bearer auth, E.164 recipient, body and idempotency key', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sms/messages' => Http::response(swalaTestMessage('uid-7'), 202)]);
    $key = (string) Str::uuid();

    $result = app(SwalaSmsService::class)->sendMessage('0712 345 678', 'Habari', $key);

    expect($result['uid'])->toBe('uid-7')->and($result['status'])->toBe('queued');
    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer swl_live_unit_test_key')
        && $request->hasHeader('Idempotency-Key', $key)
        && $request['recipient'] === '+255712345678'
        && $request['sender_id'] === 'KKKT'
        && $request['body'] === 'Habari');
});

it('maps provider errors to acceptance state and retryability', function (int $status, string $code, string $state, bool $retryable) {
    swalaTestConfigure();
    Http::fake(['https://api.swalasms.com/*' => Http::response(['success' => false, 'message' => 'nope', 'error_code' => $code, 'acceptance_state' => $state], $status)]);

    try {
        app(SwalaSmsService::class)->sendMessage('0712345678', 'Hi', (string) Str::uuid());
        $this->fail('Expected an exception.');
    } catch (SwalaSmsException $exception) {
        expect($exception->errorCode)->toBe($code)
            ->and($exception->acceptanceState)->toBe($state)
            ->and($exception->retryable)->toBe($retryable);
    }
})->with([
    'auth' => [401, 'AUTHENTICATION_FAILED', 'DEFINITELY_NOT_ACCEPTED', false],
    'idempotency conflict' => [409, 'IDEMPOTENCY_CONFLICT', 'DEFINITELY_NOT_ACCEPTED', false],
    'validation' => [422, 'INVALID_RECIPIENT', 'DEFINITELY_NOT_ACCEPTED', false],
    'rate limited' => [429, 'RATE_LIMITED', 'DEFINITELY_NOT_ACCEPTED', true],
    'unavailable' => [503, 'TEMPORARY_UNAVAILABLE', 'UNKNOWN', true],
]);

it('keeps the same idempotency key when a send is retried after an unknown outcome', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sms/messages' => Http::sequence()
        ->push(['success' => false, 'message' => 'later', 'error_code' => 'TEMPORARY_UNAVAILABLE', 'acceptance_state' => 'UNKNOWN'], 503)
        ->push(swalaTestMessage('uid-after-retry'), 202)]);
    $log = swalaTestLog();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class));
    $log->refresh();
    $firstKey = $log->idempotency_key;

    expect($firstKey)->not->toBeNull()
        ->and($log->provider_acceptance_state)->toBe('UNKNOWN')
        ->and($log->status)->toBe('failed');

    $log->forceFill(['status' => 'queued'])->save();
    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class));

    expect($log->fresh()->status)->toBe('accepted')
        ->and($log->fresh()->provider_message_id)->toBe('uid-after-retry')
        ->and($log->fresh()->provider_status)->toBe('queued');
    $keys = [];
    Http::assertSent(function (HttpRequest $request) use (&$keys): bool {
        $keys[] = $request->header('Idempotency-Key')[0] ?? null;

        return true;
    });
    expect(array_unique($keys))->toBe([$firstKey]);
});

it('does not retry permanent validation errors and records no secrets', function () {
    swalaTestConfigure();
    Http::fake(['https://api.swalasms.com/*' => Http::response(['success' => false, 'message' => 'Insufficient credit', 'error_code' => 'INSUFFICIENT_CREDIT', 'acceptance_state' => 'DEFINITELY_NOT_ACCEPTED'], 422)]);
    $log = swalaTestLog();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->provider_error_code)->toBe('INSUFFICIENT_CREDIT')
        ->and($log->fresh()->error_message)->not->toContain('swl_live_');
    Http::assertSentCount(1);
});

it('sends bulk payloads with tags and maps accepted messages and failures', function () {
    swalaTestConfigure();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response(['success' => true, 'data' => [
        'uid' => 'camp-1',
        'messages' => [['to' => '+255712345678', 'message_uid' => 'm-1', 'status' => 'queued', 'units' => 1]],
        'failures' => [['to' => '+255700000000', 'reason' => 'invalid']],
    ]], 202)]);

    $result = app(SwalaSmsService::class)->sendBulk([
        ['to' => '+255712345678', 'tags' => ['member_name' => 'Neema']],
        ['to' => '+255700000000'],
    ], 'Hi {member_name}', 'Campaign', 'batch-key-1');

    expect($result['campaign_uid'])->toBe('camp-1')->and($result['messages'])->toHaveCount(1)->and($result['failures'])->toHaveCount(1);
    Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('Idempotency-Key', 'batch-key-1')
        && $request['recipients'][0] === ['to' => '+255712345678', 'member_name' => 'Neema']
        && $request['recipients'][1] === '+255700000000'
        && $request['message'] === 'Hi {member_name}');
});

it('rejects bulk requests above 1000 recipients without calling the provider', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    $recipients = array_map(fn (int $i): array => ['to' => '+2557'.str_pad((string) $i, 8, '0', STR_PAD_LEFT)], range(1, 1001));

    expect(fn () => app(SwalaSmsService::class)->sendBulk($recipients, 'Hi', null, 'k'))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

it('queues campaign logs in bounded batches with one stable key per batch', function () {
    Queue::fake([SendBulkSmsBatch::class]);
    config(['services.swalasms.bulk_batch_size' => 1000]);
    $creator = swalaTestUser([]);
    $campaign = BulkSmsCampaign::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Big', 'message' => 'Hello', 'language' => 'en', 'status' => 'queued',
        'selection_mode' => 'selected', 'selection_criteria' => [], 'created_by' => $creator->id,
    ]);
    $ids = [];

    foreach (range(1, 1200) as $i) {
        $ids[] = swalaTestLog(['bulk_sms_campaign_id' => $campaign->id, 'notification_type' => 'bulk_campaign', 'phone' => '255712'.str_pad((string) $i, 6, '0', STR_PAD_LEFT)])->id;
    }

    app(BulkSmsBatchDispatcher::class)->dispatch($campaign, $ids);

    Queue::assertPushed(SendBulkSmsBatch::class, 2);
    $sizes = FinancialSmsLog::query()->where('bulk_sms_campaign_id', $campaign->id)->selectRaw('provider_batch_key, count(*) as total')->groupBy('provider_batch_key')->pluck('total')->all();
    expect(max($sizes))->toBeLessThanOrEqual(1000)->and(count($sizes))->toBe(2);
});

it('maps a bulk response onto recipient logs and does not resend successful recipients on retry', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    $creator = swalaTestUser([]);
    $campaign = BulkSmsCampaign::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Update', 'message' => 'Hello {{member_name}}', 'language' => 'en', 'status' => 'queued',
        'selection_mode' => 'selected', 'selection_criteria' => [], 'created_by' => $creator->id,
    ]);
    $members = collect([1, 2])->map(fn (int $i): Member => Member::query()->create([
        'membership_number' => 'KKKT-SW-000'.$i, 'envelope_number' => '9000'.$i, 'first_name' => 'Member'.$i, 'last_name' => 'Test',
        'gender' => 'Female', 'date_of_birth' => '1990-01-01', 'phone' => '07123456'.$i.'0', 'membership_date' => '2020-01-01',
        'status' => 'active', 'sms_opt_in' => true,
    ]));
    $logs = $members->map(fn (Member $m): FinancialSmsLog => swalaTestLog([
        'bulk_sms_campaign_id' => $campaign->id, 'member_id' => $m->id, 'notification_type' => 'bulk_campaign',
        'phone' => preg_replace('/^0/', '255', $m->phone), 'message' => 'Hello '.$m->first_name,
    ]));
    Queue::fake([SendBulkSmsBatch::class]);
    app(BulkSmsBatchDispatcher::class)->dispatch($campaign, $logs->pluck('id')->all());
    $batchKey = $logs->first()->fresh()->provider_batch_key;

    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response(['success' => true, 'data' => [
        'uid' => 'camp-9',
        'messages' => [['to' => '+'.$logs[0]->phone, 'message_uid' => 'bm-1', 'status' => 'queued', 'units' => 1]],
        'failures' => [['to' => '+'.$logs[1]->phone, 'reason' => 'invalid recipient']],
    ]], 202)]);

    (new SendBulkSmsBatch($campaign->id, $batchKey))->handle(app(SwalaSmsService::class));

    expect($logs[0]->fresh()->status)->toBe('accepted')
        ->and($logs[0]->fresh()->provider_message_id)->toBe('bm-1')
        ->and($logs[0]->fresh()->provider_campaign_uid)->toBe('camp-9')
        ->and($logs[1]->fresh()->status)->toBe('failed');

    Http::assertSent(fn (HttpRequest $request): bool => $request['message'] === 'Hello {member_name}'
        && count($request['recipients']) === 2);

    $user = swalaTestUser(['view SMS campaigns', 'retry failed SMS']);
    $this->actingAs($user)->post(route('finance.sms.campaigns.retry-failed', $campaign))->assertRedirect();
    Queue::assertPushed(SendBulkSmsBatch::class, 2);
    expect($logs[0]->fresh()->status)->toBe('accepted');
});

it('rejects webhooks with a missing or invalid signature and does not change logs', function () {
    FinancialSetting::setValue('swala_webhook_secret', 'whsec_test');
    $log = swalaTestLog(['status' => 'accepted', 'provider' => 'swalasms', 'provider_message_id' => 'w-1']);
    $payload = ['event' => 'sms.delivered', 'message_id' => 'w-1', 'status' => 'delivered', 'timestamp' => 1793000000];

    swalaSignedWebhook($this, $payload, null)->assertUnauthorized();
    $this->postJson(route('webhooks.swalasms'), $payload)->assertUnauthorized();

    expect($log->fresh()->delivery_status)->toBeNull();
});

it('rejects webhooks when no signing secret is configured', function () {
    $this->postJson(route('webhooks.swalasms'), ['message_id' => 'x'], ['X-SwalaSMS-Signature' => hash_hmac('sha256', '', '')])->assertUnauthorized();
});

it('records a signed delivery event once and ignores repeats and older events', function () {
    FinancialSetting::setValue('swala_webhook_secret', 'whsec_test');
    $log = swalaTestLog(['status' => 'accepted', 'provider' => 'swalasms', 'provider_message_id' => 'w-2']);

    swalaSignedWebhook($this, ['event' => 'sms.delivered', 'message_id' => 'w-2', 'status' => 'delivered', 'timestamp' => 1793000100, 'delivered_at' => '2026-11-02T10:05:00Z'])->assertOk();
    $first = $log->fresh();
    expect($first->delivery_status)->toBe('delivered')->and($first->delivered_at)->not->toBeNull();

    swalaSignedWebhook($this, ['event' => 'sms.delivered', 'message_id' => 'w-2', 'status' => 'delivered', 'timestamp' => 1793000100])->assertOk();
    swalaSignedWebhook($this, ['event' => 'sms.failed', 'message_id' => 'w-2', 'status' => 'failed', 'error' => 'late', 'timestamp' => 1793000050])->assertOk();

    expect($log->fresh()->delivery_status)->toBe('delivered')
        ->and($log->fresh()->provider_failure_reason)->toBeNull();
});

it('records a failed delivery event and acknowledges unknown message ids', function () {
    FinancialSetting::setValue('swala_webhook_secret', 'whsec_test');
    $log = swalaTestLog(['status' => 'accepted', 'provider' => 'swalasms', 'provider_message_id' => 'w-3']);

    swalaSignedWebhook($this, ['event' => 'sms.failed', 'message_id' => 'w-3', 'status' => 'failed', 'error' => 'Handset unreachable', 'timestamp' => 1793000200])->assertOk();
    swalaSignedWebhook($this, ['event' => 'sms.delivered', 'message_id' => 'nobody', 'status' => 'delivered', 'timestamp' => 1793000300])->assertOk();

    expect($log->fresh()->delivery_status)->toBe('failed')->and($log->fresh()->provider_failure_reason)->toBe('Handset unreachable');
});

it('stores the webhook secret and API key encrypted, masked and only for authorized users', function () {
    $manager = swalaTestUser(['manage SMS settings']);
    $payload = [
        'swala_enabled' => '1', 'swala_test_mode' => '1', 'swala_sender_id' => 'KKKT',
        'swala_api_key' => 'swl_test_secretvalue', 'swala_webhook_secret' => 'whsec_plain_secret',
        'sms_language' => 'en', 'sms_auto_offering' => '0', 'sms_auto_contribution' => '0', 'sms_auto_other_income' => '0',
    ];
    foreach (['offering', 'contribution', 'sunday_offering'] as $type) {
        foreach (['en', 'sw'] as $language) {
            $payload["sms_template_{$type}_{$language}"] = __('finance.sms_template_'.$type, [], $language);
        }
    }

    $this->actingAs(swalaTestUser(['view finances']))->put(route('finance.sms.settings.update'), $payload)->assertForbidden();
    $this->actingAs($manager)->put(route('finance.sms.settings.update'), $payload)->assertSessionHasNoErrors();

    expect(FinancialSetting::getValue('swala_webhook_secret'))->toBe('whsec_plain_secret');
    $this->assertDatabaseMissing('financial_settings', ['value' => 'swl_test_secretvalue']);
    $this->assertDatabaseMissing('financial_settings', ['value' => 'whsec_plain_secret']);
    $this->actingAs($manager)->get(route('settings.edit'))->assertOk()->assertDontSee('swl_test_secretvalue')->assertDontSee('whsec_plain_secret');

    $this->actingAs($manager)->put(route('finance.sms.settings.update'), array_merge($payload, ['swala_api_key' => 'bad-format']))->assertSessionHasErrors('swala_api_key');
});

it('tests the connection through the documented balance endpoint only', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/balance' => Http::response(['success' => true, 'data' => ['balance' => 42, 'currency' => 'SMS', 'countries' => []]], 200)]);
    $manager = swalaTestUser(['manage SMS settings']);

    $this->actingAs(swalaTestUser(['view finances']))->post(route('finance.sms.settings.test-connection'))->assertForbidden();
    $this->actingAs($manager)->post(route('finance.sms.settings.test-connection'))->assertRedirect();

    expect(FinancialSetting::getValue('swala_last_check_status'))->toBe('ok')
        ->and(FinancialSetting::getValue('swala_last_balance'))->toBe('42')
        ->and(FinancialSetting::getValue('swala_last_success_at'))->not->toBeNull();
    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'GET' && $request->url() === 'https://api.swalasms.com/v1/balance');
});

it('shows real provider status and SMS dashboard totals without double-counting failures', function () {
    FinancialSetting::setValue('swala_sender_id', 'KKKT');
    FinancialSetting::setValue('swala_last_check_status', 'failed');
    FinancialSmsLog::query()->create([
        'notification_type' => 'financial_receipt',
        'phone' => '0712345678',
        'status' => 'accepted',
        'provider' => 'swalasms',
        'delivery_status' => 'queued',
    ]);
    FinancialSmsLog::query()->create([
        'notification_type' => 'financial_receipt',
        'phone' => '0712345679',
        'status' => 'accepted',
        'provider' => 'swalasms',
        'delivery_status' => 'delivered',
    ]);
    FinancialSmsLog::query()->create([
        'notification_type' => 'financial_receipt',
        'phone' => '0712345680',
        'status' => 'failed',
        'provider' => 'swalasms',
        'delivery_status' => 'failed',
    ]);

    $this->actingAs(swalaTestUser(['view SMS history']))
        ->get(route('finance.sms.index'))
        ->assertOk()
        ->assertSee(__('finance.swala_status_disconnected'))
        ->assertSee(__('finance.swala_sender_id'))
        ->assertSee('KKKT')
        ->assertSee(__('finance.sms_total_messages'))
        ->assertSee(__('finance.sms_pending_messages'));
});

it('refreshes a message status from the provider and keeps final statuses final', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sms/messages/r-1' => Http::response(['success' => true, 'data' => [
        'uid' => 'r-1', 'recipient' => '+255712345678', 'sender_id' => 'KKKT', 'units' => 1, 'channel' => 'sms', 'status' => 'delivered',
        'delivered_at' => '2026-11-02T10:05:00Z',
    ]], 200)]);
    $log = swalaTestLog(['status' => 'accepted', 'provider' => 'swalasms', 'provider_message_id' => 'r-1']);
    $user = swalaTestUser(['view SMS history']);

    $this->actingAs($user)->post(route('finance.sms.refresh-status', $log))->assertRedirect();

    expect($log->fresh()->delivery_status)->toBe('delivered');
});
