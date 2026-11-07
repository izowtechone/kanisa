<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div><h2 class="text-3xl font-bold text-white">{{ __('finance.record_by_envelope') }}</h2><p class="mt-2 text-sm text-slate-400">{{ __('finance.envelope_entry_description') }}</p></div>
        @if ($errors->any())<div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('finance.envelopes.store') }}" x-data="{
            envelope: @js(old('envelope_code', '')),
            type: @js(old('type', 'offering')),
            memberText: '',
            found: false,
            saving: false,
            req: kkktLatestRequest(),
            lookupUrl: @js(route('finance.envelopes.lookup')),
            async lookupMember() {
                this.memberText = '';
                this.found = false;
                if (!this.envelope.trim()) { this.req.cancel(); return; }
                const url = new URL(this.lookupUrl);
                url.searchParams.set('envelope_code', this.envelope.trim());
                try {
                    const outcome = await this.req.run(async (signal) => {
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' }, signal });
                        return { ok: response.ok, member: response.ok ? await response.json() : null };
                    });
                    if (outcome.stale) return;
                    if (!outcome.value.ok) {
                        this.memberText = @js(__('finance.member_not_found'));
                        return;
                    }
                    const member = outcome.value.member;
                    this.memberText = @js(__('finance.member_lookup_result', ['name' => ':name', 'number' => ':number'])).replace(':name', member.name).replace(':number', member.membership_number);
                    this.found = true;
                } catch {
                    this.memberText = @js(__('loading.failed'));
                }
            }
        }" @submit="saving = true" class="space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-8">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.type') }} <span class="text-rose-400">*</span></span><select name="type" x-model="type" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"><option value="offering">{{ __('finance.types.offering') }}</option><option value="tithe">{{ __('finance.types.tithe') }}</option></select></label>
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.category') }} <span class="text-rose-400">*</span></span><select name="category_id" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"><option value="">{{ __('finance.choose_category') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" data-category-type="{{ $category->type }}" @selected((string) old('category_id') === (string) $category->id)>{{ __('finance.types.'.$category->type) }} · {{ $category->localizedName() }}</option>@endforeach</select></label>
                <div class="space-y-2 sm:col-span-2">
                    <label for="envelope_code" class="block text-sm text-slate-300">{{ __('finance.envelope_code') }} <span class="text-rose-400">*</span></label>
                    <div class="flex flex-col gap-2 sm:flex-row"><input id="envelope_code" name="envelope_code" required maxlength="255" x-model="envelope" value="{{ old('envelope_code') }}" class="min-w-0 flex-1 rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"><button type="button" @click="lookupMember" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-2.5 text-sm font-semibold text-blue-300 hover:bg-blue-500/20">{{ __('finance.lookup_member') }}</button></div>
                    <p class="text-xs text-slate-500">{{ __('finance.member_number_hint') }}</p>
                    <p x-cloak x-show="req.pending" role="status" class="flex items-center gap-2 text-sm text-slate-400"><span class="kkkt-loader" aria-hidden="true"></span>{{ __('loading.loading') }}</p>
                    <p x-cloak x-show="memberText && !req.pending" aria-live="polite" :class="found ? 'text-emerald-300' : 'text-rose-300'" class="text-sm" x-text="memberText"></p>
                    @error('envelope_code')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </div>
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.amount') }} <span class="text-rose-400">*</span></span><input name="amount" required inputmode="decimal" value="{{ old('amount') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">@error('amount')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror</label>
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.payment_method') }} <span class="text-rose-400">*</span></span><select name="payment_method" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"><option value="">{{ __('finance.choose_payment_method') }}</option>@foreach (['cash', 'bank_transfer', 'mobile_money', 'cheque', 'other'] as $method)<option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('finance.payment_methods.'.$method) }}</option>@endforeach</select></label>
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.transaction_date') }} <span class="text-rose-400">*</span></span><input type="date" name="transaction_date" required max="{{ now()->toDateString() }}" value="{{ old('transaction_date', now()->toDateString()) }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"></label>
                <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.reference_number') }}</span><input name="reference_number" value="{{ old('reference_number') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"></label>
                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2"><span>{{ __('finance.description_field') }}</span><input name="description" maxlength="500" value="{{ old('description') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"></label>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-800 pt-5"><a href="{{ route('finance.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300">{{ __('finance.cancel') }}</a><button :disabled="saving" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-60"><span x-show="!saving">{{ __('finance.record') }}</span><span x-cloak x-show="saving">{{ __('finance.saving') }}</span></button></div>
        </form>
        @can('import envelope offerings')<a class="text-sm font-semibold text-blue-300 hover:text-blue-200" href="{{ route('finance.imports.create') }}">{{ __('finance.import_envelope_offerings') }}</a>@endcan
    </div>
</x-app-layout>
