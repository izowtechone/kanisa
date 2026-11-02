<?php

use App\Jobs\PrepareBulkSmsCampaign;
use App\Jobs\SendBulkSmsBatch;
use App\Jobs\SendFinancialSms;
use App\Models\BulkSmsCampaign;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsAttempt;
use App\Models\FinancialSmsLog;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\BulkSmsBatchDispatcher;
use App\Services\BulkSmsRecipientSelector;
use App\Services\FinancialSmsMessageBuilder;
use App\Services\SmsSegmentEstimator;
use App\Services\SwalaSmsService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function bulkSmsCampaignTestUser(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function bulkSmsCampaignTestMember(array $overrides = []): Member
{
    $memberNumber = Member::withTrashed()->count() + 1;

    return Member::query()->create(array_merge([
        'membership_number' => 'KKKT-CAMPAIGN-'.str_pad((string) $memberNumber, 4, '0', STR_PAD_LEFT),
        'envelope_number' => str_pad((string) (1000 + $memberNumber), 5, '0', STR_PAD_LEFT),
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
    ], $overrides));
}

function bulkSmsCampaignTestCampaign(User $creator, array $overrides = []): BulkSmsCampaign
{
    return BulkSmsCampaign::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Membership update',
        'message' => 'Hello {{member_name}}',
        'language' => 'en',
        'status' => 'queued',
        'selection_mode' => 'selected',
        'selection_criteria' => [],
        'created_by' => $creator->id,
    ], $overrides));
}

function enableBulkSmsCampaignDelivery(): void
{
    FinancialSetting::setValue('swala_enabled', '1');
    FinancialSetting::setValue('swala_test_mode', '0');
    FinancialSetting::setValue('swala_api_key', 'swl_live_campaignkey');
    FinancialSetting::setValue('swala_sender_id', 'KKKT');
}

it('queues a campaign for an authorized user with an eligible opted-in recipient', function () {
    Queue::fake([PrepareBulkSmsCampaign::class]);
    Http::preventStrayRequests();

    $user = bulkSmsCampaignTestUser(['send bulk SMS']);
    $member = bulkSmsCampaignTestMember(['sms_opt_in' => true]);
    enableBulkSmsCampaignDelivery();

    $response = $this->actingAs($user)
        ->post(route('finance.sms.campaigns.store'), [
            'name' => 'Membership update',
            'message' => 'Hello {{member_name}}',
            'language' => 'en',
            'selection_mode' => 'selected',
            'member_ids' => [$member->id],
            'confirmation' => '1',
        ])
        ->assertRedirect();

    $campaign = BulkSmsCampaign::query()->firstOrFail();

    expect($campaign->name)->toBe('Membership update')
        ->and($campaign->selection_criteria)->toBe(['member_ids' => [$member->id]])
        ->and($campaign->status)->toBe('queued');
    $response->assertRedirect(route('finance.sms.campaigns.show', $campaign));
    $this->get(route('finance.sms.campaigns.show', $campaign))
        ->assertOk()
        ->assertSee($campaign->name)
        ->assertSee('href="'.route('finance.sms.campaigns.create').'"', false)
        ->assertDontSee('href="'.route('finance.sms.campaigns.index').'"', false);

    $otherCreator = bulkSmsCampaignTestUser(['send bulk SMS']);
    $otherCampaign = bulkSmsCampaignTestCampaign($otherCreator, [
        'name' => 'Private campaign',
    ]);
    $this->get(route('finance.sms.campaigns.show', $otherCampaign))
        ->assertForbidden();

    Queue::assertPushed(PrepareBulkSmsCampaign::class, fn (PrepareBulkSmsCampaign $job): bool => $job->campaignId === $campaign->id);
    Http::assertNothingSent();
});

