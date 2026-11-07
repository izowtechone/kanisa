<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('communities.title') }}</p>
                <h2 class="mt-2 text-3xl font-bold text-white">{{ $community ? __('communities.edit_title') : __('communities.create_title') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('communities.description') }}</p>
            </div>
            <a href="{{ $community ? route('communities.show', $community) : route('communities.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-slate-800">
                {{ __('communities.back_to_list') }}
            </a>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300">
                <p class="mb-2 font-semibold">{{ __('communities.fix_errors') }}</p>
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ $community ? route('communities.update', $community) : route('communities.store') }}"
            x-data="{ saving: false }"
            @submit="if (saving) { $event.preventDefault(); } else { saving = true; }"
            class="space-y-6 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 shadow-xl sm:p-8"
        >
            @csrf
            @if ($community) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2">
                    <span>{{ __('communities.name') }} <span class="text-rose-400" aria-hidden="true">*</span></span>
                    <input name="name" required maxlength="150" value="{{ old('name', $community?->name) }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('name')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>

                @if ($community)
                    <div class="space-y-2 text-sm text-slate-300">
                        <span class="block">{{ __('communities.code') }}</span>
                        <p class="rounded-lg border border-slate-800 bg-[#0b1120] px-4 py-3 font-mono text-blue-300">{{ $community->code }}</p>
                    </div>
                @endif

                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('communities.location') }}</span>
                    <input name="location" maxlength="150" value="{{ old('location', $community?->location) }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100">
                    @error('location')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('communities.contact_phone') }}</span>
                    <input type="tel" name="contact_phone" maxlength="30" value="{{ old('contact_phone', $community?->contact_phone) }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100">
                    @error('contact_phone')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>{{ __('communities.contact_email') }}</span>
                    <input type="email" name="contact_email" maxlength="255" value="{{ old('contact_email', $community?->contact_email) }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100">
                    @error('contact_email')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>

                <label class="space-y-2 text-sm text-slate-300 sm:col-span-2">
                    <span>{{ __('communities.description_field') }}</span>
                    <textarea name="description" rows="4" maxlength="2000" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100">{{ old('description', $community?->description) }}</textarea>
                    @error('description')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
            </div>

            <section
                x-data="{
                    query: '',
                    results: [],
                    selected: @js($leader ? ['id' => $leader->id, 'name' => trim(collect([$leader->first_name, $leader->middle_name, $leader->last_name])->filter()->implode(' ')), 'membership_number' => $leader->membership_number] : null),
                    lookupUrl: @js(route('communities.leaders.search')),
                    message: '',
                    req: kkktLatestRequest(),
                    async lookup() {
                        const value = this.query.trim();
                        this.results = [];
                        this.message = '';
                        if (value.length < 2) {
                            this.req.cancel();
                            this.message = @js(__('communities.leader_search'));
                            return;
                        }
                        const url = new URL(this.lookupUrl);
                        url.searchParams.set('search', value);
                        try {
                            const outcome = await this.req.run(async (signal) => {
                                const response = await fetch(url, { headers: { 'Accept': 'application/json' }, signal });
                                if (!response.ok) {
                                    throw new Error('lookup-failed');
                                }
                                return response.json();
                            });
                            if (outcome.stale) {
                                return;
                            }
                            this.results = outcome.value.data;
                            this.message = this.results.length ? '' : @js(__('communities.leader_not_active'));
                        } catch {
                            this.message = @js(__('loading.failed'));
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
                class="space-y-3"
            >
                <h3 class="border-b border-slate-800 pb-3 text-lg font-semibold text-white">{{ __('communities.leader') }}</h3>
                <input type="hidden" name="leader_member_id" :value="selected ? selected.id : ''">
                <label for="leader-search" class="block text-sm text-slate-300">{{ __('communities.leader_search') }}</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input id="leader-search" type="search" x-model="query" @keydown.enter.prevent="lookup()" maxlength="100" autocomplete="off" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-slate-100" placeholder="{{ __('communities.leader_search') }}">
                    <button type="button" @click="lookup()" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-3 text-sm font-semibold text-blue-300 hover:bg-blue-500/20">{{ __('communities.search') }}</button>
                </div>
                @error('leader_member_id')<p role="alert" class="text-sm text-rose-300">{{ $message }}</p>@enderror
                <p x-cloak x-show="req.pending" role="status" class="flex items-center gap-2 text-sm text-slate-400"><span class="kkkt-loader" aria-hidden="true"></span>{{ __('loading.loading') }}</p>
                <p x-cloak x-show="message && !req.pending" aria-live="polite" class="text-sm text-slate-400" x-text="message"></p>
                <div x-cloak x-show="results.length" :aria-busy="req.pending" class="overflow-hidden rounded-lg border border-slate-700">
                    <ul class="divide-y divide-slate-800" role="listbox" aria-label="{{ __('communities.leader_search') }}">
                        <template x-for="member in results" :key="member.id">
                            <li role="option">
                                <button type="button" @click="choose(member)" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm text-slate-200 hover:bg-slate-800">
                                    <span x-text="member.name"></span>
                                    <span class="font-mono text-xs text-blue-300" x-text="member.membership_number"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
                <div x-cloak x-show="selected" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-800/70 bg-emerald-950/30 px-4 py-3">
                    <p class="text-sm text-emerald-200">
                        <span class="font-semibold" x-text="selected ? selected.name : ''"></span>
                        <span class="ml-2 font-mono text-xs text-emerald-300" x-text="selected ? selected.membership_number : ''"></span>
                    </p>
                    <button type="button" @click="clear()" class="text-sm font-semibold text-slate-300 underline underline-offset-4 hover:text-white">{{ __('communities.clear_leader') }}</button>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-800 pt-5">
                <a href="{{ $community ? route('communities.show', $community) : route('communities.index') }}" class="rounded-lg border border-slate-700 px-5 py-3 text-sm text-slate-300">{{ __('communities.cancel') }}</a>
                <button type="submit" :disabled="saving" class="rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="!saving">{{ __('communities.save') }}</span>
                    <span x-cloak x-show="saving">{{ __('members.saving') }}</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
