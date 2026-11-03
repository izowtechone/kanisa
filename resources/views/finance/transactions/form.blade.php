<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div><h2 class="text-3xl font-bold text-white">{{ __('finance.record_'.$type) }}</h2><p class="mt-2 text-sm text-slate-400">{{ __('finance.recording_guidance') }}</p></div>
        @if ($errors->any())<div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('finance.transactions.store', $type) }}" x-data="{ saving: false, anonymous: {{ old('is_anonymous') ? 'true' : 'false' }} }" @submit="saving = true" class="space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-8">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.category') }} <span class="text-rose-400">*</span></span><select required name="category_id" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"><option value="">{{ __('finance.choose_category') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->localizedName() }}</option>@endforeach</select>@error('category_id')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                @if ($type === 'contribution')
                    <label class="space-y-2 text-sm text-slate-300 sm:col-span-2">
                        <span>{{ __('finance.campaign') }} <span class="text-rose-400">*</span></span>
                        <select required name="campaign_id" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                            <option value="">{{ __('finance.choose_campaign') }}</option>
                            @foreach ($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" @selected((string) old('campaign_id') === (string) $campaign->id)>{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                        @error('campaign_id')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                        @if ($campaigns->isEmpty())<span class="text-xs text-amber-300">{{ __('finance.no_active_campaign') }} <a class="font-semibold underline" href="{{ route('finance.campaigns.create') }}">{{ __('finance.campaign_create') }}</a></span>@endif
                    </label>
                @endif
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.amount') }} ({{ $currency ?? 'TZS' }}) <span class="text-rose-400">*</span></span><input required name="amount" inputmode="decimal" value="{{ old('amount') }}" placeholder="0.00" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">@error('amount')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.payment_method') }} <span class="text-rose-400">*</span></span><select required name="payment_method" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"><option value="">{{ __('finance.choose_payment_method') }}</option>@foreach (['cash', 'bank_transfer', 'mobile_money', 'cheque', 'other'] as $method)<option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('finance.payment_methods.'.$method) }}</option>@endforeach</select>@error('payment_method')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.transaction_date') }} <span class="text-rose-400">*</span></span><input required type="date" max="{{ now()->toDateString() }}" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">@error('transaction_date')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                @if ($type === 'contribution')
                    <label class="space-y-2 text-sm text-slate-300 sm:col-span-2"><span>{{ __('finance.member_number') }}</span><input x-bind:disabled="anonymous" name="member_number" value="{{ old('member_number') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">@error('member_number')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                    <label class="flex items-center gap-3 text-sm text-slate-300 sm:col-span-2"><input type="checkbox" name="is_anonymous" value="1" x-model="anonymous" @checked(old('is_anonymous')) class="rounded border-slate-600 bg-[#0b1120] text-blue-500"><span>{{ __('finance.anonymous') }}</span></label>
                @endif
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.reference_number') }}</span><input name="reference_number" value="{{ old('reference_number') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">@error('reference_number')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                @if ($type === 'expense')
                    <label class="space-y-2 text-sm text-slate-300 sm:col-span-2"><span>{{ __('finance.payee') }} <span class="text-rose-400">*</span></span><input required name="payee" maxlength="200" value="{{ old('payee') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">@error('payee')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                @endif
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.description_field') }}</span><input name="description" value="{{ old('description') }}" maxlength="500" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">@error('description')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2"><span>{{ __('finance.notes') }}</span><textarea name="notes" rows="3" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">{{ old('notes') }}</textarea>@error('notes')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-5">
                <p class="text-sm text-slate-400">{{ __('finance.recording_guidance') }}</p>
                <div class="flex gap-3"><a href="{{ route('finance.transactions.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300">{{ __('finance.cancel') }}</a><button type="submit" :disabled="saving" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-60"><span x-show="!saving">{{ __('finance.record') }}</span><span x-cloak x-show="saving" role="status">{{ __('finance.saving') }}</span></button></div>
            </div>
        </form>
    </div>
</x-app-layout>
