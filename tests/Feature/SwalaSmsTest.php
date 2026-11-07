<?php

use App\Jobs\SendBulkSmsBatch;
use App\Jobs\SendFinancialSms;
use App\Models\BulkSmsCampaign;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use App\Models\FinancialSmsStatusEvent;
use App\Models\Member;
use App\Models\User;
use App\Services\BulkSmsBatchDispatcher;
use App\Services\SmsSegmentEstimator;
use App\Services\SmsSettings;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

function swalaTestSuperAdmin(array $permissions): User
{
    $user = swalaTestUser($permissions);
    $user->assignRole(Role::findOrCreate(User::SUPER_ADMIN_ROLE, 'web'));

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

function swalaTestBulkMessage(string $uid = 'msg-1', string $status = 'queued'): array
{
    return ['success' => true, 'data' => [
        'uid' => 'campaign-1',
        'messages' => [['to' => '+255712345678', 'message_uid' => $uid, 'status' => $status, 'units' => 1]],
        'failures' => [],
    ]];
}

it('sends one SMS with bearer auth, E.164 recipient, body and idempotency key', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response(swalaTestBulkMessage('uid-7'), 202)]);
    $key = (string) Str::uuid();

    $result = app(SwalaSmsService::class)->sendMessage('0712 345 678', 'Habari', $key);

    expect($result['uid'])->toBe('uid-7')->and($result['status'])->toBe('queued');
    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer swl_live_unit_test_key')
        && $request->hasHeader('Idempotency-Key', $key)
        && $request['recipients'] === ['+255712345678']
        && $request['sender_id'] === 'KKKT'
        && $request['message'] === 'Habari');
});

it('treats a bulk response without the requested message as an unknown outcome', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response([
        'success' => true,
        'data' => ['uid' => 'campaign-1', 'messages' => [], 'failures' => []],
    ], 202)]);

    try {
        app(SwalaSmsService::class)->sendMessage('0712345678', 'Habari', (string) Str::uuid());
        $this->fail('An unconfirmed recipient must not be marked as accepted.');
    } catch (SwalaSmsException $exception) {
        expect($exception->acceptanceState)->toBe(SwalaSmsException::UNKNOWN);
    }
});

it('treats an unrecognized provider status as an unknown outcome', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response(swalaTestBulkMessage('message-1', 'unrecognized'), 202)]);

    try {
        app(SwalaSmsService::class)->sendMessage('0712345678', 'Habari', (string) Str::uuid());
        $this->fail('An unrecognized provider status must not be marked as queued.');
    } catch (SwalaSmsException $exception) {
        expect($exception->acceptanceState)->toBe(SwalaSmsException::UNKNOWN);
    }
});

it('records a provider recipient rejection without marking the SMS accepted', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response([
        'success' => true,
        'data' => [
            'uid' => 'campaign-1',
            'messages' => [],
            'failures' => [['to' => '+255712345678', 'reason' => 'Recipient rejected']],
        ],
    ], 202)]);
    $log = swalaTestLog();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->provider_acceptance_state)->toBe(SwalaSmsException::NOT_ACCEPTED)
        ->and($log->fresh()->error_message)->toContain('Recipient rejected');
});

it('records an invalid legacy phone as not submitted without calling the provider', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response(swalaTestBulkMessage(), 202)]);
    $log = swalaTestLog(['phone' => 'invalid legacy phone']);

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->provider_acceptance_state)->toBe(SwalaSmsException::NOT_ACCEPTED)
        ->and($log->fresh()->error_message)->toContain('invalid or not a mobile number');
    Http::assertNothingSent();
});

it('estimates GSM-7 and Unicode characters using their segment limits', function () {
    $estimator = app(SmsSegmentEstimator::class);

    expect($estimator->estimate(str_repeat('a', 161)))
        ->toBe(['encoding' => 'GSM-7', 'characters' => 161, 'units' => 161, 'segments' => 2])
        ->and($estimator->estimate(str_repeat('^', 81)))
        ->toBe(['encoding' => 'GSM-7', 'characters' => 81, 'units' => 162, 'segments' => 2])
        ->and($estimator->estimate(str_repeat('雪', 71)))
        ->toBe(['encoding' => 'Unicode', 'characters' => 71, 'units' => 71, 'segments' => 2]);
});

it('queues a confirmed manual SMS with encoding metadata for an E.164 recipient', function () {
    swalaTestConfigure();
    Queue::fake();
    $user = swalaTestUser(['send SMS']);

    $this->actingAs($user)
        ->get(route('finance.sms.compose'))
        ->assertOk();

    $this->actingAs($user)
        ->post(route('finance.sms.send'), [
            'phone' => '+255712345678',
            'message' => 'Habari, Amina',
            'confirmation' => '1',
        ])
        ->assertRedirect(route('finance.sms.index'))
        ->assertSessionHas('success', __('finance.manual_sms_queued'));

    $log = FinancialSmsLog::query()->where('notification_type', 'manual_sms')->firstOrFail();

    expect($log->phone)->toBe('+255712345678')
        ->and($log->message)->toBe('Habari, Amina')
        ->and($log->status)->toBe('queued')
        ->and($log->sender_id)->toBe('KKKT')
        ->and($log->encoding)->toBe('GSM-7')
        ->and($log->sms_segments)->toBe(1);

    Queue::assertPushed(SendFinancialSms::class, fn (SendFinancialSms $job): bool => $job->smsLogId === $log->id);
});

