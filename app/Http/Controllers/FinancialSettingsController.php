<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveFinancialSettingsRequest;
use App\Http\Requests\SaveFinancialSmsSettingsRequest;
use App\Http\Requests\SaveReceiptSettingsRequest;
use App\Http\Requests\TestFinancialSmsSettingsConnectionRequest;
use App\Models\FinancialSetting;
use App\Models\FinancialTransaction;
use App\Models\Setting;
use App\Services\BeemSmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class FinancialSettingsController extends Controller
{
    public function edit(): View
    {
        return view('finance.settings.edit', [
            'currency' => FinancialSetting::getValue('currency', Setting::getValue('currency', 'TZS')),
        ]);
    }

    public function update(SaveFinancialSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $currentCurrency = FinancialSetting::getValue('currency', Setting::getValue('currency', 'TZS'));

        if ($currentCurrency !== $validated['currency'] && FinancialTransaction::query()->exists()) {
            throw ValidationException::withMessages([
                'currency' => __('finance.currency_locked'),
            ]);
        }
        FinancialSetting::setValue('currency', $validated['currency']);
        Setting::setValue('currency', $validated['currency'], 'general');

        return redirect()
            ->route('finance.settings.edit')
            ->with('success', __('finance.settings_saved'));
    }

    public function editReceipts(): View
    {
        return view('finance.settings.receipts', [
            'receiptPrefix' => FinancialSetting::getValue('receipt_prefix', 'KKKT-RCPT'),
        ]);
    }

    public function updateReceipts(SaveReceiptSettingsRequest $request): RedirectResponse
    {
        FinancialSetting::setValue('receipt_prefix', $request->validated('receipt_prefix'));

        return redirect()
            ->route('finance.receipts.settings.edit')
            ->with('success', __('finance.receipt_settings_saved'));
    }

    public function editSms(): RedirectResponse
    {
        return redirect()->to(route('settings.edit').'#sms-notifications');
    }

    public function updateSms(SaveFinancialSmsSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            if ($request->boolean('clear_beem_credentials')) {
                FinancialSetting::setValue('beem_api_key', null);
                FinancialSetting::setValue('beem_api_secret', null);
            } elseif ($request->filled('beem_api_key') && $request->filled('beem_api_secret')) {
                FinancialSetting::setValue('beem_api_key', $validated['beem_api_key']);
                FinancialSetting::setValue('beem_api_secret', $validated['beem_api_secret']);
            }

            foreach ([
                'beem_sender_id',
                'beem_language',
                'beem_low_credit_threshold',
                'beem_template_offering_en',
                'beem_template_offering_sw',
                'beem_template_contribution_en',
                'beem_template_contribution_sw',
            ] as $key) {
                FinancialSetting::setValue($key, $validated[$key] ?? null);
            }

            foreach ([
                'beem_enabled',
                'beem_test_mode',
                'beem_auto_offering',
                'beem_auto_contribution',
                'beem_auto_other_income',
            ] as $key) {
                FinancialSetting::setValue($key, $request->boolean($key) ? '1' : '0');
            }
        });

        return redirect()
            ->to(route('settings.edit').'#sms-notifications')
            ->with('success', __('finance.sms_settings_saved'));
    }

    public function testSmsConnection(
        TestFinancialSmsSettingsConnectionRequest $request,
        BeemSmsGateway $gateway,
    ): RedirectResponse {
        if (trim((string) FinancialSetting::getValue('beem_api_key')) === ''
            || trim((string) FinancialSetting::getValue('beem_api_secret')) === '') {
            return redirect()->to(route('settings.edit').'#sms-notifications')->withErrors([
                'sms_connection' => __('finance.beem_connection_missing_credentials'),
            ]);
        }

        try {
            $balance = $gateway->balance();
        } catch (RuntimeException) {
            return redirect()->to(route('settings.edit').'#sms-notifications')->withErrors([
                'sms_connection' => __('finance.beem_connection_failed'),
            ]);
        }

        FinancialSetting::setValue('beem_last_balance', (string) $balance['credit_balance']);
        FinancialSetting::setValue('beem_last_balance_checked_at', now()->toDateTimeString());

        return redirect()->to(route('settings.edit').'#sms-notifications')
            ->with('sms_connection', __('finance.beem_connection_success', [
                'balance' => $balance['credit_balance'],
            ]));
    }
}
