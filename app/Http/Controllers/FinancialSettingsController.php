<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveFinancialSettingsRequest;
use App\Http\Requests\SaveFinancialSmsSettingsRequest;
use App\Http\Requests\SaveReceiptSettingsRequest;
use App\Models\FinancialSetting;
use App\Models\FinancialTransaction;
use App\Models\Setting;
use App\Services\SmsSettings;
use App\Services\SwalaSmsException;
use App\Services\SwalaSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
            $senderChanged = SmsSettings::senderId() !== trim((string) ($validated['swala_sender_id'] ?? ''));
            $keyChanged = $request->boolean('clear_swala_credentials')
                || ($request->filled('swala_api_key') && SmsSettings::apiKey() !== $validated['swala_api_key']);

            if ($senderChanged || $keyChanged) {
                FinancialSetting::setValue('swala_sender_verification', null);
            }
            if ($keyChanged) {
                FinancialSetting::setValue('swala_sender_ids_snapshot', null);
            }

            if ($request->boolean('clear_swala_credentials')) {
                FinancialSetting::setValue('swala_api_key', null);
                FinancialSetting::setValue('swala_webhook_secret', null);
            } else {
                if ($request->filled('swala_api_key')) {
                    FinancialSetting::setValue('swala_api_key', $validated['swala_api_key']);
                }
            }

            if ($request->filled('swala_api_key') || $request->boolean('clear_swala_credentials')) {
                FinancialSetting::setValue('swala_last_check_at', null);
                FinancialSetting::setValue('swala_last_check_status', null);
                FinancialSetting::setValue('swala_last_check_message', null);
                FinancialSetting::setValue('swala_last_balance', null);
            }

            FinancialSetting::setValue('swala_sender_id', trim((string) ($validated['swala_sender_id'] ?? '')));
            FinancialSetting::setValue('swala_timeout', (string) $validated['swala_timeout']);

            foreach ([
                'language',
                'template_offering_en',
                'template_offering_sw',
                'template_contribution_en',
                'template_contribution_sw',
                'template_sunday_offering_en',
                'template_sunday_offering_sw',
            ] as $name) {
                SmsSettings::setShared($name, $validated['sms_'.$name]);
            }

            foreach (['swala_enabled', 'swala_test_mode'] as $key) {
                FinancialSetting::setValue($key, $request->boolean($key) ? '1' : '0');
            }

            foreach (['auto_offering', 'auto_contribution', 'auto_other_income'] as $name) {
                SmsSettings::setShared($name, $request->boolean('sms_'.$name) ? '1' : '0');
            }
        });

        return redirect()
            ->to(route('settings.edit').'#sms-notifications')
            ->with('success', __('finance.sms_settings_saved'));
    }

    public function testSmsConnection(SwalaSmsService $swala): RedirectResponse
    {
        if (! SmsSettings::hasCredentials() && SmsSettings::apiKey() === '') {
            return redirect()->to(route('settings.edit').'#sms-notifications')
                ->withErrors(['sms' => __('finance.swala_connection_missing_credentials')]);
        }

        try {
            $balance = $swala->balance();
            FinancialSetting::setValue('swala_last_check_status', 'ok');
            FinancialSetting::setValue('swala_last_check_message', null);
            FinancialSetting::setValue('swala_last_balance', (string) $balance['balance']);
            FinancialSetting::setValue('swala_last_success_at', now()->toIso8601String());
            $success = __('finance.swala_connection_success', ['balance' => $balance['balance']]);
        } catch (SwalaSmsException $exception) {
            FinancialSetting::setValue('swala_last_check_status', 'failed');
            FinancialSetting::setValue('swala_last_check_message', trim(($exception->errorCode ?? 'ERROR').': '.$exception->getMessage()));
            FinancialSetting::setValue('swala_last_balance', null);
            $success = null;
        }

        FinancialSetting::setValue('swala_last_check_at', now()->toIso8601String());

        $response = redirect()->to(route('settings.edit').'#sms-notifications');

        return $success === null
            ? $response->withErrors(['sms' => __('finance.swala_connection_failed')])
            : $response->with('success', $success);
    }

    public function verifySmsSender(SwalaSmsService $swala): RedirectResponse
    {
        $response = redirect()->to(route('settings.edit').'#sms-notifications');
        $apiKey = SmsSettings::apiKey();
        $list = SmsSettings::senderList();
        $error = null;

        if ($apiKey === '') {
            $error = 'missing';
        } else {
            try {
                $list['senders'] = $swala->senderIds($apiKey);
                $list['success_at'] = now()->toIso8601String();
            } catch (SwalaSmsException $exception) {
                $error = match (true) {
                    $exception->httpStatus === 401 => 'unauthorized',
                    $exception->httpStatus === 403 => 'forbidden',
                    $exception->httpStatus === 404 => 'endpoint_not_found',
                    $exception->httpStatus === 429 => 'rate_limited',
                    $exception->httpStatus !== null && $exception->httpStatus >= 500 => 'provider_unavailable',
                    $exception->errorCode === 'CONNECTION_FAILED' => 'connection',
                    default => 'unavailable',
                };
            }
        }

        FinancialSetting::setValue('swala_sender_ids_snapshot', json_encode([
            'senders' => $list['senders'],
            'success_at' => $list['success_at'],
            'checked_at' => now()->toIso8601String(),
            'error' => $error,
            'fingerprint' => SmsSettings::senderVerificationFingerprint($apiKey),
        ], JSON_THROW_ON_ERROR));

        if ($error !== null) {
            return $response->withErrors(['sms' => __('finance.swala_sender_verification_reasons.'.$error)]);
        }

        return $response->with('success', __('finance.swala_sender_list_refreshed'));
    }

    public function selectSmsSender(Request $request): RedirectResponse
    {
        $validated = $request->validate(['sender_id' => ['required', 'string', 'max:32']]);
        $list = SmsSettings::senderList();
        $allowed = $list['error'] === null && $list['success_at'] !== null
            && collect($list['senders'])->contains(fn (array $sender): bool => $sender['sender_id'] === $validated['sender_id']
                && $sender['status'] === 'approved' && $sender['is_usable']);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'sender_id' => __('finance.swala_sender_selection_invalid'),
            ]);
        }

        FinancialSetting::setValue('swala_sender_id', $validated['sender_id']);

        return redirect()->to(route('settings.edit').'#sms-notifications')
            ->with('success', __('finance.sms_settings_saved'));
    }
}
