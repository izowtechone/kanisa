<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveChurchSettingsRequest;
use App\Models\FinancialSetting;
use App\Models\Setting;
use App\Services\FinancialSmsMessageBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class SystemSettingsController extends Controller
{
    public function edit(): View
    {
        $user = Auth::user();
        abort_unless(
            $user !== null && ($user->can('manage settings') || $user->can('manage SMS settings')),
            403,
        );

        $data = [
            'churchShortName' => Setting::getValue('church_short_name', ''),
            'systemLanguage' => Setting::getValue('system_language', config('app.locale')),
            'churchLogo' => Setting::getValue('church_logo', ''),
            'canManageChurchSettings' => $user->can('manage settings'),
            'canManageSmsSettings' => $user->can('manage SMS settings'),
            'canSendTestSms' => $user->can('send test SMS'),
        ];

        if ($data['canManageSmsSettings']) {
            $language = FinancialSetting::getValue(
                'beem_language',
                (string) Setting::getValue('system_language', config('app.locale', 'sw')),
            );
            $data['smsSettings'] = [
                'enabled' => FinancialSetting::getValue('beem_enabled', '0') === '1',
                'testMode' => FinancialSetting::getValue('beem_test_mode', '1') === '1',
                'senderId' => FinancialSetting::getValue('beem_sender_id', ''),
                'language' => $language,
                'autoOffering' => FinancialSetting::getValue('beem_auto_offering', '1') === '1',
                'autoContribution' => FinancialSetting::getValue('beem_auto_contribution', '1') === '1',
                'autoOtherIncome' => FinancialSetting::getValue('beem_auto_other_income', '0') === '1',
                'lowCreditThreshold' => FinancialSetting::getValue('beem_low_credit_threshold', ''),
                'lastBalance' => FinancialSetting::getValue('beem_last_balance'),
                'lastBalanceCheckedAt' => FinancialSetting::getValue('beem_last_balance_checked_at'),
                'credentialsConfigured' => trim((string) FinancialSetting::getValue('beem_api_key')) !== ''
                    && trim((string) FinancialSetting::getValue('beem_api_secret')) !== '',
                'templates' => [
                    'offering_en' => FinancialSetting::getValue(
                        'beem_template_offering_en',
                        __('finance.sms_template_offering', [], 'en'),
                    ),
                    'offering_sw' => FinancialSetting::getValue(
                        'beem_template_offering_sw',
                        __('finance.sms_template_offering', [], 'sw'),
                    ),
                    'contribution_en' => FinancialSetting::getValue(
                        'beem_template_contribution_en',
                        __('finance.sms_template_contribution', [], 'en'),
                    ),
                    'contribution_sw' => FinancialSetting::getValue(
                        'beem_template_contribution_sw',
                        __('finance.sms_template_contribution', [], 'sw'),
                    ),
                ],
            ];
        }

        if ($data['canManageSmsSettings'] && $data['canSendTestSms']) {
            $messageBuilder = app(FinancialSmsMessageBuilder::class);
            $data['smsTemplatePreviews'] = [
                'en' => [
                    'offering' => $messageBuilder->preview('offering', 'en'),
                    'contribution' => $messageBuilder->preview('contribution', 'en'),
                ],
                'sw' => [
                    'offering' => $messageBuilder->preview('offering', 'sw'),
                    'contribution' => $messageBuilder->preview('contribution', 'sw'),
                ],
            ];
        }

        return view('settings.edit', $data);
    }

    public function update(SaveChurchSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $previousLogo = Setting::getValue('church_logo', '');
        $newLogo = $previousLogo;

        if ($request->hasFile('church_logo')) {
            $storedLogo = $request->file('church_logo')->store('church-logos', 'public');

            if (! is_string($storedLogo)) {
                throw new RuntimeException('Unable to store the church logo.');
            }

            $newLogo = $storedLogo;
        } elseif ($request->boolean('remove_church_logo')) {
            $newLogo = null;
        }

        Setting::setValue(
            'church_short_name',
            strtoupper(trim($validated['church_short_name'])),
            'general',
        );
        Setting::setValue('system_language', $validated['system_language'], 'general');
        Setting::setValue('church_logo', $newLogo, 'general');

        if ($previousLogo !== '' && $previousLogo !== $newLogo && str_starts_with($previousLogo, 'church-logos/')) {
            Storage::disk('public')->delete($previousLogo);
        }

        return redirect()
            ->route('settings.edit')
            ->with('success', __('members.settings_saved'));
    }
}
