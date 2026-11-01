<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendTestSmsRequest;
use App\Jobs\SendFinancialSms;
use App\Models\FinancialSetting;
use App\Models\FinancialSmsLog;
use App\Services\BeemSmsGateway;
use App\Services\FinancialSmsMessageBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'type' => ['nullable', 'in:financial_receipt,test_sms'],
            'status' => ['nullable', 'in:queued,sending,accepted,test_only,not_configured,no_phone,failed'],
            'delivery_status' => ['nullable', 'in:PENDING,DELIVERED,UNDELIVERED'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $query = FinancialSmsLog::query()
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
            ->when($validated['type'] ?? null, fn (Builder $query, string $type) => $query->where('notification_type', $type))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['delivery_status'] ?? null, fn (Builder $query, string $status) => $query->where('delivery_status', $status))
            ->when($validated['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));

        return view('finance.sms.index', [
            'logs' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function retry(FinancialSmsLog $smsLog): RedirectResponse
    {
        if (FinancialSetting::getValue('beem_enabled', '0') !== '1') {
            return back()->withErrors([
                'sms' => __('finance.test_sms_requires_enabled'),
            ]);
        }

        $testMode = FinancialSetting::getValue('beem_test_mode', '1') === '1';
        $credentialsConfigured = trim((string) FinancialSetting::getValue('beem_api_key')) !== ''
            && trim((string) FinancialSetting::getValue('beem_api_secret')) !== ''
            && trim((string) FinancialSetting::getValue('beem_sender_id')) !== '';

        if (! $testMode && ! $credentialsConfigured) {
            return back()->withErrors([
                'sms' => __('finance.sms_not_configured'),
            ]);
        }

        $smsLog = DB::transaction(function () use ($smsLog, $testMode): FinancialSmsLog {
            $lockedLog = FinancialSmsLog::query()->lockForUpdate()->findOrFail($smsLog->id);
            abort_unless(in_array($lockedLog->status, ['failed', 'not_configured'], true), 409);

            $lockedLog->forceFill([
                'status' => 'queued',
                'is_test' => $testMode,
                'error_message' => null,
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

    public function balance(BeemSmsGateway $gateway): RedirectResponse
    {
        try {
            $balance = $gateway->balance();
        } catch (RuntimeException) {
            return back()->withErrors([
                'sms' => __('finance.sms_balance_failed'),
            ]);
        }

        FinancialSetting::setValue('beem_last_balance', (string) $balance['credit_balance']);
        FinancialSetting::setValue('beem_last_balance_checked_at', now()->toDateTimeString());

        return back()->with('sms_balance', $balance['credit_balance']);
    }

    public function sendTest(
        SendTestSmsRequest $request,
        FinancialSmsMessageBuilder $messages,
        BeemSmsGateway $gateway,
    ): RedirectResponse {
        if (FinancialSetting::getValue('beem_enabled', '0') !== '1') {
            return redirect()->to(route('settings.edit').'#sms-notifications')
                ->withErrors(['sms' => __('finance.test_sms_requires_enabled')]);
        }

        $testMode = FinancialSetting::getValue('beem_test_mode', '1') === '1';
        $credentialsConfigured = trim((string) FinancialSetting::getValue('beem_api_key')) !== ''
            && trim((string) FinancialSetting::getValue('beem_api_secret')) !== ''
            && trim((string) FinancialSetting::getValue('beem_sender_id')) !== '';

        if (! $testMode && ! $credentialsConfigured) {
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
            'status' => $testMode ? 'test_only' : 'queued',
            'is_test' => $testMode,
        ]);

        if ($testMode) {
            return redirect()->to(route('settings.edit').'#sms-notifications')
                ->with('success', __('finance.test_sms_recorded'));
        }

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

    public function checkDelivery(FinancialSmsLog $smsLog, BeemSmsGateway $gateway): RedirectResponse
    {
        abort_unless($smsLog->status === 'accepted' && ! $smsLog->is_test, 409);

        if ($smsLog->accepted_at === null || $smsLog->accepted_at->gt(now()->subMinutes(5))) {
            return back()->withErrors(['sms' => __('finance.delivery_report_too_early')]);
        }

        try {
            $status = $gateway->deliveryReport($smsLog);
        } catch (RuntimeException) {
            return back()->withErrors(['sms' => __('finance.delivery_status_failed')]);
        }

        if ($status === null) {
            return back()->withErrors(['sms' => __('finance.delivery_status_unavailable')]);
        }

        $smsLog->forceFill([
            'delivery_status' => $status,
            'delivery_checked_at' => now(),
        ])->save();

        return back()->with('success', __('finance.delivery_status_updated', [
            'status' => __('finance.delivery_statuses.'.$status),
        ]));
    }
}