it('rejects manual SMS without a live key and rejects non-E.164 recipients', function () {
    swalaTestConfigure();
    Queue::fake();
    $user = swalaTestUser(['send SMS']);

    FinancialSetting::setValue('swala_api_key', 'swl_test_unit_test_key');

    $this->actingAs($user)
        ->post(route('finance.sms.send'), [
            'phone' => '+255712345678',
            'message' => 'Hello',
            'confirmation' => '1',
        ])
        ->assertRedirect(route('finance.sms.compose'))
        ->assertSessionHasErrors('sms');

    FinancialSetting::setValue('swala_api_key', 'swl_live_unit_test_key');

    $this->actingAs($user)
        ->post(route('finance.sms.send'), [
            'phone' => '0712345678',
            'message' => 'Hello',
            'confirmation' => '1',
        ])
        ->assertSessionHasErrors('phone');

    expect(FinancialSmsLog::query()->where('notification_type', 'manual_sms')->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('requires the send SMS permission to open the manual composer', function () {
    $this->actingAs(swalaTestUser([]))
        ->get(route('finance.sms.compose'))
        ->assertForbidden();
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
    'validation' => [422, 'INVALID_RECIPIENT', 'DEFINITELY_NOT_ACCEPTED', false],
    'rate limited' => [429, 'RATE_LIMITED', 'DEFINITELY_NOT_ACCEPTED', true],
    'unavailable' => [503, 'TEMPORARY_UNAVAILABLE', 'UNKNOWN', true],
]);

it('holds an idempotency conflict for reconciliation even when the new payload was not accepted', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response([
        'success' => false, 'error_code' => 'IDEMPOTENCY_CONFLICT', 'acceptance_state' => 'DEFINITELY_NOT_ACCEPTED',
    ], 409)]);
    $log = swalaTestLog();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->provider_acceptance_state)->toBe(SwalaSmsException::UNKNOWN);
    Http::assertSentCount(1);
});

it('marks a job that failed while sending as an unknown outcome', function () {
    $log = swalaTestLog(['status' => 'sending', 'attempts' => 1]);

    (new SendFinancialSms($log->id))->failed(new RuntimeException('worker failed'));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->provider_acceptance_state)->toBe(SwalaSmsException::UNKNOWN);
});

it('preserves the idempotency key and does not resend after an unknown outcome', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response(
        ['success' => false, 'message' => 'later', 'error_code' => 'TEMPORARY_UNAVAILABLE', 'acceptance_state' => 'UNKNOWN'], 503,
    )]);
    $log = swalaTestLog();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));
    $log->refresh();
    $firstKey = $log->idempotency_key;

    expect($firstKey)->not->toBeNull()
        ->and($log->provider_acceptance_state)->toBe('UNKNOWN')
        ->and($log->status)->toBe('failed');

    $log->forceFill(['status' => 'queued'])->save();
    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->idempotency_key)->toBe($firstKey)
        ->and($log->fresh()->provider_message_id)->toBeNull();
    Http::assertSentCount(1);
});

it('holds an HTTP timeout with no acceptance state for reconciliation', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response(
        ['success' => false, 'message' => 'Request timeout'], 408,
    )]);
    $log = swalaTestLog();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->provider_acceptance_state)->toBe(SwalaSmsException::UNKNOWN);
    Http::assertSentCount(1);
});

it('holds an unexpected single-send exception for reconciliation without changing its key', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    $requests = 0;
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => function () use (&$requests) {
        $requests++;
        throw new RuntimeException('Unexpected transport failure');
    }]);
    $log = swalaTestLog();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));
    $log->refresh();
    $key = $log->idempotency_key;

    expect($log->status)->toBe('failed')
        ->and($log->provider_acceptance_state)->toBe(SwalaSmsException::UNKNOWN)
        ->and($key)->not->toBeNull()
        ->and($requests)->toBe(1);

    $log->forceFill(['status' => 'queued'])->save();
    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->idempotency_key)->toBe($key)
        ->and($requests)->toBe(1);
});

it('holds an unexpected bulk-send exception for reconciliation without changing its key', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    $requests = 0;
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => function () use (&$requests) {
        $requests++;
        throw new RuntimeException('Unexpected transport failure');
    }]);
    $creator = swalaTestUser([]);
    $campaign = BulkSmsCampaign::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Update', 'message' => 'Hello', 'language' => 'en',
        'status' => 'queued', 'selection_mode' => 'selected', 'selection_criteria' => [], 'created_by' => $creator->id,
    ]);
    $member = Member::query()->create([
        'membership_number' => 'KKKT-SW-0001', 'envelope_number' => '90001', 'first_name' => 'Member',
        'last_name' => 'Test', 'gender' => 'Female', 'date_of_birth' => '1990-01-01', 'phone' => '0712345678',
        'membership_date' => '2020-01-01', 'status' => 'active', 'sms_opt_in' => true,
    ]);
    $key = (string) Str::uuid();
    $log = swalaTestLog([
        'bulk_sms_campaign_id' => $campaign->id, 'member_id' => $member->id, 'notification_type' => 'bulk_campaign',
        'phone' => '255712345678', 'provider_batch_key' => $key, 'idempotency_key' => $key,
    ]);

    (new SendBulkSmsBatch($campaign->id, $key))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));
    $log->refresh();

    expect($log->status)->toBe('failed')
        ->and($log->provider_acceptance_state)->toBe(SwalaSmsException::UNKNOWN)
        ->and($log->idempotency_key)->toBe($key)
        ->and($requests)->toBe(1);

    $log->forceFill(['status' => 'queued'])->save();
    (new SendBulkSmsBatch($campaign->id, $key))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->idempotency_key)->toBe($key)
        ->and($requests)->toBe(1);
});

