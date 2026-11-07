<section id="sms-notifications" class="scroll-mt-24 space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-6 shadow-xl sm:p-8">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('finance.sms_notifications_title') }}</p>
        <h2 class="mt-2 text-xl font-bold text-white">{{ __('finance.swala_settings') }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ __('finance.sms_notifications_description') }}</p>
    </div>

    <div class="flex flex-wrap gap-3">
        @can('view SMS history')
            <a href="{{ route('finance.sms.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-blue-300 transition hover:bg-slate-800">
                {{ __('finance.manage_sms') }}
            </a>
        @endcan
    </div>

    <div role="note" class="space-y-2 rounded-xl border border-amber-800/70 bg-amber-950/30 p-4 text-sm text-amber-200">
        <p>{{ __('finance.sms_delivery_tracking_note') }}</p>
        <p>{{ __('finance.sms_consent_policy') }}</p>
        <p>{{ __('finance.swala_sender_verification_note') }}</p>
    </div>

    <div class="space-y-3 rounded-xl border border-slate-800 bg-[#0b1120] p-4 text-sm text-slate-300">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-semibold text-white">{{ __('finance.swala_connection') }}</p>
                @if ($smsSettings['lastCheckAt'])
                    <p class="mt-1 text-xs text-slate-400">
                        {{ __('finance.swala_last_checked', ['date' => \Illuminate\Support\Carbon::parse($smsSettings['lastCheckAt'])->format('Y-m-d H:i')]) }}
                        —
                        @if ($smsSettings['lastCheckStatus'] === 'ok')
                            <span class="text-emerald-300">{{ __('finance.swala_last_balance', ['balance' => $smsSettings['lastBalance']]) }}</span>
                        @else
                            <span class="text-rose-300">{{ $smsSettings['lastCheckMessage'] }}</span>
                        @endif
                    </p>
                @else
                    <p class="mt-1 text-xs text-slate-500">{{ __('finance.swala_never_checked') }}</p>
                @endif
                @if ($smsSettings['lastSuccessAt'])
                    <p class="mt-1 text-xs text-slate-400">{{ __('finance.swala_last_success', ['date' => \Illuminate\Support\Carbon::parse($smsSettings['lastSuccessAt'])->format('Y-m-d H:i')]) }}</p>
                @endif
            </div>
            @if ($smsSettings['credentialsConfigured'])
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('finance.sms.settings.test-connection') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-blue-500/40 bg-blue-600/20 px-4 py-2 text-sm font-semibold text-blue-200 transition hover:bg-blue-600/30">{{ __('finance.swala_test_connection') }}</button>
                    </form>
                </div>
            @endif
        </div>
        @error('sms')<p class="text-xs text-rose-300">{{ $message }}</p>@enderror
    </div>

    <div class="space-y-3 rounded-xl border border-slate-800 bg-[#0b1120] p-4 text-sm text-slate-300">
        <h3 class="font-semibold text-white">{{ __('finance.swala_sender_list_title') }}</h3>
        <p class="font-semibold text-white">{{ __('finance.swala_sender_id') }}: {{ $smsSettings['senderId'] ?: '—' }}</p>
        <p>{{ __('finance.swala_sender_status') }}: {{ __('finance.swala_sender_statuses.'.$smsSettings['senderVerification']['status']) }}</p>
        @if ($smsSettings['senderVerification']['checked_at'])
            <p>{{ __('finance.swala_last_checked', ['date' => \Illuminate\Support\Carbon::parse($smsSettings['senderVerification']['checked_at'])->format('Y-m-d H:i')]) }}</p>
        @else
            <p>{{ __('finance.swala_never_checked') }}</p>
        @endif
        @if ($smsSettings['senderVerification']['reason'])
            <p>{{ __('finance.swala_sender_verification_reasons.'.$smsSettings['senderVerification']['reason']) }}</p>
        @endif
        @if ($smsSettings['senderVerification']['status'] !== 'approved')
            <p role="alert" class="text-amber-200">{{ __('finance.swala_sender_send_warning') }}</p>
        @endif
        @if ($smsSettings['senderList']['success_at'])
            <p>{{ __('finance.swala_sender_list_last_success', ['date' => \Illuminate\Support\Carbon::parse($smsSettings['senderList']['success_at'])->format('Y-m-d H:i')]) }}</p>
            @if ($smsSettings['senderList']['error'])
                <p role="alert" class="text-amber-200">{{ __('finance.swala_sender_list_stale') }}</p>
            @endif
            @if (count($smsSettings['senderList']['senders']))
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-700">
                                @foreach (['swala_sender_id', 'swala_sender_country', 'swala_sender_purpose', 'swala_sender_status', 'swala_sender_usable', 'swala_sender_default', 'swala_sender_updated'] as $heading)
                                    <th scope="col" class="px-3 py-2">{{ __('finance.'.$heading) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($smsSettings['senderList']['senders'] as $sender)
                                <tr class="border-b border-slate-800">
                                    <td class="px-3 py-2">{{ $sender['sender_id'] }}</td>
                                    <td class="px-3 py-2">{{ $sender['country_name'] ?: '—' }} {{ $sender['country_code'] }}</td>
                                    <td class="px-3 py-2">{{ $sender['purpose'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ in_array($sender['status'], ['approved', 'processing', 'pending', 'rejected', 'unverified', 'suspended'], true) ? __('finance.swala_sender_statuses.'.$sender['status']) : $sender['status'] }}</td>
                                    <td class="px-3 py-2">{{ $sender['is_usable'] ? __('finance.swala_sender_yes') : __('finance.swala_sender_no') }}</td>
                                    <td class="px-3 py-2">{{ $sender['is_default'] === null ? '—' : ($sender['is_default'] ? __('finance.swala_sender_yes') : __('finance.swala_sender_no')) }}</td>
                                    <td class="px-3 py-2">{{ $sender['updated_at'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p>{{ __('finance.swala_sender_list_empty') }}</p>
            @endif
        @endif
        <form method="POST" action="{{ route('finance.sms.settings.verify-sender') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-blue-500/40 bg-blue-600/20 px-4 py-2 text-sm font-semibold text-blue-200 transition hover:bg-blue-600/30">{{ __('finance.swala_verify_again') }}</button>
        </form>
        @if ($smsSettings['senderList']['success_at'] && ! $smsSettings['senderList']['error'])
            @php($selectableSenders = collect($smsSettings['senderList']['senders'])->filter(fn ($sender) => $sender['status'] === 'approved' && $sender['is_usable'] && mb_strlen($sender['sender_id']) <= 32)->unique('sender_id'))
            @if ($selectableSenders->isNotEmpty())
                <form method="POST" action="{{ route('finance.sms.settings.select-sender') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="block space-y-2">
                        <span>{{ __('finance.swala_sender_select') }}</span>
                        <select name="sender_id" required class="block rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                            <option value="">{{ __('finance.swala_sender_select_placeholder') }}</option>
                            @foreach ($selectableSenders as $sender)
                                <option value="{{ $sender['sender_id'] }}">{{ $sender['sender_id'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">{{ __('finance.swala_sender_save_selection') }}</button>
                </form>
                @error('sender_id')<p role="alert" class="text-rose-300">{{ $message }}</p>@enderror
            @endif
        @endif
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
                <input type="hidden" name="swala_enabled" value="0">
                <input type="checkbox" name="swala_enabled" value="1" @checked(old('swala_enabled', $smsSettings['enabled'] ? '1' : '0') === '1') class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
                <span>{{ __('finance.swala_enabled') }}</span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-slate-800 bg-[#0b1120] p-4 text-sm text-slate-300">
                <input type="hidden" name="swala_test_mode" value="0">
                <input type="checkbox" name="swala_test_mode" value="1" @checked(old('swala_test_mode', $smsSettings['testMode'] ? '1' : '0') === '1') class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
                <span><span class="block font-medium">{{ __('finance.swala_test_mode') }}</span><span class="mt-1 block text-xs text-slate-400">{{ __('finance.test_mode_help') }}</span></span>
            </label>
        </div>

        <p class="text-sm text-slate-400">
            {{ $smsSettings['credentialsConfigured'] ? __('finance.credentials_configured') : __('finance.credentials_missing') }}
            @if ($smsSettings['keyEnvironment'])
                <span class="ml-1 rounded-full border border-slate-700 px-2 py-0.5 text-xs text-slate-300">{{ __('finance.swala_key_environment.'.$smsSettings['keyEnvironment']) }}</span>
            @endif
        </p>
        <p class="text-xs text-slate-500">{{ __('finance.credentials_masked') }}</p>

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.swala_api_key') }}</span>
                <input type="password" name="swala_api_key" autocomplete="new-password" placeholder="{{ __('finance.credentials_placeholder') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                <span class="block text-xs text-slate-500">{{ __('finance.swala_api_key_help') }}</span>
                @error('swala_api_key')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.swala_timeout') }}</span>
                <input type="number" name="swala_timeout" min="1" max="60" required value="{{ old('swala_timeout', $smsSettings['timeout']) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                <span class="block text-xs text-slate-500">{{ __('finance.swala_timeout_help') }}</span>
                @error('swala_timeout')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="rounded-xl border border-slate-800 bg-[#0b1120] p-4 text-sm text-slate-300">
            <p>{{ __('finance.swala_delivery_status_polling') }}</p>
        </div>

        <label class="flex items-start gap-3 text-sm text-slate-300">
            <input type="checkbox" name="clear_swala_credentials" value="1" @checked(old('clear_swala_credentials')) class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
            <span>{{ __('finance.clear_swala_credentials') }}</span>
        </label>
        @error('clear_swala_credentials')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.swala_sender_id') }}</span>
                <input name="swala_sender_id" maxlength="32" value="{{ old('swala_sender_id', $smsSettings['senderId']) }}" autocomplete="off" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                <span class="block text-xs text-slate-400">{{ __('finance.swala_sender_manual_help') }}</span>
                @error('swala_sender_id')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.sms_language') }}</span>
                <select name="sms_language" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    <option value="sw" @selected(old('sms_language', $smsSettings['language']) === 'sw')>{{ __('members.kiswahili') }}</option>
                    <option value="en" @selected(old('sms_language', $smsSettings['language']) === 'en')>{{ __('members.english') }}</option>
                </select>
                @error('sms_language')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
        </div>

        <fieldset class="space-y-3">
            <legend class="text-sm font-semibold text-white">{{ __('finance.automatic_sms_notifications') }}</legend>
            @foreach ([
                'sms_auto_offering' => ['translation' => 'sms_auto_offering', 'value' => $smsSettings['autoOffering']],
                'sms_auto_contribution' => ['translation' => 'sms_auto_contribution', 'value' => $smsSettings['autoContribution']],
                'sms_auto_other_income' => ['translation' => 'sms_auto_other_income', 'value' => $smsSettings['autoOtherIncome']],
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
                    'sms_template_offering_en' => 'sms_template_offering_en',
                    'sms_template_offering_sw' => 'sms_template_offering_sw',
                    'sms_template_contribution_en' => 'sms_template_contribution_en',
                    'sms_template_contribution_sw' => 'sms_template_contribution_sw',
                    'sms_template_sunday_offering_en' => 'sms_template_sunday_offering_en',
                    'sms_template_sunday_offering_sw' => 'sms_template_sunday_offering_sw',
                ] as $field => $label)
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>{{ __('finance.'.$label) }}</span>
                        <textarea name="{{ $field }}" rows="3" required maxlength="500" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">{{ old($field, $smsSettings['templates'][str_replace('sms_template_', '', $field)]) }}</textarea>
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
                <p class="mt-2 text-sm text-amber-200">{{ __('finance.test_sms_warning') }}</p>
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
                    <span>{{ __('finance.sms_language') }}</span>
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
        <p>{{ __('finance.swala_credit_note') }}</p>
    </div>
</section>
