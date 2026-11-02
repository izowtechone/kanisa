<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveChurchSettingsRequest;
use App\Models\FinancialSetting;
use App\Models\Setting;
use App\Services\FinancialSmsMessageBuilder;
use App\Services\SmsSettings;
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
            'communityCodePrefix' => Setting::getValue('community_code_prefix', 'JUM'),
            'systemLanguage' => Setting::getValue('system_language', config('app.locale')),
            'churchLogo' => Setting::getValue('church_logo', ''),
            'canManageChurchSettings' => $user->can('manage settings'),
            'canManageSmsSettings' => $user->can('manage SMS settings'),
            'canSendTestSms' => $user->can('send test SMS'),
        ];

        if ($data['canManageSmsSettings']) {
            $language = SmsSettings::shared(
                'language',
                (string) Setting::getValue('system_language', config('app.locale', 'sw')),
            );
            $templates = [];

            foreach (['offering', 'contribution', 'sunday_offering'] as $type) {
                foreach (['en', 'sw'] as $locale) {
                    $templates[$type.'_'.$locale] = SmsSettings::shared(
                        'template_'.$type.'_'.$locale,
                        __('finance.sms_template_'.$type, [], $locale),
                    );
                }
            }

            $data['smsSettings'] = [
                'enabled' => SmsSettings::enabled(),
                'testMode' => SmsSettings::testMode(),
                'senderId' => SmsSettings::senderId(),
                'language' => $language,
                'autoOffering' => SmsSettings::shared('auto_offering', '1') === '1',
                'autoContribution' => SmsSettings::shared('auto_contribution', '1') === '1',
                'autoOtherIncome' => SmsSettings::shared('auto_other_income', '0') === '1',
                'credentialsConfigured' => SmsSettings::apiKey() !== '',
                'keyEnvironment' => SmsSettings::keyEnvironment(),
                'webhookSecretConfigured' => SmsSettings::webhookSecret() !== '',
                'webhookUrl' => route('webhooks.swalasms'),
                'lastCheckAt' => FinancialSetting::getValue('swala_last_check_at'),
                'lastCheckStatus' => FinancialSetting::getValue('swala_last_check_status'),
                'lastCheckMessage' => FinancialSetting::getValue('swala_last_check_message'),
                'lastBalance' => FinancialSetting::getValue('swala_last_balance'),
                'lastSuccessAt' => FinancialSetting::getValue('swala_last_success_at'),
                'templates' => $templates,
            ];
        }
        if ($data['canManageSmsSettings'] && $data['canSendTestSms']) {
            $messageBuilder = app(FinancialSmsMessageBuilder::class);
            $data['smsTemplatePreviews'] = [
                'en' => [
                    'offering' => $messageBuilder->preview('offering', 'en'),
                    'contribution' => $messageBuilder->preview('contribution', 'en'),
                    'sunday_offering' => $messageBuilder->previewSundayOffering('en'),
                ],
                'sw' => [
                    'offering' => $messageBuilder->preview('offering', 'sw'),
                    'contribution' => $messageBuilder->preview('contribution', 'sw'),
                    'sunday_offering' => $messageBuilder->previewSundayOffering('sw'),
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
        if (isset($validated['community_code_prefix']) && trim($validated['community_code_prefix']) !== '') {
            Setting::setValue(
                'community_code_prefix',
                strtoupper(trim($validated['community_code_prefix'])),
                'general',
            );
        }
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