it('rejects a manual retry when the prior submission outcome is unknown', function () {
    swalaTestConfigure();
    Queue::fake();
    $log = swalaTestLog([
        'notification_type' => 'financial_receipt',
        'status' => 'failed',
        'provider_acceptance_state' => SwalaSmsException::UNKNOWN,
    ]);

    $this->actingAs(swalaTestUser(['retry failed SMS']))
        ->from(route('finance.sms.index'))
        ->post(route('finance.sms.retry', $log))
        ->assertRedirect(route('finance.sms.index'))
        ->assertSessionHasErrors(['sms' => __('finance.sms_unknown_outcome')]);

    expect($log->fresh()->status)->toBe('failed');
    Queue::assertNothingPushed();
});

it('does not retry permanent validation errors and records no secrets', function () {
    swalaTestConfigure();
    Http::fake(['https://api.swalasms.com/*' => Http::response(['success' => false, 'message' => 'Insufficient credit', 'error_code' => 'INSUFFICIENT_CREDIT', 'acceptance_state' => 'DEFINITELY_NOT_ACCEPTED'], 422)]);
    $log = swalaTestLog();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

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

    (new SendBulkSmsBatch($campaign->id, $batchKey))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

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

it('does not assign a new batch key to a queued unknown submission', function () {
    Queue::fake([SendBulkSmsBatch::class]);
    $creator = swalaTestUser([]);
    $campaign = BulkSmsCampaign::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Update', 'message' => 'Hello', 'language' => 'en', 'status' => 'queued',
        'selection_mode' => 'selected', 'selection_criteria' => [], 'created_by' => $creator->id,
    ]);
    $key = (string) Str::uuid();
    $log = swalaTestLog([
        'bulk_sms_campaign_id' => $campaign->id,
        'provider_batch_key' => $key,
        'idempotency_key' => $key,
        'provider_acceptance_state' => SwalaSmsException::UNKNOWN,
    ]);

    try {
        app(BulkSmsBatchDispatcher::class)->dispatch($campaign, [$log->id]);
        $this->fail('Unknown outcomes must not be assigned a new batch.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('must be reconciled');
    }

    expect($log->fresh()->idempotency_key)->toBe($key)
        ->and($log->fresh()->provider_batch_key)->toBe($key);
    Queue::assertNotPushed(SendBulkSmsBatch::class);
});

it('does not replace the batch key after another job has claimed a recipient', function () {
    Queue::fake([SendBulkSmsBatch::class]);
    $creator = swalaTestUser([]);
    $campaign = BulkSmsCampaign::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Update', 'message' => 'Hello', 'language' => 'en', 'status' => 'processing',
        'selection_mode' => 'selected', 'selection_criteria' => [], 'created_by' => $creator->id,
    ]);
    $key = (string) Str::uuid();
    $log = swalaTestLog([
        'bulk_sms_campaign_id' => $campaign->id,
        'status' => 'sending',
        'provider_batch_key' => $key,
        'idempotency_key' => $key,
    ]);

    $count = app(BulkSmsBatchDispatcher::class)->dispatch($campaign, [$log->id]);

    expect($count)->toBe(0)
        ->and($log->fresh()->provider_batch_key)->toBe($key)
        ->and($log->fresh()->idempotency_key)->toBe($key);
    Queue::assertNotPushed(SendBulkSmsBatch::class);
});

it('does not send an already queued batch with an unknown prior outcome', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    $creator = swalaTestUser(['retry failed SMS']);
    $campaign = BulkSmsCampaign::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Unknown outcome', 'message' => 'Hello', 'language' => 'en',
        'status' => 'queued', 'selection_mode' => 'selected', 'selection_criteria' => [], 'created_by' => $creator->id,
    ]);
    $batchKey = (string) Str::uuid();
    $log = swalaTestLog([
        'bulk_sms_campaign_id' => $campaign->id,
        'notification_type' => 'bulk_campaign',
        'provider_batch_key' => $batchKey,
        'provider_acceptance_state' => SwalaSmsException::UNKNOWN,
    ]);

    (new SendBulkSmsBatch($campaign->id, $batchKey))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->provider_acceptance_state)->toBe(SwalaSmsException::UNKNOWN);
    Http::assertNothingSent();

    $this->actingAs($creator)
        ->from(route('finance.sms.campaigns.show', $campaign))
        ->post(route('finance.sms.campaigns.retry-failed', $campaign))
        ->assertSessionHasErrors(['campaign' => __('finance.sms_unknown_outcome')]);
    expect($log->fresh()->status)->toBe('failed');
});

it('rejects webhook requests because the provider does not document an authenticated webhook endpoint', function () {
    $log = swalaTestLog(['status' => 'accepted', 'provider' => 'swalasms', 'provider_message_id' => 'w-1']);

    $this->postJson('/webhooks/swalasms', [
        'event' => 'sms.delivered',
        'message_id' => 'w-1',
        'status' => 'delivered',
    ])->assertNotFound();

    expect($log->fresh()->delivery_status)->toBeNull();
});

it('requires history permission before polling a message delivery status', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sms/messages/w-2' => Http::response(swalaTestMessage('w-2', 'delivered'), 200)]);
    $log = swalaTestLog(['status' => 'accepted', 'provider' => 'swalasms', 'provider_message_id' => 'w-2']);

    $this->actingAs(swalaTestUser([]))
        ->post(route('finance.sms.refresh-status', $log))
        ->assertForbidden();

    expect($log->fresh()->delivery_status)->toBeNull();
    Http::assertNothingSent();
});

