<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="text-3xl font-bold text-white">{{ $campaign->exists ? __('finance.campaign_edit_title') : __('finance.campaign_create_title') }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ $campaign->exists ? __('finance.campaign_edit_description') : __('finance.campaign_create_description') }}</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $campaign->exists ? route('finance.campaigns.update', $campaign) : route('finance.campaigns.store') }}" class="space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-8">
            @csrf
            @if ($campaign->exists) @method('PUT') @endif
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.campaign_code') }} <span class="text-rose-400">*</span></span>
                    <input required name="code" maxlength="40" value="{{ old('code', $campaign->code) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    @error('code')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.campaign_name') }} <span class="text-rose-400">*</span></span>
                    <input required name="name" maxlength="150" value="{{ old('name', $campaign->name) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    @error('name')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.campaign_start') }}</span>
                    <input type="date" name="starts_on" value="{{ old('starts_on', $campaign->starts_on?->format('Y-m-d')) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    @error('starts_on')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.campaign_end') }}</span>
                    <input type="date" name="ends_on" value="{{ old('ends_on', $campaign->ends_on?->format('Y-m-d')) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    @error('ends_on')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2">
                    <span>{{ __('finance.description_field') }}</span>
                    <textarea name="description" rows="4" maxlength="2000" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">{{ old('description', $campaign->description) }}</textarea>
                    @error('description')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-800 pt-5">
                <a href="{{ $campaign->exists ? route('finance.campaigns.show', $campaign) : route('finance.campaigns.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300">{{ __('finance.cancel') }}</a>
                <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.save_campaign') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
