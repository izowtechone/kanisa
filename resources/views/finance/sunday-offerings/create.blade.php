<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('finance.sunday_offerings_title') }}</p>
                <h2 class="mt-2 text-3xl font-bold text-white">{{ __('finance.record_sunday_offering') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('finance.sunday_offerings_description') }}</p>
            </div>
            <a href="{{ route('finance.sunday-offerings.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-800">{{ __('communities.back_to_list') }}</a>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300">
                <p class="mb-2 font-semibold">{{ __('communities.fix_errors') }}</p>
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('finance.sunday-offerings.store') }}"
            x-data="{
                query: '',
                results: [],
                selected: @js($selectedMember ? [
                    'id' => $selectedMember->id,
                    'name' => trim(collect([$selectedMember->first_name, $selectedMember->middle_name, $selectedMember->last_name])->filter()->implode(' ')),
                    'membership_number' => $selectedMember->membership_number,
                    'envelope_number' => $selectedMember->envelope_number,
                    'phone' => $selectedMember->phone,
                    'community_name' => $selectedMember->community?->name,
                ] : null),
                lookupUrl: @js(route('finance.sunday-offerings.members.search')),
                message: '',
                saving: false,
                async lookup() {
                    const value = this.query.trim();
                    this.results = [];
                    this.message = '';
                    if (value.length < 2) {
                        this.message = @js(__('finance.sunday_offering_search_hint'));
                        return;
                    }
                    const url = new URL(this.lookupUrl);
                    url.searchParams.set('search', value);
                    try {
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) {
                            throw new Error('member-search-failed');
                        }
                        const payload = await response.json();
                        this.results = payload.data;
                        this.message = this.results.length ? '' : @js(__('finance.sunday_offering_no_member'));
                    } catch {
                        this.message = @js(__('members.community_lookup_failed'));
                    }
                },
                choose(member) {
                    this.selected = member;
                    this.results = [];
                    this.query = '';
                    this.message = '';
                },
                clear() {
                    this.selected = null;
                    this.query = '';
                    this.results = [];
                }
            }"
            @submit="if (saving) { $event.preventDefault(); } else { saving = true; }"
            class="space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 shadow-xl sm:p-8"
        >
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <input type="hidden" name="member_id" :value="selected ? selected.id : ''">
            <input type="hidden" name="type" value="offering">

            <section class="space-y-3">
                <h3 class="border-b border-slate-800 pb-3 text-lg font-semibold text-white">{{ __('finance.sunday_offering_member') }}</h3>
                <label for="member-search" class="block text-sm text-slate-300">{{ __('finance.sunday_offering_member_search') }}</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        id="member-search"
                        type="search"
                        x-model="query"
                        @keydown.enter.prevent="lookup()"
                        maxlength="100"
                        autocomplete="off"
                        aria-controls="sunday-offering-member-results"
                        class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100 placeholder:text-slate-500"
                        placeholder="{{ __('finance.sunday_offering_member_search') }}"
                    >
                    <button type="button" @click="lookup()" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-3 text-sm font-semibold text-blue-300 hover:bg-blue-500/20">{{ __('finance.search') }}</button>
                </div>
                <p class="text-xs text-slate-500">{{ __('finance.sunday_offering_search_hint') }}</p>
                @error('member_id')<p role="alert" class="text-sm text-rose-300">{{ $message }}</p>@enderror
                <p x-cloak x-show="message" aria-live="polite" class="text-sm text-slate-400" x-text="message"></p>
                <div id="sunday-offering-member-results" x-cloak x-show="results.length" class="overflow-hidden rounded-lg border border-slate-700">
                    <ul class="divide-y divide-slate-800" role="listbox" aria-label="{{ __('finance.sunday_offering_select_member') }}">
                        <template x-for="member in results" :key="member.id">
                            <li role="option">
                                <button type="button" @click="choose(member)" class="flex w-full flex-wrap items-center justify-between gap-2 px-4 py-3 text-left text-sm text-slate-200 hover:bg-slate-800">
                                    <span>
                                        <span class="block font-semibold" x-text="member.name"></span>
                                        <span class="font-mono text-xs text-slate-400" x-text="member.membership_number"></span>
                                    </span>
                                    <span class="text-xs text-slate-400" x-text="member.envelope_number"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
                <div x-cloak x-show="selected" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-emerald-800/70 bg-emerald-950/30 p-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-emerald-200" x-text="selected ? selected.name : ''"></p>
                        <p class="mt-1 font-mono text-xs text-emerald-300" x-text="selected ? selected.membership_number : ''"></p>
                        <p class="mt-1 text-xs text-slate-400">
                            <span x-text="selected ? selected.envelope_number : ''"></span>
                            <span x-show="selected && selected.phone" x-text="selected ? ' · ' + selected.phone : ''"></span>
                            <span x-show="selected && selected.community_name" x-text="selected ? ' · ' + selected.community_name : ''"></span>
                        </p>
                    </div>
                    <button type="button" @click="clear()" class="text-sm font-semibold text-slate-300 underline underline-offset-4 hover:text-white">{{ __('finance.sunday_offering_clear_member') }}</button>
                </div>
            </section>

            <div class="grid gap-5 sm:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2">
                    <span>{{ __('finance.category') }} <span class="text-rose-400" aria-hidden="true">*</span></span>
                    <select name="category_id" required class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100">
                        <option value="">{{ __('finance.choose_category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.amount') }} ({{ $currency }}) <span class="text-rose-400" aria-hidden="true">*</span></span>
                    <input name="amount" required inputmode="decimal" maxlength="16" value="{{ old('amount') }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100">
                    @error('amount')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.payment_method') }} <span class="text-rose-400" aria-hidden="true">*</span></span>
                    <select name="payment_method" required class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100">
                        <option value="">{{ __('finance.choose_payment_method') }}</option>
                        @foreach (['cash', 'bank_transfer', 'mobile_money', 'cheque', 'other'] as $method)
                            <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('finance.payment_methods.'.$method) }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.transaction_date') }} <span class="text-rose-400" aria-hidden="true">*</span></span>
                    <input type="date" name="transaction_date" required max="{{ now()->toDateString() }}" value="{{ old('transaction_date', now()->toDateString()) }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100 [color-scheme:dark]">
                    @error('transaction_date')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.reference_number') }}</span>
                    <input name="reference_number" maxlength="100" value="{{ old('reference_number') }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100">
                    @error('reference_number')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>

                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2">
                    <span>{{ __('finance.description_field') }}</span>
                    <textarea name="description" rows="3" maxlength="500" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100">{{ old('description') }}</textarea>
                    @error('description')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
            </div>

            <div role="note" class="space-y-2 rounded-xl border border-amber-800/70 bg-amber-950/30 p-4 text-sm text-amber-200">
                <p>{{ __('finance.sunday_offering_sms_approval_note') }}</p>
                <p>{{ __('finance.sunday_offering_sms_consent_note') }}</p>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-800 pt-5">
                <a href="{{ route('finance.sunday-offerings.index') }}" class="rounded-lg border border-slate-700 px-5 py-3 text-sm text-slate-300">{{ __('members.cancel') }}</a>
                <button type="submit" :disabled="saving" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="!saving">{{ __('finance.record') }}</span>
                    <span x-cloak x-show="saving" class="inline-flex items-center gap-2">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        {{ __('finance.saving') }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