it('keeps final delivery statuses unchanged when later provider polls disagree', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.swalasms.com/v1/sms/messages/w-3' => Http::sequence()
            ->push(['success' => true, 'data' => [
                'uid' => 'w-3', 'status' => 'delivered', 'units' => 1,
                'delivered_at' => '2026-11-02T10:05:00Z',
            ]], 200)
            ->push(['success' => true, 'data' => [
                'uid' => 'w-3', 'status' => 'failed', 'units' => 1,
                'failure_reason' => 'late failure',
            ]], 200),
    ]);
    $log = swalaTestLog(['status' => 'accepted', 'provider' => 'swalasms', 'provider_message_id' => 'w-3']);
    $user = swalaTestUser(['view SMS history']);

    $this->actingAs($user)->post(route('finance.sms.refresh-status', $log))->assertRedirect();
    $this->actingAs($user)->post(route('finance.sms.refresh-status', $log))->assertRedirect();

    expect($log->fresh()->delivery_status)->toBe('delivered')
        ->and($log->fresh()->provider_failure_reason)->toBeNull();
});

it('automatically refreshes a bounded pending-status batch and records status history', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.swalasms.com/v1/sms/messages/auto-1' => Http::response([
            'success' => true,
            'data' => [
                'uid' => 'auto-1',
                'status' => 'delivered',
                'units' => 1,
                'sent_at' => '2026-11-06T09:00:00Z',
                'delivered_at' => '2026-11-06T09:01:00Z',
            ],
        ], 200),
    ]);
    $log = swalaTestLog([
        'status' => 'accepted',
        'provider' => 'swalasms',
        'provider_message_id' => 'auto-1',
        'delivery_status' => 'queued',
        'delivery_checked_at' => now()->subMinutes(10),
    ]);

    $this->artisan('app:sync-pending-swala-sms-statuses')->assertSuccessful();

    expect($log->fresh()->delivery_status)->toBe('delivered');
    $this->assertDatabaseHas('financial_sms_status_events', [
        'financial_sms_log_id' => $log->id,
        'previous_status' => 'queued',
        'status' => 'delivered',
        'source' => 'automatic',
    ]);
});

it('records provider delivery failures from the authenticated status API', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sms/messages/w-4' => Http::response(['success' => true, 'data' => [
        'uid' => 'w-4',
        'status' => 'failed',
        'units' => 1,
        'failure_reason' => 'Handset unreachable',
    ]], 200)]);
    $log = swalaTestLog(['status' => 'accepted', 'provider' => 'swalasms', 'provider_message_id' => 'w-4']);

    $this->actingAs(swalaTestUser(['view SMS history']))
        ->post(route('finance.sms.refresh-status', $log))
        ->assertRedirect();

    expect($log->fresh()->delivery_status)->toBe('failed')
        ->and($log->fresh()->provider_failure_reason)->toBe('Handset unreachable');
});

it('stores API credentials encrypted, masks them, and restricts SMS settings', function () {
    $manager = swalaTestSuperAdmin(['manage SMS settings']);
    $payload = [
        'swala_enabled' => '1', 'swala_test_mode' => '1', 'swala_sender_id' => 'KKKT',
        'swala_timeout' => '30', 'swala_api_key' => 'swl_test_secretvalue',
        'sms_language' => 'en', 'sms_auto_offering' => '0', 'sms_auto_contribution' => '0', 'sms_auto_other_income' => '0',
    ];
    foreach (['offering', 'contribution', 'sunday_offering'] as $type) {
        foreach (['en', 'sw'] as $language) {
            $payload["sms_template_{$type}_{$language}"] = __('finance.sms_template_'.$type, [], $language);
        }
    }

    $this->actingAs(swalaTestUser(['view finances']))->put(route('finance.sms.settings.update'), $payload)->assertForbidden();
    $this->actingAs($manager)->put(route('finance.sms.settings.update'), $payload)->assertSessionHasNoErrors();

    expect(FinancialSetting::getValue('swala_timeout'))->toBe('30');
    $this->assertDatabaseMissing('financial_settings', ['value' => 'swl_test_secretvalue']);
    $this->actingAs($manager)->get(route('settings.edit'))->assertOk()->assertDontSee('swl_test_secretvalue');

    $this->actingAs($manager)->put(route('finance.sms.settings.update'), array_merge($payload, ['swala_api_key' => 'bad-format']))->assertSessionHasErrors('swala_api_key');
    $this->actingAs($manager)->put(route('finance.sms.settings.update'), array_merge($payload, ['swala_timeout' => '61']))->assertSessionHasErrors('swala_timeout');
});

it('verifies a saved sender ID using the documented read-only endpoint', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response([
        'success' => true, 'data' => [['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => true]],
    ])]);
    $manager = swalaTestSuperAdmin([]);

    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))
        ->assertSessionHas('success', __('finance.swala_sender_list_refreshed'));
    expect(SmsSettings::senderVerification()['status'])->toBe('approved');
    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.swalasms.com/v1/sender-ids'
        && $request->hasHeader('Authorization', 'Bearer swl_live_unit_test_key'));
});

