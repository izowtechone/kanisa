<x-app-layout>
    <div
        x-data="smsManualComposer(@js(__('finance.manual_sms_confirm')))"
        class="mx-auto max-w-3xl space-y-6"
    >
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold text-white">{{ __('finance.manual_sms_title') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('finance.manual_sms_description') }}</p>
            </div>
            <a href="{{ route('finance.sms.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">
                {{ __('finance.sms_title') }}
            </a>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless ($liveConfigured)
            <div role="alert" class="rounded-xl border border-amber-700/60 bg-amber-950/40 p-4 text-sm text-amber-200">
                {{ __('finance.manual_sms_requires_live_configuration') }}
                @can('manage SMS settings')
                    <a href="{{ route('settings.edit') }}#sms-notifications" class="ml-1 font-semibold underline">{{ __('members.open_settings') }}</a>
                @endcan
            </div>
        @endunless

        @if ($senderVerification['status'] !== 'approved')
            <div role="alert" class="rounded-xl border border-amber-700/60 bg-amber-950/40 p-4 text-sm text-amber-200">
                {{ __('finance.manual_sms_sender', ['sender' => $senderId ?: '—']) }} {{ __('finance.swala_sender_send_warning') }}
            </div>
        @endif

        <form method="POST" action="{{ route('finance.sms.send') }}" @submit="confirmSubmit($event)" class="space-y-5 rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
            @csrf
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.manual_sms_phone') }}</span>
                <input
                    type="tel"
                    name="phone"
                    value="{{ old('phone') }}"
                    inputmode="tel"
                    autocomplete="tel"
                    maxlength="16"
                    pattern="\+[1-9][0-9]{7,14}"
                    placeholder="+255712345678"
                    required
                    class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"
                >
                @error('phone')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>

            <p class="text-xs text-slate-400">{{ __('finance.manual_sms_sender', ['sender' => $senderId ?: '—']) }}</p>

            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.manual_sms_message') }}</span>
                <textarea
                    name="message"
                    x-model="message"
                    rows="6"
                    maxlength="3000"
                    required
                    class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"
                >{{ old('message') }}</textarea>
                @error('message')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>

            <div class="grid gap-3 sm:grid-cols-3">
                <p class="rounded-lg border border-slate-800 bg-[#0b1120] p-3 text-sm text-slate-300" x-text="@js(__('finance.sms_campaign_characters', ['count' => ':count'])).replace(':count', characterCount)"></p>
                <p class="rounded-lg border border-slate-800 bg-[#0b1120] p-3 text-sm text-slate-300">{{ __('finance.sms_campaign_encoding') }}: <span x-text="encodingType"></span></p>
                <p class="rounded-lg border border-slate-800 bg-[#0b1120] p-3 text-sm text-slate-300">{{ __('finance.manual_sms_segments') }}: <span x-text="segments"></span></p>
            </div>

            <p class="rounded-lg border border-amber-800/60 bg-amber-950/30 p-3 text-sm text-amber-200">{{ __('finance.manual_sms_cost_notice') }}</p>

            <label class="flex items-start gap-3 text-sm text-slate-300">
                <input type="checkbox" name="confirmation" value="1" required class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
                <span>{{ __('finance.manual_sms_confirmation') }}</span>
            </label>

            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="submitting || !message.trim() || {{ ! $liveConfigured ? 'true' : 'false' }}"
                    class="rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span x-show="!submitting">{{ __('finance.manual_sms_submit') }}</span>
                    <span x-cloak x-show="submitting">{{ __('members.saving') }}</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