it('forbids users without the bulk SMS permission and rejects selections without consent', function () {
    $unapprovedUser = bulkSmsCampaignTestUser(['view SMS campaigns']);

    $this->actingAs($unapprovedUser)
        ->get(route('finance.sms.campaigns.index'))
        ->assertOk()
        ->assertSee('href="'.route('finance.sms.campaigns.index').'"', false)
        ->assertDontSee('href="'.route('finance.sms.campaigns.create').'"', false);

    $this->actingAs($unapprovedUser)
        ->get(route('finance.sms.campaigns.create'))
        ->assertForbidden();

    Queue::fake([PrepareBulkSmsCampaign::class]);
    $user = bulkSmsCampaignTestUser(['send bulk SMS']);
    $member = bulkSmsCampaignTestMember();
    enableBulkSmsCampaignDelivery();

    $this->actingAs($user)
        ->from(route('finance.sms.campaigns.create'))
        ->post(route('finance.sms.campaigns.store'), [
            'name' => 'Membership update',
            'message' => 'Hello',
            'language' => 'en',
            'selection_mode' => 'selected',
            'member_ids' => [$member->id],
            'confirmation' => '1',
        ])
        ->assertSessionHasErrors('campaign');

    $this->assertDatabaseCount('bulk_sms_campaigns', 0);
    Queue::assertNothingPushed();
});

it('renders campaign creation controls and supported personalization placeholders', function () {
    $user = bulkSmsCampaignTestUser(['send bulk SMS']);
    $member = bulkSmsCampaignTestMember(['sms_opt_in' => true]);
    enableBulkSmsCampaignDelivery();

    $this->actingAs($user)
        ->get(route('finance.sms.campaigns.create'))
        ->assertOk()
        ->assertSee($member->membership_number)
        ->assertSee('smsCampaignSelector', false)
        ->assertSee(__('finance.sms_campaign_confirm_send', ['count' => ':count']))
        ->assertSee('{{member_name}}')
        ->assertSee(__('finance.sms_campaign_selected', ['count' => ':count']));
});

it('prepares only opted-in recipients with valid phones and personalizes queued messages', function () {
    Queue::fake([SendBulkSmsBatch::class]);
    Http::preventStrayRequests();

    $creator = bulkSmsCampaignTestUser(['send bulk SMS']);
    $member = bulkSmsCampaignTestMember([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Mushi',
        'sms_opt_in' => true,
    ]);
    $invalidPhoneMember = bulkSmsCampaignTestMember([
        'membership_number' => 'KKKT-CAMPAIGN-0002',
        'envelope_number' => '01002',
        'phone' => '123',
        'sms_opt_in' => true,
    ]);
    $optedOutMember = bulkSmsCampaignTestMember([
        'membership_number' => 'KKKT-CAMPAIGN-0003',
        'envelope_number' => '01003',
        'sms_opt_in' => false,
    ]);
    $campaign = bulkSmsCampaignTestCampaign($creator, [
        'message' => '{{member_name}} ({{member_number}}) Ã‚Â· {{church_name}}',
        'selection_criteria' => [
            'member_ids' => [$member->id, $invalidPhoneMember->id, $optedOutMember->id],
        ],
    ]);
    Setting::setValue('church_name', 'KKKT Dodoma');

    (new PrepareBulkSmsCampaign($campaign->id))->handle(
        app(BulkSmsRecipientSelector::class),
        app(SwalaSmsService::class),
        app(BulkSmsBatchDispatcher::class),
        app(FinancialSmsMessageBuilder::class),
        app(SmsSegmentEstimator::class),
    );

    $log = FinancialSmsLog::query()->firstOrFail();
    expect(FinancialSmsLog::query()->count())->toBe(1)
        ->and($log->member_id)->toBe($member->id)
        ->and($log->phone)->toBe('255712345678')
        ->and($log->message)->toBe('Amina Mushi (KKKT-CAMPAIGN-0001) Ã‚Â· KKKT Dodoma')
        ->and($campaign->fresh()->recipient_count)->toBe(1)
        ->and($campaign->fresh()->invalid_phone_count)->toBe(1);

    Queue::assertPushed(SendBulkSmsBatch::class, fn (SendBulkSmsBatch $job): bool => $job->campaignId === $campaign->id);
    Http::assertNothingSent();
});