it('does not claim sender approval for unapproved, unusable, unavailable or malformed responses', function (int $status, array $response, ?string $message) {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response($response, $status)]);

    $result = $this->actingAs(swalaTestSuperAdmin([]))->post(route('finance.sms.settings.verify-sender'));
    if ($message === null) {
        $result->assertSessionHas('success', __('finance.swala_sender_list_refreshed'));
    } else {
        $result->assertSessionHasErrors(['sms' => __($message)]);
    }
    expect(SmsSettings::senderVerification()['status'])->not->toBe('approved');
    Http::assertSentCount(1);
})->with([
    'pending' => [200, ['success' => true, 'data' => [['sender_id' => 'KKKT', 'status' => 'pending', 'is_usable' => false]]], null],
    'unusable' => [200, ['success' => true, 'data' => [['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => false]]], null],
    'permission denied' => [403, ['success' => false], 'finance.swala_sender_verification_reasons.forbidden'],
    'malformed' => [200, ['success' => true, 'data' => [['sender_id' => 'KKKT', 'status' => 'approved']]], 'finance.swala_sender_verification_unavailable'],
]);

it('displays the sender verification result and timestamp without exposing the key', function (array $entries, string $status, string $reason) {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response(['success' => true, 'data' => $entries])]);
    $this->freezeTime();
    $manager = swalaTestSuperAdmin([]);

    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))->assertRedirect();

    expect(SmsSettings::senderVerification())->toMatchArray([
        'status' => $status, 'reason' => $reason, 'checked_at' => now()->toIso8601String(),
    ]);
    $this->get(route('settings.edit'))->assertOk()
        ->assertSee('KKKT')
        ->assertSee(__('finance.swala_sender_statuses.'.$status))
        ->assertSee(__('finance.swala_sender_verification_reasons.'.$reason))
        ->assertSee(now()->format('Y-m-d H:i'))
        ->assertSee(__('finance.swala_verify_again'))
        ->assertDontSee('swl_live_unit_test_key');
    $this->assertDatabaseMissing('financial_settings', ['value' => 'swl_live_unit_test_key']);
    Http::assertSentCount(1);
})->with([
    'approved' => [[['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => true]], 'approved', 'usable'],
    'pending' => [[['sender_id' => 'KKKT', 'status' => 'pending', 'is_usable' => false]], 'pending', 'pending'],
    'processing' => [[['sender_id' => 'KKKT', 'status' => 'processing', 'is_usable' => false]], 'processing', 'processing'],
    'rejected' => [[['sender_id' => 'KKKT', 'status' => 'rejected', 'is_usable' => false]], 'rejected', 'rejected'],
    'missing sender' => [[['sender_id' => 'OTHER', 'status' => 'approved', 'is_usable' => true]], 'unverified', 'not_found'],
    'approved but unusable' => [[['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => false]], 'unverified', 'not_usable'],
    'suspended' => [[['sender_id' => 'KKKT', 'status' => 'suspended', 'is_usable' => false]], 'unverified', 'not_usable'],
    'one approved route' => [[
        ['sender_id' => 'KKKT', 'status' => 'rejected', 'is_usable' => false],
        ['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => true],
    ], 'approved', 'usable'],
]);

it('shows a safe specific sender verification error for provider failures', function (int $status, array $body, string $reason) {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response($body, $status)]);
    $manager = swalaTestSuperAdmin([]);

    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))
        ->assertSessionHasErrors(['sms' => __('finance.swala_sender_verification_reasons.'.$reason)]);

    expect(SmsSettings::senderVerification()['status'])->toBe('unverified');
    $this->get(route('settings.edit'))->assertOk()
        ->assertSee(__('finance.swala_sender_verification_reasons.'.$reason))
        ->assertDontSee('private provider response')
        ->assertDontSee('swl_live_unit_test_key');
    Http::assertSentCount(1);
})->with([
    'unauthorized' => [401, ['success' => false, 'message' => 'private provider response'], 'unauthorized'],
    'forbidden' => [403, ['success' => false, 'message' => 'private provider response'], 'forbidden'],
    'endpoint missing' => [404, ['success' => false], 'endpoint_not_found'],
    'rate limited' => [429, ['success' => false], 'rate_limited'],
    'provider unavailable' => [503, ['success' => false], 'provider_unavailable'],
    'malformed' => [200, ['success' => true, 'data' => [['sender_id' => 'KKKT', 'status' => 'approved']]], 'unavailable'],
    'missing success flag' => [200, ['data' => [['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => true]]], 'unavailable'],
]);

it('marks sender verification unverified on a connection timeout', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::failedConnection()]);

    $this->actingAs(swalaTestSuperAdmin([]))->post(route('finance.sms.settings.verify-sender'))
        ->assertSessionHasErrors(['sms' => __('finance.swala_sender_verification_reasons.connection')]);

    expect(SmsSettings::senderVerification()['status'])->toBe('unverified');
});

it('lists senders without a configured ID and invalidates results for a different key', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response([
        'success' => true, 'data' => [['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => true]],
    ])]);
    $manager = swalaTestSuperAdmin([]);
    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))->assertRedirect();
    expect(SmsSettings::senderVerification()['status'])->toBe('approved');

    FinancialSetting::setValue('swala_api_key', 'swl_live_another_key');
    expect(SmsSettings::senderVerification()['status'])->toBe('unverified');
    FinancialSetting::setValue('swala_api_key', 'swl_live_unit_test_key');
    FinancialSetting::setValue('swala_sender_id', '');
    expect(SmsSettings::senderVerification()['status'])->toBe('unverified');
    $this->post(route('finance.sms.settings.verify-sender'))
        ->assertSessionHas('success', __('finance.swala_sender_list_refreshed'));

    expect(SmsSettings::senderVerification()['reason'])->toBe('missing_sender');
    Http::assertSentCount(2);
});

it('does not retry a provider sender ID rejection', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response([
        'success' => false, 'error_code' => 'SENDER_ID_REJECTED', 'message' => 'Sender ID rejected.',
    ], 422)]);
    $log = swalaTestLog();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class), app(SmsSegmentEstimator::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and($log->fresh()->provider_error_code)->toBe('SENDER_ID_REJECTED')
        ->and($log->fresh()->provider_acceptance_state)->toBe(SwalaSmsException::NOT_ACCEPTED)
        ->and($log->fresh()->error_message)->toContain('Sender ID rejected.');
    Http::assertSentCount(1);
});

