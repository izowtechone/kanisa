<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveChurchSettingsRequest;
use App\Models\FinancialSetting;
use App\Models\Setting;
use App\Services\AppSettings;
use App\Services\FinancialSmsMessageBuilder;
use App\Services\SmsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class SystemSettingsController extends Controller
{
    public function edit(): View
    {
        $user = Auth::user();
        abort_unless($user?->isSuperAdmin(), 403);

        $settings = app(AppSettings::class);
        $data = [
            'systemName' => $settings->systemName(),
            'systemLanguage' => $settings->locale(),
            'timezone' => $settings->timezone(),
            'currency' => $settings->currency(),
            'dateFormat' => $settings->dateFormat(),
            'timeFormat' => $settings->timeFormat(),
            'communityCodePrefix' => $settings->get('community_code_prefix', 'JUM'),
            'churchName' => $settings->churchName() ?? '',
            'churchShortName' => $settings->get('church_short_name', ''),
            'churchAddress' => $settings->churchAddress() ?? '',
            'churchPhone' => $settings->churchPhone() ?? '',
            'churchEmail' => $settings->churchEmail() ?? '',
            'churchLogoUrl' => $settings->logoUrl(),
            'churchFaviconUrl' => $settings->customFaviconUrl(),
            'churchFaviconType' => $settings->faviconMimeType(),
            'canManageChurchSettings' => true,
            'canManageSmsSettings' => true,
            'canSendTestSms' => true,
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
        $section = $validated['section'];
        $fileSetting = match ($section) {
            'logo' => 'church_logo',
            'favicon' => 'church_favicon',
            'legacy' => $request->hasFile('church_logo') || $request->boolean('remove_church_logo')
                ? 'church_logo'
                : null,
            default => null,
        };
        $directory = match ($fileSetting) {
            'church_logo' => 'church-logos',
            'church_favicon' => 'church-favicons',
            default => null,
        };
        $previousFile = $fileSetting === null ? null : Setting::getValue($fileSetting);
        $storedFile = null;

        if ($fileSetting !== null && $request->hasFile($fileSetting)) {
            $storedFile = $request->file($fileSetting)->store(
                $directory ?? throw new RuntimeException('The church branding destination is not configured.'),
                'public',
            );

            if (! is_string($storedFile)) {
                throw new RuntimeException('Unable to store the church branding file.');
            }
        }

        $removeFile = $fileSetting !== null
            && $request->boolean($fileSetting === 'church_logo' ? 'remove_church_logo' : 'remove_church_favicon');

        try {
            DB::transaction(function () use ($validated, $section, $fileSetting, $storedFile, $removeFile): void {
                if (in_array($section, ['general', 'legacy'], true)) {
                    if ($section === 'general') {
                        Setting::setValue('system_name', trim($validated['system_name']), 'general');
                        Setting::setValue('timezone', $validated['timezone'], 'general');
                        Setting::setValue('currency', $validated['currency'], 'general');
                        Setting::setValue('date_format', $validated['date_format'], 'general');
                        Setting::setValue('time_format', $validated['time_format'], 'general');
                    }

                    Setting::setValue('system_language', $validated['system_language'], 'general');

                    if ($section === 'legacy') {
                        Setting::setValue(
                            'church_short_name',
                            strtoupper(trim($validated['church_short_name'])),
                            'general',
                        );
                    }

                    if (isset($validated['community_code_prefix']) && trim($validated['community_code_prefix']) !== '') {
                        Setting::setValue(
                            'community_code_prefix',
                            strtoupper(trim($validated['community_code_prefix'])),
                            'general',
                        );
                    }
                }

                if ($section === 'church') {
                    Setting::setValue('church_name', trim($validated['church_name']), 'church');
                    Setting::setValue(
                        'church_short_name',
                        strtoupper(trim($validated['church_short_name'])),
                        'general',
                    );
                    Setting::setValue('system_address', $validated['church_address'] ?? null, 'church');
                    Setting::setValue('system_phone', $validated['church_phone'] ?? null, 'church');
                    Setting::setValue('system_email', $validated['church_email'] ?? null, 'church');
                }

                if ($fileSetting !== null && ($storedFile !== null || $removeFile)) {
                    Setting::setValue($fileSetting, $storedFile, 'branding');
                }
            });
        } catch (Throwable $exception) {
            if ($storedFile !== null) {
                Storage::disk('public')->delete($storedFile);
            }

            throw $exception;
        }

        if ($previousFile !== null && $previousFile !== $storedFile && ($removeFile || $storedFile !== null)) {
            $this->deleteManagedBrandingFile($previousFile, $directory);
        }

        app(AppSettings::class)->forget();

        $redirect = route('settings.edit');

        if ($section !== 'legacy') {
            $anchor = match ($section) {
                'general' => 'general-settings',
                'church' => 'church-information',
                'logo' => 'logo-settings',
                'favicon' => 'favicon-settings',
            };
            $redirect .= '#'.$anchor;
        }

        return redirect($redirect)
            ->with('success', __('settings.settings_saved'));
    }

    private function deleteManagedBrandingFile(?string $path, ?string $directory): void
    {
        $filePath = $path ?? '';

        if ($directory === null || ! str_starts_with($filePath, $directory.'/') || str_contains($filePath, '..')) {
            return;
        }

        if (! Storage::disk('public')->delete($filePath)) {
            Log::warning('A replaced church branding file could not be removed.', [
                'setting_path' => $directory,
            ]);
        }
    }
}
