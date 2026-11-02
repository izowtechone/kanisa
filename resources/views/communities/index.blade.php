<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('dashboard.administration') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ __('communities.title') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('communities.description') }}</p>
            </div>
            @can('create communities')
                <a href="{{ route('communities.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">
                    {{ __('communities.create') }}
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300">
                <p class="mb-2 font-semibold">{{ __('communities.fix_errors') }}</p>
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="GET" action="{{ route('communities.index') }}" class="grid gap-3 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:grid-cols-[minmax(0,1fr)_14rem_auto_auto]">
            <label class="sr-only" for="community-search">{{ __('communities.search') }}</label>
            <input id="community-search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="{{ __('communities.search') }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-sm text-white placeholder:text-slate-500">
            <label class="sr-only" for="community-status">{{ __('communities.status') }}</label>
            <select id="community-status" name="status" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-sm text-white">
                <option value="">{{ __('communities.all_statuses') }}</option>
                <option value="active" @selected($filters['status'] === 'active')>{{ __('communities.active') }}</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('communities.inactive') }}</option>
            </select>
            <button type="submit" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-5 py-3 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/20">{{ __('communities.filter') }}</button>
            <a href="{{ route('communities.index') }}" class="rounded-lg border border-slate-700 px-5 py-3 text-center text-sm font-semibold text-slate-300 transition hover:bg-slate-800">{{ __('finance.reset') }}</a>
        </form>

        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-[#111a2e] shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="border-b border-slate-800 bg-slate-900/50 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('communities.code') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('communities.name') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('communities.leader') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('communities.members') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('communities.status') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('finance.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($communities as $community)
                            <tr class="text-slate-300">
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-blue-300">{{ $community->code }}</td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('communities.show', $community) }}" class="font-semibold text-white hover:text-blue-300">{{ $community->name }}</a>
                                    @if ($community->location)<span class="mt-1 block text-xs text-slate-500">{{ $community->location }}</span>@endif
                                </td>
                                <td class="px-5 py-4">
                                    @if ($community->leader)
                                        <span class="block text-white">{{ trim(collect([$community->leader->first_name, $community->leader->middle_name, $community->leader->last_name])->filter()->implode(' ')) }}</span>
                                        <span class="text-xs text-slate-500">{{ $community->leader->membership_number }}</span>
                                    @else
                                        <span class="text-slate-500">{{ __('communities.leader_not_selected') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">{{ number_format($community->members_count) }}</td>
                                <td class="px-5 py-4">
                                    <span @class([
                                        'rounded-full px-3 py-1 text-xs font-semibold',
                                        'bg-emerald-500/10 text-emerald-300' => $community->status === 'active',
                                        'bg-slate-700/60 text-slate-300' => $community->status !== 'active',
                                    ])>{{ __('communities.'.$community->status) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('communities.show', $community) }}" class="font-semibold text-blue-300 hover:text-blue-200">{{ __('groups.view') }}</a>
                                        @can('edit communities')
                                            <a href="{{ route('communities.edit', $community) }}" class="font-semibold text-slate-300 hover:text-white">{{ __('communities.edit') }}</a>
                                            <form method="POST" action="{{ route('communities.status', $community) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="font-semibold text-amber-300 hover:text-amber-200">
                                                    {{ $community->status === 'active' ? __('communities.inactive') : __('communities.active') }}
                                                </button>
                                            </form>
                                        @endcan
                                        @can('delete communities')
                                            <form method="POST" action="{{ route('communities.destroy', $community) }}" onsubmit="return window.confirm(@js(__('communities.confirm_delete')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-semibold text-rose-300 hover:text-rose-200">{{ __('communities.delete') }}</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">{{ __('communities.no_communities') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($communities->hasPages())
                <div class="border-t border-slate-800 px-5 py-4">{{ $communities->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