it('matches a changed sender against the retained list and clears the list when the key changes', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response([
        'success' => true, 'data' => [['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => true]],
    ])]);
    $manager = swalaTestSuperAdmin([]);
    $payload = [
        'swala_enabled' => '1', 'swala_test_mode' => '0', 'swala_sender_id' => 'KKKT',
        'swala_timeout' => '30', 'swala_api_key' => '',
        'sms_language' => 'en', 'sms_auto_offering' => '0', 'sms_auto_contribution' => '0', 'sms_auto_other_income' => '0',
    ];
    foreach (['offering', 'contribution', 'sunday_offering'] as $type) {
        foreach (['en', 'sw'] as $language) {
            $payload["sms_template_{$type}_{$language}"] = __('finance.sms_template_'.$type, [], $language);
        }
    }

    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))->assertRedirect();
    $this->put(route('finance.sms.settings.update'), $payload)->assertSessionHasNoErrors();
    expect(SmsSettings::senderVerification()['status'])->toBe('approved')
        ->and(SmsSettings::apiKey())->toBe('swl_live_unit_test_key');

    $this->put(route('finance.sms.settings.update'), array_merge($payload, ['swala_sender_id' => 'OTHER']))->assertSessionHasNoErrors();
    expect(SmsSettings::senderVerification()['status'])->toBe('unverified');
    $this->put(route('finance.sms.settings.update'), $payload)->assertSessionHasNoErrors();
    expect(SmsSettings::senderVerification()['status'])->toBe('approved');

    $this->post(route('finance.sms.settings.verify-sender'))->assertRedirect();
    $this->put(route('finance.sms.settings.update'), array_merge($payload, ['swala_api_key' => 'swl_live_other_key']))->assertSessionHasNoErrors();
    $this->put(route('finance.sms.settings.update'), array_merge($payload, ['swala_api_key' => 'swl_live_unit_test_key']))->assertSessionHasNoErrors();
    expect(SmsSettings::senderVerification()['status'])->toBe('unverified');
    Http::assertSentCount(2);
});

it('restricts sender verification to Super Admin', function () {
    Http::preventStrayRequests();
    $this->actingAs(swalaTestUser(['manage SMS settings']))
        ->post(route('finance.sms.settings.verify-sender'))
        ->assertForbidden();
    Http::assertNothingSent();
});

it('lists all provider senders and matches the configured sender case-insensitively', function () {
    swalaTestConfigure();
    FinancialSetting::setValue('swala_sender_id', 'kkkt cms');
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response([
        'success' => true,
        'request_uid' => 'example-request',
        'data' => [
            ['sender_id' => 'EDUANA DEVS', 'country_code' => '+255', 'country_name' => 'Tanzania, United Republic of',
                'status' => 'processing', 'is_default' => false, 'is_usable' => false, 'purpose' => 'Notification'],
            ['sender_id' => 'KKKT CMS', 'country_code' => '+255', 'country_name' => 'Tanzania, United Republic of',
                'status' => 'approved', 'is_default' => false, 'is_usable' => true, 'purpose' => 'Notification',
                'updated_at' => '2026-11-06T08:00:00+00:00'],
            ['sender_id' => 'REJECTED ID', 'status' => 'rejected', 'is_usable' => false],
            ['sender_id' => 'FUTURE ID', 'status' => 'reviewing', 'is_usable' => false],
        ],
    ])]);
    $manager = swalaTestSuperAdmin([]);

    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))->assertRedirect();

    expect(SmsSettings::senderVerification()['status'])->toBe('approved')
        ->and(SmsSettings::senderList()['senders'])->toHaveCount(4)
        ->and(SmsSettings::senderId())->toBe('kkkt cms');
    $this->get(route('settings.edit'))->assertOk()
        ->assertSee('EDUANA DEVS')->assertSee('KKKT CMS')->assertSee('REJECTED ID')->assertSee('FUTURE ID')
        ->assertSee(__('finance.swala_sender_statuses.processing'))
        ->assertSee(__('finance.swala_sender_statuses.approved'))
        ->assertSee(__('finance.swala_sender_statuses.rejected'))
        ->assertSee('reviewing')->assertSee('Tanzania, United Republic of')->assertSee('+255')
        ->assertSee('Notification')->assertSee('2026-11-06T08:00:00+00:00');
    Http::assertSentCount(1);
});

it('saves only an explicitly selected approved and usable sender ID', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response(['success' => true, 'data' => [
        ['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => true],
        ['sender_id' => 'SECOND', 'status' => 'approved', 'is_usable' => true],
        ['sender_id' => 'PROCESSING', 'status' => 'processing', 'is_usable' => false],
        ['sender_id' => 'NOT USABLE', 'status' => 'approved', 'is_usable' => false],
    ]])]);
    $manager = swalaTestSuperAdmin([]);
    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))->assertRedirect();

    $this->post(route('finance.sms.settings.select-sender'), ['sender_id' => 'PROCESSING'])->assertSessionHasErrors('sender_id');
    $this->post(route('finance.sms.settings.select-sender'), ['sender_id' => 'NOT USABLE'])->assertSessionHasErrors('sender_id');
    $this->post(route('finance.sms.settings.select-sender'), ['sender_id' => 'NOT LISTED'])->assertSessionHasErrors('sender_id');
    expect(SmsSettings::senderId())->toBe('KKKT');
    $this->post(route('finance.sms.settings.select-sender'), ['sender_id' => 'SECOND'])->assertSessionHasNoErrors();

    expect(SmsSettings::senderId())->toBe('SECOND')
        ->and(SmsSettings::senderVerification()['status'])->toBe('approved');
    $this->actingAs(swalaTestUser(['manage SMS settings']))
        ->post(route('finance.sms.settings.select-sender'), ['sender_id' => 'KKKT'])->assertForbidden();
    Http::assertSentCount(1);
});

