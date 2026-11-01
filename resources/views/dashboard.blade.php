<x-app-layout :title="__('dashboard.title')">
    @if ($memberStatistics)
        <div class="mx-auto max-w-7xl space-y-8">
            <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[2px] text-blue-400">
                        {{ __('dashboard.overview') }}
                    </p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-white lg:text-3xl">
                        {{ __('dashboard.welcome', ['name' => Auth::user()->name]) }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-400">{{ __('dashboard.welcome_description') }}</p>
                </div>

                <div class="rounded-xl border border-slate-800 bg-[#101827] px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ __('dashboard.today') }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-200">
                        {{ now()->locale(app()->getLocale())->translatedFormat('d M Y') }}
                    </p>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3" aria-label="{{ __('dashboard.member_statistics') }}">
                @foreach ([
                    ['label' => 'dashboard.total_members', 'description' => 'dashboard.total_members_description', 'value' => $memberStatistics->total_members, 'icon' => 'bg-blue-500/10 text-blue-400'],
                    ['label' => 'dashboard.active_members', 'description' => 'dashboard.active_members_description', 'value' => $memberStatistics->active_members, 'icon' => 'bg-emerald-500/10 text-emerald-400'],
                    ['label' => 'dashboard.inactive_members', 'description' => 'dashboard.inactive_members_description', 'value' => $memberStatistics->inactive_members, 'icon' => 'bg-slate-500/10 text-slate-400'],
                    ['label' => 'dashboard.male_members', 'description' => 'dashboard.male_members_description', 'value' => $memberStatistics->male_members, 'icon' => 'bg-sky-500/10 text-sky-400'],
                    ['label' => 'dashboard.female_members', 'description' => 'dashboard.female_members_description', 'value' => $memberStatistics->female_members, 'icon' => 'bg-violet-500/10 text-violet-400'],
                    ['label' => 'dashboard.new_members', 'description' => 'dashboard.new_members_description', 'value' => $memberStatistics->new_members_this_month, 'icon' => 'bg-amber-500/10 text-amber-400'],
                ] as $stat)
                    <article class="rounded-2xl border border-slate-800 bg-[#101827] p-6 shadow-xl transition duration-200 hover:-translate-y-1 hover:border-blue-500/60">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm text-slate-400">{{ __($stat['label']) }}</p>
                                <p class="mt-3 text-3xl font-bold text-white">{{ number_format((int) $stat['value']) }}</p>
                                <p class="mt-2 text-xs text-slate-500">{{ __($stat['description']) }}</p>
                            </div>
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $stat['icon'] }}" aria-hidden="true">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="10" cy="7" r="4"/>
                                    <path d="M20 21v-2a4 4 0 0 0-3-3.87"/>
                                </svg>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <div class="rounded-2xl border border-slate-800 bg-[#101827] p-6 shadow-xl xl:col-span-2">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-bold text-white">{{ __('dashboard.recent_members') }}</h2>
                            <p class="mt-1 text-xs text-slate-500">{{ __('dashboard.recent_members_description') }}</p>
                        </div>
                        <a href="{{ route('members.index') }}" class="text-sm font-semibold text-blue-400 transition hover:text-blue-300">
                            {{ __('dashboard.view_all_members') }}
                        </a>
                    </div>

                    @if ($recentMembers->isEmpty())
                        <p class="mt-6 rounded-xl border border-dashed border-slate-700 px-5 py-8 text-center text-sm text-slate-400">
                            {{ __('dashboard.no_recent_members') }}
                        </p>
                    @else
                        <ul class="mt-5 divide-y divide-slate-800">
                            @foreach ($recentMembers as $member)
                                <li class="flex items-center gap-3 py-4 sm:gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-blue-500/20 bg-blue-500/10 text-sm font-semibold text-blue-300" aria-hidden="true">
                                        {{ strtoupper(substr($member->first_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-200">
                                            {{ collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' ') }}
                                        </p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $member->membership_number }}</p>
                                    </div>
                                    @if ($member->created_at)
                                        <time class="shrink-0 text-xs text-slate-500" datetime="{{ $member->created_at->toAtomString() }}">
                                            {{ $member->created_at->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                        </time>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <aside class="rounded-2xl border border-slate-800 bg-[#101827] p-6 shadow-xl">
                    <h2 class="text-base font-bold text-white">{{ __('dashboard.quick_actions') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('dashboard.quick_actions_description') }}</p>

                    <div class="mt-6 space-y-3">
                        @can('create members')
                            <a href="{{ route('members.create') }}" class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/40 p-3 transition hover:border-blue-500/40 hover:bg-slate-800/80">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-400" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M12 5v14M5 12h14"/>
                                    </svg>
                                </span>
                                <span class="flex-1">
                                    <span class="block text-sm font-semibold text-slate-200">{{ __('dashboard.register_member') }}</span>
                                    <span class="mt-1 block text-xs text-slate-500">{{ __('dashboard.register_member_description') }}</span>
                                </span>
                            </a>
                        @endcan

                        @can('view members')
                            <a href="{{ route('members.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/40 p-3 transition hover:border-blue-500/40 hover:bg-slate-800/80">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 text-sky-400" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <circle cx="11" cy="11" r="7"/>
                                        <path d="m20 20-4-4"/>
                                    </svg>
                                </span>
                                <span class="flex-1">
                                    <span class="block text-sm font-semibold text-slate-200">{{ __('dashboard.browse_members') }}</span>
                                    <span class="mt-1 block text-xs text-slate-500">{{ __('dashboard.browse_members_description') }}</span>
                                </span>
                            </a>
                        @endcan

                        @can('manage settings')
                            <a href="{{ route('settings.edit') }}" class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/40 p-3 transition hover:border-blue-500/40 hover:bg-slate-800/80">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-400" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-2.6V20a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1A1.7 1.7 0 0 0 8 15a1.7 1.7 0 0 0-1.6-1H6v-2.6h.2a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6v-.2h2.6v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2V14h-.2a1.7 1.7 0 0 0-1.6 1Z"/>
                                    </svg>
                                </span>
                                <span class="flex-1">
                                    <span class="block text-sm font-semibold text-slate-200">{{ __('dashboard.manage_settings') }}</span>
                                    <span class="mt-1 block text-xs text-slate-500">{{ __('dashboard.manage_settings_description') }}</span>
                                </span>
                            </a>
                        @endcan

                        @if (! Auth::user()->can('create members') && ! Auth::user()->can('view members') && ! Auth::user()->can('manage settings'))
                            <p class="rounded-xl border border-dashed border-slate-700 px-4 py-6 text-center text-sm text-slate-500">
                                {{ __('dashboard.no_quick_actions') }}
                            </p>
                        @endif
                    </div>
                </aside>
            </section>
        </div>
    @else
        <section class="mx-auto max-w-7xl rounded-2xl border border-slate-800 bg-[#101827] p-6 shadow-xl">
            <p class="text-sm text-slate-300">{{ __('dashboard.welcome', ['name' => Auth::user()->name]) }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ __('dashboard.no_member_access') }}</p>
        </section>
    @endif
</x-app-layout>
