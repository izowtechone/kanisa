<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBulkSmsCampaignRequest;
use App\Jobs\PrepareBulkSmsCampaign;
use App\Models\BulkSmsCampaign;
use App\Models\Department;
use App\Models\Group;
use App\Services\BulkSmsBatchDispatcher;
use App\Services\BulkSmsRecipientSelector;
use App\Services\SmsSettings;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class FinancialSmsCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,scheduled,queued,processing,completed,partially_failed,failed,cancelled'],
        ]);

        $campaigns = BulkSmsCampaign::query()
            ->with('creator:id,name')
            ->withCount([
                'recipients as submitted_count' => fn (Builder $query) => $query->where('status', 'accepted'),
                'recipients as failed_count' => fn (Builder $query) => $query->where('status', 'failed'),
                'recipients as pending_count' => fn (Builder $query) => $query->whereIn('status', ['queued', 'sending']),
            ])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('finance.sms.campaigns.index', compact('campaigns', 'filters'));
    }

    public function create(Request $request, BulkSmsRecipientSelector $selector, SwalaSmsService $gateway): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'status' => ['nullable', 'in:active,inactive'],
            'gender' => ['nullable', 'in:Male,Female'],
            'registered_from' => ['nullable', 'date_format:Y-m-d'],
            'registered_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:registered_from'],
        ]);

        $members = $selector->query($filters)
            ->with('groups:id,department_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $validPhoneIds = [];

        foreach ($members as $member) {
            try {
                $gateway->normalizePhone($member->phone);
                $validPhoneIds[] = $member->id;
            } catch (\RuntimeException) {
                continue;
            }
        }

        return view('finance.sms.campaigns.create', [
            'members' => $members,
            'filters' => $filters,
            'eligibleCount' => $selector->query($filters)->count(),
            'validPhoneIds' => $validPhoneIds,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'groups' => Group::query()->orderBy('name')->get(['id', 'department_id', 'name']),
            'liveConfigured' => SmsSettings::isLiveConfigured(),
            'senderVerification' => SmsSettings::senderVerification(),
            'senderId' => SmsSettings::senderId(),
        ]);
    }

    public function store(
        CreateBulkSmsCampaignRequest $request,
        BulkSmsRecipientSelector $selector,
    ): RedirectResponse {
        $this->ensureLiveConfiguration();

        $validated = $request->validated();
        $filterFields = [
            'search',
            'department_id',
            'group_id',
            'status',
            'gender',
            'registered_from',
            'registered_to',
        ];
        $criteria = $validated['selection_mode'] === 'selected'
            ? ['member_ids' => array_map('intval', $validated['member_ids'])]
            : Arr::only($validated, $filterFields);

        $candidateCount = $selector->query($criteria)->count();

        if ($candidateCount === 0) {
            throw ValidationException::withMessages([
                'campaign' => __('finance.sms_no_eligible_recipients'),
            ]);
        }

        $scheduledAt = isset($validated['scheduled_at'])
            ? Carbon::parse($validated['scheduled_at'])
            : null;
        $campaign = DB::transaction(fn (): BulkSmsCampaign => BulkSmsCampaign::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'message' => $validated['message'],
            'language' => $validated['language'],
            'status' => $scheduledAt?->isFuture() ? 'scheduled' : 'queued',
            'selection_mode' => $validated['selection_mode'],
            'selection_criteria' => $criteria,
            'recipient_count' => $candidateCount,
            'created_by' => $request->user()->id,
            'scheduled_at' => $scheduledAt,
        ]));

        try {
            PrepareBulkSmsCampaign::dispatch($campaign->id)->onQueue('bulk')->delay($scheduledAt);
        } catch (Throwable) {
            $campaign->forceFill([
                'status' => 'failed',
                'error_message' => 'Campaign preparation could not be added to the queue.',
                'completed_at' => now(),
            ])->save();

            return redirect()->route('finance.sms.campaigns.show', $campaign)
                ->withErrors(['campaign' => __('finance.sms_campaign_queue_failed')]);
        }

        return redirect()->route('finance.sms.campaigns.show', $campaign)
            ->with('success', $scheduledAt?->isFuture()
                ? __('finance.sms_campaign_scheduled')
                : __('finance.sms_campaign_queued'));
    }

    private function ensureLiveConfiguration(): void
    {
        if (! SmsSettings::isLiveConfigured()) {
            throw ValidationException::withMessages([
                'campaign' => __('finance.bulk_sms_requires_live_configuration'),
            ]);
        }
    }

    public function show(Request $request, BulkSmsCampaign $campaign): View
    {
        abort_unless(
            $request->user()->can('view SMS campaigns')
                || (
                    $campaign->created_by === $request->user()->id
                    && $request->user()->can('send bulk SMS')
                ),
            403,
        );

        $recipients = $campaign->recipients()
            ->with('member:id,first_name,middle_name,last_name,membership_number')
            ->with('smsAttempts')
            ->orderBy('id')
            ->paginate(25);

        $duplicateCount = $campaign->recipients()->where('provider_error_code', 'DUPLICATE_RECIPIENT')->count();

        return view('finance.sms.campaigns.show', [
            'campaign' => $campaign,
            'recipients' => $recipients,
            'duplicateCount' => $duplicateCount,
            'senderId' => SmsSettings::senderId(),
            'senderVerification' => SmsSettings::senderVerification(),
        ]);
    }

    public function cancel(BulkSmsCampaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($campaign): void {
            $lockedCampaign = BulkSmsCampaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (! in_array($lockedCampaign->status, ['scheduled', 'queued', 'processing'], true)) {
                throw ValidationException::withMessages([
                    'campaign' => __('finance.sms_campaign_cannot_cancel'),
                ]);
            }

            $lockedCampaign->recipients()
                ->where('status', 'queued')
                ->update([
                    'status' => 'cancelled',
                    'error_message' => __('finance.sms_campaign_cancelled_by_admin'),
                    'updated_at' => now(),
                ]);

            $lockedCampaign->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'completed_at' => now(),
            ])->save();
        });

        return redirect()->route('finance.sms.campaigns.show', $campaign)
            ->with('success', __('finance.sms_campaign_cancelled'));
    }

    public function retryFailed(BulkSmsCampaign $campaign, BulkSmsBatchDispatcher $dispatcher): RedirectResponse
    {
        $this->ensureLiveConfiguration();

        $failedIds = DB::transaction(function () use ($campaign): array {
            $lockedCampaign = BulkSmsCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $logs = $lockedCampaign->recipients()->where('status', 'failed')->lockForUpdate()->get();

            if ($logs->isEmpty()) {
                throw ValidationException::withMessages([
                    'campaign' => __('finance.sms_campaign_no_failed_recipients'),
                ]);
            }

            if ($logs->contains('provider_acceptance_state', SwalaSmsException::UNKNOWN)) {
                throw ValidationException::withMessages([
                    'campaign' => __('finance.sms_unknown_outcome'),
                ]);
            }

            foreach ($logs as $log) {
                $log->forceFill([
                    'status' => 'queued',
                    'error_message' => null,
                ])->save();
            }

            $lockedCampaign->forceFill([
                'status' => 'queued',
                'completed_at' => null,
                'error_message' => null,
            ])->save();

            return $logs->modelKeys();
        });

        $dispatcher->dispatch($campaign, $failedIds);

        $campaign->refresh()->refreshStatus();

        return redirect()->route('finance.sms.campaigns.show', $campaign)
            ->with('success', __('finance.sms_campaign_failed_retries_queued', ['count' => count($failedIds)]));
    }
}