it('keeps the previous sender list visible but not currently verified after a failed refresh', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::sequence()
        ->push(['success' => true, 'data' => [['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => true]]], 200)
        ->push(['success' => false], 403)]);
    $manager = swalaTestSuperAdmin([]);
    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))->assertRedirect();
    $lastSuccess = SmsSettings::senderList()['success_at'];

    $this->post(route('finance.sms.settings.verify-sender'))
        ->assertSessionHasErrors(['sms' => __('finance.swala_sender_verification_reasons.forbidden')]);

    expect(SmsSettings::senderList()['senders'])->toHaveCount(1)
        ->and(SmsSettings::senderList()['success_at'])->toBe($lastSuccess)
        ->and(SmsSettings::senderVerification()['status'])->toBe('unverified');
    $this->get(route('settings.edit'))->assertOk()
        ->assertSee('KKKT')->assertSee(__('finance.swala_sender_list_stale'))
        ->assertSee(__('finance.swala_sender_verification_reasons.forbidden'));
    $this->post(route('finance.sms.settings.select-sender'), ['sender_id' => 'KKKT'])->assertSessionHasErrors('sender_id');
    Http::assertSentCount(2);
});

it('shows an empty provider sender list without claiming approval', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response(['success' => true, 'data' => []])]);
    $manager = swalaTestSuperAdmin([]);

    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))->assertRedirect();

    expect(SmsSettings::senderVerification()['reason'])->toBe('not_found');
    $this->get(route('settings.edit'))->assertOk()->assertSee(__('finance.swala_sender_list_empty'));
    Http::assertSentCount(1);
});

it('stores a complete large sender list and accepts a 32-character approved sender', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    $name = str_repeat('A', 32);
    $senders = array_map(fn (int $number): array => [
        'sender_id' => 'SENDER '.$number, 'status' => 'processing', 'is_usable' => false,
        'purpose' => str_repeat('N', 100),
    ], range(1, 500));
    $senders[] = ['sender_id' => $name, 'status' => 'approved', 'is_usable' => true];
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::response(['success' => true, 'data' => $senders])]);
    $manager = swalaTestSuperAdmin([]);

    $this->actingAs($manager)->post(route('finance.sms.settings.verify-sender'))->assertRedirect();
    $this->post(route('finance.sms.settings.select-sender'), ['sender_id' => $name])->assertSessionHasNoErrors();

    expect(SmsSettings::senderList()['senders'])->toHaveCount(501)
        ->and(SmsSettings::senderId())->toBe($name)
        ->and(SmsSettings::senderVerification()['status'])->toBe('approved');
    $log = swalaTestLog(['sender_id' => $name]);
    expect($log->fresh()->sender_id)->toBe($name);
    Http::assertSentCount(1);
});

it('retains the last good list when the provider returns malformed JSON', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/sender-ids' => Http::sequence()
        ->push(['success' => true, 'data' => [['sender_id' => 'KKKT', 'status' => 'approved', 'is_usable' => true]]], 200)
        ->push('{invalid json', 200)]);
    $this->actingAs(swalaTestSuperAdmin([]))->post(route('finance.sms.settings.verify-sender'))->assertRedirect();

    $this->post(route('finance.sms.settings.verify-sender'))
        ->assertSessionHasErrors(['sms' => __('finance.swala_sender_verification_reasons.unavailable')]);

    expect(SmsSettings::senderList()['senders'])->toHaveCount(1)
        ->and(SmsSettings::senderVerification()['status'])->toBe('unverified');
    Http::assertSentCount(2);
});

it('warns about an unverified sender and still sends the configured ID when explicitly requested', function () {
    swalaTestConfigure();
    FinancialSetting::setValue('swala_sender_id', 'MY SENDER');
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v2/sms/send-bulk' => Http::response(swalaTestBulkMessage(), 202)]);
    $user = swalaTestUser(['send SMS']);

    $this->actingAs($user)->get(route('finance.sms.compose'))->assertOk()->assertSee(__('finance.swala_sender_send_warning'));
    app(SwalaSmsService::class)->sendMessage('0712345678', 'Hello', (string) Str::uuid());

    Http::assertSent(fn (HttpRequest $request): bool => $request['sender_id'] === 'MY SENDER');
    Http::assertSentCount(1);
});