it('skips a queued bulk message when the member withdraws consent before delivery', function () {
    Http::preventStrayRequests();

    $creator = bulkSmsCampaignTestUser(['send bulk SMS']);
    $member = bulkSmsCampaignTestMember(['sms_opt_in' => true]);
    $campaign = bulkSmsCampaignTestCampaign($creator);
    $log = FinancialSmsLog::query()->create([
        'bulk_sms_campaign_id' => $campaign->id,
        'member_id' => $member->id,
        'notification_type' => 'bulk_campaign',
        'message' => 'Hello Neema',
        'created_by' => $creator->id,
        'phone' => '255712345678',
        'status' => 'queued',
        'is_test' => false,
        'sms_segments' => 1,
    ]);
    $member->update(['sms_opt_in' => false]);

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class));

    expect($log->fresh()->status)->toBe('cancelled')
        ->and($campaign->fresh()->status)->toBe('completed');
    $this->assertDatabaseCount('financial_sms_attempts', 0);
    Http::assertNothingSent();
});

it('marks a campaign recipient failed when SwalaSMS rejects the submission', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.swalasms.com/v1/sms/messages' => Http::response(['success' => false, 'message' => 'Rejected', 'error_code' => 'INVALID_RECIPIENT', 'acceptance_state' => 'DEFINITELY_NOT_ACCEPTED'], 422),
    ]);

    $creator = bulkSmsCampaignTestUser(['send bulk SMS']);
    $member = bulkSmsCampaignTestMember(['sms_opt_in' => true]);
    $campaign = bulkSmsCampaignTestCampaign($creator);
    $log = FinancialSmsLog::query()->create([
        'bulk_sms_campaign_id' => $campaign->id,
        'member_id' => $member->id,
        'notification_type' => 'bulk_campaign',
        'message' => 'Hello Neema',
        'created_by' => $creator->id,
        'phone' => '255712345678',
        'status' => 'queued',
        'is_test' => false,
        'sms_segments' => 1,
    ]);
    enableBulkSmsCampaignDelivery();

    (new SendFinancialSms($log->id))->handle(app(SwalaSmsService::class));

    expect($log->fresh()->status)->toBe('failed')
        ->and(FinancialSmsAttempt::query()->firstOrFail()->status)->toBe('failed')
        ->and($campaign->fresh()->status)->toBe('failed');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.swalasms.com/v1/sms/messages');
});

it('retries failed campaign recipients without re-queuing cancelled recipients', function () {
    Queue::fake([SendBulkSmsBatch::class]);
    Http::preventStrayRequests();

    $user = bulkSmsCampaignTestUser(['view SMS campaigns', 'retry failed SMS']);
    $failedMember = bulkSmsCampaignTestMember(['sms_opt_in' => true]);
    $cancelledMember = bulkSmsCampaignTestMember(['sms_opt_in' => true]);
    $campaign = bulkSmsCampaignTestCampaign($user, ['status' => 'failed']);
    $failedLog = FinancialSmsLog::query()->create([
        'bulk_sms_campaign_id' => $campaign->id,
        'member_id' => $failedMember->id,
        'notification_type' => 'bulk_campaign',
        'message' => 'Failed message',
        'created_by' => $user->id,
        'phone' => '255712345678',
        'status' => 'failed',
        'is_test' => false,
        'sms_segments' => 1,
    ]);
    $cancelledLog = FinancialSmsLog::query()->create([
        'bulk_sms_campaign_id' => $campaign->id,
        'member_id' => $cancelledMember->id,
        'notification_type' => 'bulk_campaign',
        'message' => 'Skipped message',
        'created_by' => $user->id,
        'phone' => '255712345679',
        'status' => 'cancelled',
        'is_test' => false,
        'sms_segments' => 1,
    ]);
    enableBulkSmsCampaignDelivery();

    $this->actingAs($user)
        ->get(route('finance.sms.campaigns.show', $campaign))
        ->assertOk()
        ->assertSee(__('finance.confirm_retry_sms'))
        ->assertSee(__('finance.sms_campaign_submitted'));

    $this->actingAs($user)
        ->post(route('finance.sms.campaigns.retry-failed', $campaign))
        ->assertRedirect(route('finance.sms.campaigns.show', $campaign));

    expect($failedLog->fresh()->status)->toBe('queued')
        ->and($cancelledLog->fresh()->status)->toBe('cancelled');
    Queue::assertPushed(SendBulkSmsBatch::class, fn (SendBulkSmsBatch $job): bool => $job->campaignId === $campaign->id);
    Http::assertNothingSent();
});

