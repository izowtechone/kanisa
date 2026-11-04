<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendTestSmsRequest;
use App\Jobs\SendFinancialSms;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use App\Services\FinancialSmsMessageBuilder;
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
use Throwable;

class FinancialSmsController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:financial_receipt,sunday_offering,test_sms,bulk_campaign'],
            'status' => ['nullable', 'in:queued,sending,accepted,test_only,not_configured,no_phone,failed,cancelled'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'delivery_status' => ['nullable', 'in:queued,processing,sent,delivered,failed,scheduled'],
        ]);

        $query = FinancialSmsLog::query()
            ->where('notification_type', '!=', 'user_invitation')
            ->with([
                'transaction:id,transaction_number,receipt_number,type,amount,currency',
                'smsAttempts',
            ])
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('phone', 'like', '%'.$search.'%')
                        ->orWhere('provider_message_id', 'like', '%'.$search.'%')
                        ->orWhereHas('transaction', function (Builder $transactionQuery) use ($search): void {
                            $transactionQuery->where('transaction_number', 'like', '%'.$search.'%')
                                ->orWhere('receipt_number', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($validated['delivery_status'] ?? null, fn (Builder $query, string $status) => $query->where('delivery_status', $status))
            ->when($validated['type'] ?? null, fn (Builder $query, string $type) => $query->where('notification_type', $type))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));

        return view('finance.sms.index', [
            'logs' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'totalMessages' => FinancialSmsLog::query()->count(),
            'pendingCount' => FinancialSmsLog::query()
                ->where(function (Builder $query): void {
                    $query->whereIn('status', ['queued', 'sending'])
                        ->orWhere(function (Builder $query): void {
                            $query->where('provider', 'swalasms')
                                ->where('status', 'accepted')
                                ->whereIn('delivery_status', ['queued', 'processing', 'sent', 'scheduled']);
                        });
                })
                ->count(),
            'statusCounts' => FinancialSmsLog::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'deliveryCounts' => FinancialSmsLog::query()->whereNotNull('delivery_status')->selectRaw('delivery_status, count(*) as total')->groupBy('delivery_status')->pluck('total', 'delivery_status'),
            'failedCount' => FinancialSmsLog::query()
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
            'lastBalance' => SmsSettings::apiKey() !== '' ? FinancialSetting::getValue('swala_last_balance') : null,
        ]);
    }

    public function retry(FinancialSmsLog $smsLog): RedirectResponse
    {
        abort_unless(in_array($smsLog->notification_type, ['financial_receipt', 'sunday_offering'], true), 403);

        if ($smsLog->notification_type === 'sunday_offering'
            && ($smsLog->member === null || ! $smsLog->member->sms_opt_in)) {
            return back()->withErrors([
                'sms' => __('finance.sms_sunday_consent_required'),
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
                null,
                $result['failure_reason'],
                $result['delivered_at'] === null ? null : Carbon::parse($result['delivered_at']),
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
}
