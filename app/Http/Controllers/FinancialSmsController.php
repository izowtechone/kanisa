<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendManualSmsRequest;
use App\Http\Requests\SendTestSmsRequest;
use App\Jobs\SendFinancialSms;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use App\Services\FinancialSmsMessageBuilder;
use App\Services\SmsSegmentEstimator;
use App\Services\SmsSettings;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FinancialSmsController extends Controller
{
    public function compose(): View
    {
        return view('finance.sms.compose', [
            'liveConfigured' => SmsSettings::isLiveConfigured(),
            'senderId' => SmsSettings::senderId(),
            'senderVerification' => SmsSettings::senderVerification(),
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $this->historyFilters($request);
        $query = $this->historyQuery($filters);
        $summary = $this->historyQuery($filters);
        $deliveredCount = (clone $summary)->where('delivery_status', 'delivered')->count();
        $deliveryFailureCount = (clone $summary)->where('delivery_status', 'failed')->count();
        $deliveryFinalCount = $deliveredCount + $deliveryFailureCount;

        return view('finance.sms.index', [
            'logs' => $query->with([
                'transaction:id,transaction_number,receipt_number,type,amount,currency',
                'smsAttempts',
                'statusEvents' => fn ($events) => $events->limit(3),
            ])->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'senderIds' => FinancialSmsLog::query()
                ->whereNotNull('sender_id')
                ->distinct()
                ->orderBy('sender_id')
                ->pluck('sender_id'),
            'totalMessages' => (clone $summary)->count(),
            'totalSegments' => (clone $summary)->sum(DB::raw('COALESCE(provider_units, sms_segments, 0)')),
            'deliverySuccessRate' => $deliveryFinalCount === 0
                ? 0
                : round($deliveredCount / $deliveryFinalCount * 100, 1),
            'pendingCount' => (clone $summary)
                ->where(function (Builder $query): void {
                    $query->whereIn('status', ['queued', 'sending'])
                        ->orWhere(function (Builder $query): void {
                            $query->where('provider', 'swalasms')
                                ->where('status', 'accepted')
                                ->whereIn('delivery_status', ['queued', 'processing', 'sent', 'scheduled']);
                        });
                })
                ->count(),
            'statusCounts' => (clone $summary)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'deliveryCounts' => (clone $summary)->whereNotNull('delivery_status')->selectRaw('delivery_status, count(*) as total')->groupBy('delivery_status')->pluck('total', 'delivery_status'),
            'failedCount' => (clone $summary)
                ->where(fn (Builder $query) => $query->where('status', 'failed')->orWhere('delivery_status', 'failed'))
                ->count(),
            'failedJobCount' => Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')
                    ->where(fn ($failed) => $failed->where('payload', 'like', '%SendFinancialSms%')
                        ->orWhere('payload', 'like', '%SendBulkSmsBatch%')
                        ->orWhere('payload', 'like', '%PrepareBulkSmsCampaign%'))
                    ->count()
                : 0,
            'lastCheckStatus' => FinancialSetting::getValue('swala_last_check_status'),
            'senderId' => SmsSettings::senderId(),
            'senderVerification' => SmsSettings::senderVerification(),
            'lastBalance' => SmsSettings::apiKey() !== '' ? FinancialSetting::getValue('swala_last_balance') : null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->historyFilters($request);
        $query = $this->historyQuery($filters);

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('The SMS history export stream could not be opened.');
            }

            fputcsv($stream, [
                'uid',
                'recipient',
                'sender_id',
                'message',
                'status',
                'delivery_status',
                'encoding',
                'segments',
                'provider_units',
                'attempts',
                'provider_message_id',
                'failure_reason',
                'created_at',
            ], ',', '"', '');

            foreach ($query->orderBy('id')->lazyById(500) as $log) {
                fputcsv($stream, [
                    $log->id,
                    $this->csvCell($log->phone),
                    $this->csvCell($log->sender_id),
                    $this->csvCell($log->message),
                    $this->csvCell($log->status),
                    $this->csvCell($log->delivery_status),
                    $this->csvCell($log->encoding),
                    $log->sms_segments,
                    $log->provider_units,
                    $log->attempts,
                    $this->csvCell($log->provider_message_id),
                    $this->csvCell($log->provider_failure_reason),
                    $this->csvCell($log->created_at?->toIso8601String()),
                ], ',', '"', '');
            }

            fclose($stream);
        }, 'sms-history-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function historyFilters(Request $request): array
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:financial_receipt,sunday_offering,test_sms,manual_sms,bulk_campaign'],
            'status' => ['nullable', 'in:queued,sending,accepted,test_only,not_configured,no_phone,failed,cancelled'],
            'delivery_status' => ['nullable', 'in:queued,processing,sent,delivered,failed,scheduled'],
            'sender_id' => ['nullable', 'string', 'max:32'],
            'period' => ['nullable', 'in:today,yesterday,last_7_days,last_30_days,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $today = now()->startOfDay();
        [$from, $to] = match ($filters['period'] ?? 'custom') {
            'today' => [$today->toDateString(), $today->toDateString()],
            'yesterday' => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
            'last_7_days' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
            'last_30_days' => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
            default => [$filters['from'] ?? null, $filters['to'] ?? null],
        };

        $filters['from'] = $from;
        $filters['to'] = $to;

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function historyQuery(array $filters): Builder
    {
        return FinancialSmsLog::query()
            ->where('notification_type', '!=', 'user_invitation')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('phone', 'like', '%'.$search.'%')
                        ->orWhere('provider_message_id', 'like', '%'.$search.'%')
                        ->orWhereHas('transaction', function (Builder $transactionQuery) use ($search): void {
                            $transactionQuery->where('transaction_number', 'like', '%'.$search.'%')
                                ->orWhere('receipt_number', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['delivery_status'] ?? null, fn (Builder $query, string $status) => $query->where('delivery_status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('notification_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['sender_id'] ?? null, fn (Builder $query, string $senderId) => $query->where('sender_id', $senderId))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function csvCell(?string $value): string
    {
        $value = $value ?? '';

        return preg_match('/^[\t\r\n ]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    public function retry(FinancialSmsLog $smsLog): RedirectResponse
    {
        abort_unless(in_array($smsLog->notification_type, ['financial_receipt', 'sunday_offering'], true), 403);

        if ($smsLog->provider_acceptance_state === SwalaSmsException::UNKNOWN) {
            return back()->withErrors(['sms' => __('finance.sms_unknown_outcome')]);
        }

        if ($smsLog->member === null || ! $smsLog->member->sms_opt_in) {
            return back()->withErrors([
                'sms' => __('finance.'.($smsLog->notification_type === 'sunday_offering'
                    ? 'sms_sunday_consent_required'
                    : 'sms_financial_consent_required')),
            ]);
        }

        if (! SmsSettings::enabled()) {
            return back()->withErrors([
                'sms' => __('finance.test_sms_requires_enabled'),
            ]);
        }

        $testMode = SmsSettings::testMode();
        $credentialsConfigured = SmsSettings::hasCredentials();

        if (! $testMode && ! $credentialsConfigured) {
            return back()->withErrors([
                'sms' => __('finance.sms_not_configured'),
            ]);
        }

        $smsLog = DB::transaction(function () use ($smsLog, $testMode): FinancialSmsLog {
            $lockedLog = FinancialSmsLog::query()->lockForUpdate()->findOrFail($smsLog->id);
            abort_unless(
                in_array($lockedLog->status, ['failed', 'not_configured'], true)
                    || ($lockedLog->status === 'accepted' && $lockedLog->delivery_status === 'failed'),
                409,
            );

            if ($lockedLog->provider_acceptance_state === SwalaSmsException::UNKNOWN) {
                throw ValidationException::withMessages([
                    'sms' => __('finance.sms_unknown_outcome'),
                ]);
            }

            $lockedLog->forceFill([
                'status' => 'queued',
                'is_test' => $testMode,
                'error_message' => null,
                'provider_error_code' => null,
                'provider_status' => null,
                'delivery_status' => null,
                'delivered_at' => null,
                'provider_event_at' => null,
                'provider_failure_reason' => null,
                'idempotency_key' => $lockedLog->provider_acceptance_state === SwalaSmsException::UNKNOWN
                    ? $lockedLog->idempotency_key
                    : null,
            ])->save();

            return $lockedLog;
        });

        try {
            SendFinancialSms::dispatch($smsLog->id);
        } catch (Throwable) {
            $smsLog->forceFill([
                'status' => 'failed',
                'error_message' => 'The SMS could not be added to the delivery queue.',
            ])->save();

            return back()->withErrors(['sms' => __('finance.sms_queue_failed')]);
        }

        return back()->with('success', $testMode
            ? __('finance.test_sms_recorded')
            : __('finance.sms_retry_queued'));
    }

    public function refreshStatus(FinancialSmsLog $smsLog, SwalaSmsService $swala): RedirectResponse
    {
        abort_unless($smsLog->provider === 'swalasms' && filled($smsLog->provider_message_id), 404);

        try {
            $result = $swala->messageStatus($smsLog->provider_message_id);
        } catch (SwalaSmsException) {
            return back()->withErrors(['sms' => __('finance.delivery_status_failed')]);
        }

        DB::transaction(function () use ($smsLog, $result): void {
            $locked = FinancialSmsLog::query()->lockForUpdate()->findOrFail($smsLog->id);
            $locked->applyProviderStatus(
                $result['status'],
                $result['delivered_at'] !== null
                    ? Carbon::parse($result['delivered_at'])
                    : ($result['sent_at'] === null ? null : Carbon::parse($result['sent_at'])),
                $result['failure_reason'],
                $result['delivered_at'] === null ? null : Carbon::parse($result['delivered_at']),
                'manual',
            );
            $locked->forceFill(['delivery_checked_at' => now()])->save();
        });

        return back()->with('success', __('finance.delivery_status_updated', [
            'status' => __('finance.delivery_statuses.'.$result['status']),
        ]));
    }

    public function sendTest(
        SendTestSmsRequest $request,
        FinancialSmsMessageBuilder $messages,
        SwalaSmsService $gateway,
    ): RedirectResponse {
        if (! SmsSettings::enabled()) {
            return redirect()->to(route('settings.edit').'#sms-notifications')
                ->withErrors(['sms' => __('finance.test_sms_requires_enabled')]);
        }

        if (! SmsSettings::hasCredentials()) {
            return redirect()->to(route('settings.edit').'#sms-notifications')
                ->withErrors(['sms' => __('finance.test_sms_live_credentials_required')]);
        }

        $validated = $request->validated();
        try {
            $phone = $gateway->normalizePhone($validated['phone']);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'phone' => __('finance.phone_invalid'),
            ]);
        }

        $log = FinancialSmsLog::query()->create([
            'notification_type' => 'test_sms',
            'message' => $messages->preview($validated['type'], $validated['language']),
            'created_by' => Auth::id(),
            'phone' => $phone,
            'status' => 'queued',
            'is_test' => true,
        ]);

        try {
            SendFinancialSms::dispatch($log->id);
        } catch (Throwable) {
            $log->forceFill([
                'status' => 'failed',
                'error_message' => 'The SMS could not be added to the delivery queue.',
            ])->save();

            return redirect()->to(route('settings.edit').'#sms-notifications')
                ->withErrors(['sms' => __('finance.sms_queue_failed')]);
        }

        return redirect()->to(route('settings.edit').'#sms-notifications')
            ->with('success', __('finance.test_sms_queued'));
    }

    public function sendManual(
        SendManualSmsRequest $request,
        SmsSegmentEstimator $segments,
    ): RedirectResponse {
        if (! SmsSettings::isLiveConfigured()) {
            return redirect()->route('finance.sms.compose')
                ->withErrors(['sms' => __('finance.manual_sms_requires_live_configuration')]);
        }

        $validated = $request->validated();
        $estimate = $segments->estimate($validated['message']);
        $log = FinancialSmsLog::query()->create([
            'notification_type' => 'manual_sms',
            'message' => $validated['message'],
            'created_by' => $request->user()->id,
            'phone' => $validated['phone'],
            'status' => 'queued',
            'is_test' => false,
            'sender_id' => SmsSettings::senderId(),
            'encoding' => $estimate['encoding'],
            'sms_segments' => $estimate['segments'],
        ]);

        try {
            SendFinancialSms::dispatch($log->id);
        } catch (Throwable) {
            $log->forceFill([
                'status' => 'failed',
                'error_message' => 'The SMS could not be added to the delivery queue.',
            ])->save();

            return redirect()->route('finance.sms.compose')
                ->withErrors(['sms' => __('finance.sms_queue_failed')]);
        }

        return redirect()->route('finance.sms.index')
            ->with('success', __('finance.manual_sms_queued'));
    }
}