it('rejects unsupported personalization placeholders before creating a campaign', function () {
    Queue::fake([PrepareBulkSmsCampaign::class]);

    $user = bulkSmsCampaignTestUser(['send bulk SMS']);
    $member = bulkSmsCampaignTestMember(['sms_opt_in' => true]);
    enableBulkSmsCampaignDelivery();

    $this->actingAs($user)
        ->from(route('finance.sms.campaigns.create'))
        ->post(route('finance.sms.campaigns.store'), [
            'name' => 'Membership update',
            'message' => 'Hello {{unknown_field}}',
            'language' => 'en',
            'selection_mode' => 'selected',
            'member_ids' => [$member->id],
            'confirmation' => '1',
        ])
        ->assertSessionHasErrors('message');

    $this->assertDatabaseCount('bulk_sms_campaigns', 0);
    Queue::assertNothingPushed();
});

it('renders the campaign list with no campaigns', function () {
    $user = bulkSmsCampaignTestUser(['view SMS campaigns']);

    $this->actingAs($user)
        ->get(route('finance.sms.campaigns.index'))
        ->assertOk()
        ->assertViewHas('campaigns', fn ($campaigns): bool => $campaigns->total() === 0);
});

it('forbids campaign history for users without the view permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('finance.sms.campaigns.index'))
        ->assertForbidden();
});

it('reports real recipient statistics per campaign in the history list', function () {
    $user = bulkSmsCampaignTestUser(['view SMS campaigns']);
    $campaign = bulkSmsCampaignTestCampaign($user, ['recipient_count' => 3]);

    foreach (['accepted', 'failed', 'queued'] as $status) {
        $member = bulkSmsCampaignTestMember(['sms_opt_in' => true]);
        FinancialSmsLog::query()->create([
            'bulk_sms_campaign_id' => $campaign->id,
            'member_id' => $member->id,
            'notification_type' => 'bulk_campaign',
            'phone' => '255712345678',
            'message' => 'Hello',
            'status' => $status,
        ]);
    }

    expect($campaign->recipients()->count())->toBe(3);

    $this->actingAs($user)
        ->get(route('finance.sms.campaigns.index'))
        ->assertOk()
        ->assertViewHas('campaigns', function ($campaigns) use ($campaign): bool {
            $row = $campaigns->firstWhere('id', $campaign->id);

            return $row->submitted_count === 1
                && $row->failed_count === 1
                && $row->pending_count === 1;
        });
});

it('rejects an invalid campaign status filter', function () {
    $user = bulkSmsCampaignTestUser(['view SMS campaigns']);

    $this->actingAs($user)
        ->get(route('finance.sms.campaigns.index', ['status' => 'bogus']))
        ->assertSessionHasErrors('status');
});

it('rejects campaign creation with missing data without creating a campaign', function () {
    Queue::fake([PrepareBulkSmsCampaign::class]);
    $user = bulkSmsCampaignTestUser(['send bulk SMS']);

    $this->actingAs($user)
        ->post(route('finance.sms.campaigns.store'), [])
        ->assertSessionHasErrors(['name', 'message']);

    $this->assertDatabaseCount('bulk_sms_campaigns', 0);
    Queue::assertNothingPushed();
});
