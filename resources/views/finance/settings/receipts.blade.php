<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="text-3xl font-bold text-white">{{ __('finance.receipt_settings_title') }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ __('finance.receipt_settings_description') }}</p>
        </div>
        @if (session('success'))<div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('finance.receipts.settings.update') }}" class="space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-8">
            @csrf @method('PUT')
            <label class="block space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.receipt_prefix') }}</span>
                <input required name="receipt_prefix" maxlength="20" value="{{ old('receipt_prefix', $receiptPrefix) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                @error('receipt_prefix')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
            <div class="flex justify-end border-t border-slate-800 pt-5">
                <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.save_settings') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
