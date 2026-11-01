<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="text-3xl font-bold text-white">{{ __('finance.settings_title') }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ __('finance.settings_description') }}</p>
        </div>
        @if (session('success'))<div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ route('finance.settings.update') }}" class="space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-8">
            @csrf @method('PUT')
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.currency') }}</span>
                <input required name="currency" maxlength="3" value="{{ old('currency', $currency) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] uppercase text-slate-100">
                @error('currency')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                <span class="block text-xs text-slate-500">{{ __('finance.currency_help') }}</span>
            </label>
            <div class="flex flex-wrap justify-between gap-3 border-t border-slate-800 pt-5">
                <div class="flex flex-wrap gap-3">
                    @can('manage receipts')<a href="{{ route('finance.receipts.settings.edit') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200">{{ __('finance.receipt_settings_title') }}</a>@endcan
                    @can('manage SMS settings')<a href="{{ route('settings.edit') }}#sms-notifications" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200">{{ __('finance.sms_settings_title') }}</a>@endcan
                </div>
                <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.save_settings') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