it('tests the connection through the documented balance endpoint only', function () {
    swalaTestConfigure();
    Http::preventStrayRequests();
    Http::fake(['https://api.swalasms.com/v1/balance' => Http::response(['success' => true, 'data' => ['balance' => 42, 'currency' => 'SMS', 'countries' => []]], 200)]);
    $manager = swalaTestSuperAdmin(['manage SMS settings']);

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

it('filters SMS history by preset dates and sender ID', function () {
    $this->travelTo('2026-11-05 12:00:00');
    $oldLog = swalaTestLog([
        'provider_message_id' => 'outside-range',
        'sender_id' => 'KKKT',
    ]);
    $oldLog->forceFill(['created_at' => now()->subDays(8)])->save();
    swalaTestLog([
        'provider_message_id' => 'inside-range',
        'sender_id' => 'KKKT',
    ]);
    swalaTestLog([
        'provider_message_id' => 'different-sender',
        'sender_id' => 'OTHER',
    ]);

    $this->actingAs(swalaTestUser(['view SMS history']))
        ->get(route('finance.sms.index', ['period' => 'last_7_days', 'sender_id' => 'KKKT']))
        ->assertOk()
        ->assertSee('inside-range')
        ->assertDontSee('outside-range')
        ->assertDontSee('different-sender');
});

it('shows up to three recent status events per SMS log alongside attempt history', function () {
    $this->travelTo('2026-11-06 07:15:00');
    $noEvents = swalaTestLog(['provider_message_id' => 'no-events']);
    $oneEvent = swalaTestLog(['provider_message_id' => 'one-event']);
    $multipleEvents = swalaTestLog(['provider_message_id' => 'multiple-events']);
    $anotherLog = swalaTestLog(['provider_message_id' => 'another-log']);

    $oneEvent->recordStatusEvent(null, 'queued', 'submission');
    foreach (['queued', 'processing', 'sent', 'delivered'] as $status) {
        $multipleEvents->recordStatusEvent(null, $status, 'poll');
    }
    foreach (['queued', 'sent', 'failed', 'delivered'] as $status) {
        $anotherLog->recordStatusEvent(null, $status, 'poll');
    }
    $multipleEvents->smsAttempts()->create([
        'attempt_number' => 1,
        'status' => 'failed',
        'error_message' => 'Attempt history example',
        'started_at' => now(),
    ]);

    $response = $this->actingAs(swalaTestUser(['view SMS history']))
        ->get(route('finance.sms.index'))
        ->assertOk()
        ->assertSee(__('finance.delivery_statuses.delivered'))
        ->assertSee('2026-11-06 07:15')
        ->assertSee('Attempt history example');

    $logs = $response->viewData('logs')->getCollection()->keyBy('id');

    expect($logs[$noEvents->id]->statusEvents)->toHaveCount(0)
        ->and($logs[$oneEvent->id]->statusEvents->pluck('status')->all())->toBe(['queued'])
        ->and($logs[$multipleEvents->id]->statusEvents->pluck('status')->all())->toBe(['delivered', 'sent', 'processing'])
        ->and($logs[$anotherLog->id]->statusEvents->pluck('status')->all())->toBe(['delivered', 'failed', 'sent'])
        ->and($logs[$multipleEvents->id]->smsAttempts->pluck('attempt_number')->all())->toBe([1]);
});

it('keeps SMS history filters when paginating status events', function () {
    for ($i = 1; $i <= 26; $i++) {
        $log = swalaTestLog([
            'provider_message_id' => 'filtered-'.$i,
            'sender_id' => 'KKKT',
        ]);
        $log->recordStatusEvent(null, 'queued', 'submission');
    }
    swalaTestLog(['provider_message_id' => 'other-sender', 'sender_id' => 'OTHER']);

    $response = $this->actingAs(swalaTestUser(['view SMS history']))
        ->get(route('finance.sms.index', ['sender_id' => 'KKKT', 'page' => 2]))
        ->assertOk()
        ->assertSee('filtered-1')
        ->assertDontSee('filtered-26')
        ->assertDontSee('other-sender');

    $logs = $response->viewData('logs');
    expect($logs->total())->toBe(26)
        ->and($logs->currentPage())->toBe(2)
        ->and($logs->first()->statusEvents->pluck('status')->all())->toBe(['queued']);
});

it('rejects SMS status events without an existing log', function () {
    expect(fn () => FinancialSmsStatusEvent::query()->create([
        'financial_sms_log_id' => 999999,
        'status' => 'queued',
        'source' => 'poll',
    ]))->toThrow(QueryException::class);
});

it('completes an interrupted SMS status events migration without losing events', function () {
    $log = swalaTestLog();
    $log->recordStatusEvent(null, 'queued', 'submission');
    $event = $log->statusEvents()->firstOrFail();
    Schema::table('financial_sms_status_events', function ($table): void {
        $table->dropIndex('fin_sms_events_log_created_idx');
        $table->dropIndex('fin_sms_events_status_created_idx');
    });

    $migration = require database_path('migrations/2026_11_06_012752_create_financial_sms_status_events_table.php');
    $migration->up();
    $migration->up();

    expect(Schema::hasIndex('financial_sms_status_events', ['financial_sms_log_id', 'created_at']))->toBeTrue()
        ->and(Schema::hasIndex('financial_sms_status_events', ['status', 'created_at']))->toBeTrue();
    $this->assertDatabaseHas('financial_sms_status_events', [
        'id' => $event->id,
        'financial_sms_log_id' => $log->id,
        'status' => 'queued',
    ]);
});

it('does not duplicate unchanged provider status events', function () {
    $log = swalaTestLog(['delivery_status' => 'queued']);

    expect($log->applyProviderStatus('sent'))->toBeTrue()
        ->and($log->applyProviderStatus('sent'))->toBeFalse()
        ->and($log->statusEvents()->count())->toBe(1);
});

it('exports filtered SMS history only for users with export permission', function () {
    swalaTestLog([
        'phone' => '+255712345678',
        'message' => '=1+1',
        'provider_message_id' => 'export-1',
        'sender_id' => 'KKKT',
        'encoding' => 'GSM-7',
    ]);
    swalaTestLog([
        'provider_message_id' => 'export-2',
        'sender_id' => 'OTHER',
    ]);

    $this->actingAs(swalaTestUser(['view SMS history']))
        ->get(route('finance.sms.export', ['sender_id' => 'KKKT']))
        ->assertForbidden();

    $response = $this->actingAs(swalaTestUser(['export SMS history']))
        ->get(route('finance.sms.export', ['sender_id' => 'KKKT']));

    $response->assertOk()->assertDownload();

    expect($response->streamedContent())
        ->toContain('export-1')
        ->toContain("'=1+1")
        ->not->toContain('export-2');
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
