<section id="sms-notifications" class="scroll-mt-24 space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-6 shadow-xl sm:p-8">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('finance.sms_notifications_title') }}</p>
        <h2 class="mt-2 text-xl font-bold text-white">{{ __('finance.beem_settings') }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ __('finance.sms_notifications_description') }}</p>
    </div>

    @if (session('sms_connection'))
        <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('sms_connection') }}</div>
    @endif

    @if ($smsSettings['lastBalance'] !== null)
        <div class="rounded-xl border border-slate-700 bg-[#0b1120] px-4 py-3 text-sm text-slate-300">
            <p>{{ __('finance.beem_last_balance', ['balance' => $smsSettings['lastBalance']]) }}</p>
            @if ($smsSettings['lastBalanceCheckedAt'])
                <p class="mt-1 text-xs text-slate-500">{{ __('finance.beem_balance_checked_at', ['date' => \Illuminate\Support\Carbon::parse($smsSettings['lastBalanceCheckedAt'])->format('Y-m-d H:i:s')]) }}</p>
            @endif
            @if ($smsSettings['lowCreditThreshold'] !== '' && (float) $smsSettings['lastBalance'] < (float) $smsSettings['lowCreditThreshold'])
                <p role="status" class="mt-2 font-semibold text-amber-300">{{ __('finance.beem_low_credit_warning') }}</p>
            @endif
        </div>
    @endif

    <div class="flex flex-wrap gap-3">
        <form method="POST" action="{{ route('finance.sms.connection-test') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">
                {{ __('finance.beem_test_connection') }}
            </button>
        </form>

        @can('view SMS history')
            <a href="{{ route('finance.sms.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-blue-300 transition hover:bg-slate-800">
                {{ __('finance.manage_sms') }}
            </a>
        @endcan
    </div>

    <form
        x-data="{ saving: false }"
        @submit="if (saving) { $event.preventDefault(); } else { saving = true; }"
        method="POST"
        action="{{ route('finance.sms.settings.update') }}"
        class="space-y-6 border-t border-slate-800 pt-6"
    >
        @csrf
        @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="flex items-start gap-3 rounded-xl border border-slate-800 bg-[#0b1120] p-4 text-sm text-slate-300">
                <input type="hidden" name="beem_enabled" value="0">
                <input type="checkbox" name="beem_enabled" value="1" @checked(old('beem_enabled', $smsSettings['enabled'] ? '1' : '0') === '1') class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
                <span>{{ __('finance.beem_enabled') }}</span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-slate-800 bg-[#0b1120] p-4 text-sm text-slate-300">
                <input type="hidden" name="beem_test_mode" value="0">
                <input type="checkbox" name="beem_test_mode" value="1" @checked(old('beem_test_mode', $smsSettings['testMode'] ? '1' : '0') === '1') class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
                <span><span class="block font-medium">{{ __('finance.beem_test_mode') }}</span><span class="mt-1 block text-xs text-slate-400">{{ __('finance.test_mode_help') }}</span></span>
            </label>
        </div>

        <p class="text-sm text-slate-400">{{ $smsSettings['credentialsConfigured'] ? __('finance.credentials_configured') : __('finance.credentials_missing') }}</p>
        <p class="text-xs text-slate-500">{{ __('finance.credentials_masked') }}</p>

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.beem_api_key') }}</span>
                <input type="password" name="beem_api_key" autocomplete="new-password" placeholder="{{ __('finance.credentials_placeholder') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                @error('beem_api_key')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.beem_api_secret') }}</span>
                <input type="password" name="beem_api_secret" autocomplete="new-password" placeholder="{{ __('finance.credentials_placeholder') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                @error('beem_api_secret')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
        </div>

        <label class="flex items-start gap-3 text-sm text-slate-300">
            <input type="checkbox" name="clear_beem_credentials" value="1" @checked(old('clear_beem_credentials')) class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
            <span>{{ __('finance.clear_beem_credentials') }}</span>
        </label>

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.beem_sender_id') }}</span>
                <input name="beem_sender_id" maxlength="11" value="{{ old('beem_sender_id', $smsSettings['senderId']) }}" autocomplete="off" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                @error('beem_sender_id')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.beem_language') }}</span>
                <select name="beem_language" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    <option value="sw" @selected(old('beem_language', $smsSettings['language']) === 'sw')>{{ __('members.kiswahili') }}</option>
                    <option value="en" @selected(old('beem_language', $smsSettings['language']) === 'en')>{{ __('members.english') }}</option>
                </select>
                @error('beem_language')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
        </div>

        <label class="block space-y-2 text-sm text-slate-300">
            <span>{{ __('finance.beem_low_credit_threshold') }}</span>
            <input type="number" name="beem_low_credit_threshold" min="0" max="1000000000" step="0.01" value="{{ old('beem_low_credit_threshold', $smsSettings['lowCreditThreshold']) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
            @error('beem_low_credit_threshold')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
        </label>

        <fieldset class="space-y-3">
            <legend class="text-sm font-semibold text-white">{{ __('finance.automatic_sms_notifications') }}</legend>
            @foreach ([
                'beem_auto_offering' => ['translation' => 'beem_auto_offering', 'value' => $smsSettings['autoOffering']],
                'beem_auto_contribution' => ['translation' => 'beem_auto_contribution', 'value' => $smsSettings['autoContribution']],
                'beem_auto_other_income' => ['translation' => 'beem_auto_other_income', 'value' => $smsSettings['autoOtherIncome']],
            ] as $field => $setting)
                <label class="flex items-start gap-3 text-sm text-slate-300">
                    <input type="hidden" name="{{ $field }}" value="0">
                    <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $setting['value'] ? '1' : '0') === '1') class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
                    <span>{{ __('finance.'.$setting['translation']) }}</span>
                </label>
            @endforeach
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-white">{{ __('finance.sms_templates') }}</legend>
            <p class="text-xs text-slate-400">{{ __('finance.sms_template_help') }}</p>
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ([
                    'beem_template_offering_en' => 'sms_template_offering_en',
                    'beem_template_offering_sw' => 'sms_template_offering_sw',
                    'beem_template_contribution_en' => 'sms_template_contribution_en',
                    'beem_template_contribution_sw' => 'sms_template_contribution_sw',
                ] as $field => $label)
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>{{ __('finance.'.$label) }}</span>
                        <textarea name="{{ $field }}" rows="3" required maxlength="500" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">{{ old($field, $smsSettings['templates'][str_replace('beem_template_', '', $field)]) }}</textarea>
                        @error($field)<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="flex justify-end border-t border-slate-800 pt-5">
            <button type="submit" x-bind:disabled="saving" class="rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60">
                <span x-show="!saving">{{ __('finance.save_settings') }}</span>
                <span x-cloak x-show="saving">{{ __('members.saving') }}</span>
            </button>
        </div>
    </form>

    @if ($canSendTestSms)
        <form
            x-data="{ type: 'offering', language: '{{ $smsSettings['language'] }}', previewMap: @js($smsTemplatePreviews), saving: false }"
            @submit="if (saving) { $event.preventDefault(); } else { saving = true; }"
            method="POST"
            action="{{ route('finance.sms.test') }}"
            class="space-y-5 border-t border-slate-800 pt-6"
        >
            @csrf
            <div>
                <h3 class="font-semibold text-white">{{ __('finance.test_sms') }}</h3>
                <p class="mt-1 text-sm text-slate-400">{{ __('finance.test_sms_description') }}</p>
            </div>

            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.phone') }}</span>
                <input type="tel" name="phone" required maxlength="40" value="{{ old('phone') }}" autocomplete="tel" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                @error('phone')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>

            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.sms_type') }}</span>
                    <select name="type" x-model="type" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                        <option value="offering">{{ __('finance.record_offering') }}</option>
                        <option value="contribution">{{ __('finance.record_contribution') }}</option>
                    </select>
                </label>
                <label class="block space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.beem_language') }}</span>
                    <select name="language" x-model="language" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                        <option value="sw">{{ __('members.kiswahili') }}</option>
                        <option value="en">{{ __('members.english') }}</option>
                    </select>
                </label>
            </div>

            <div class="rounded-xl border border-slate-800 bg-[#0b1120] p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('finance.sms_preview') }}</p>
                <p class="mt-2 text-sm text-slate-200" x-text="previewMap[language][type]"></p>
            </div>

            <label class="flex items-start gap-3 text-sm text-slate-300">
                <input type="checkbox" name="confirmation" value="1" required class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
                <span>{{ __('finance.test_sms_confirmation') }}</span>
            </label>

            <div class="flex justify-end">
                <button type="submit" x-bind:disabled="saving" class="rounded-lg border border-blue-500/40 bg-blue-600/20 px-5 py-2.5 text-sm font-semibold text-blue-200 transition hover:bg-blue-600/30 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="!saving">{{ __('finance.test_sms_submit') }}</span>
                    <span x-cloak x-show="saving">{{ __('members.saving') }}</span>
                </button>
            </div>
        </form>
    @endif

    <div class="rounded-xl border border-slate-800 bg-[#0b1120] p-4 text-sm text-slate-400">
        <p>{{ __('finance.beem_credit_note') }}</p>
        <a href="https://beem.africa" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex font-semibold text-blue-300 hover:text-blue-200">{{ __('finance.buy_sms_credits') }}</a>
    </div>
</section>
