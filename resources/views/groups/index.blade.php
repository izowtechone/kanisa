<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('dashboard.administration') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ __('groups.title') }}</h2>
                <p class="mt-2 text-sm text-gray-400">{{ __('groups.list_description') }}</p>
            </div>
            @can('create groups')
                <a href="{{ route('groups.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">
                    {{ __('groups.add_group') }}
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('groups.index') }}" class="grid gap-3 rounded-2xl border border-gray-800 bg-[#111a2e] p-5 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_14rem_12rem_auto_auto]">
            <div>
                <label for="group-search" class="sr-only">{{ __('groups.search') }}</label>
                <input id="group-search" name="search" value="{{ $filters['search'] }}" placeholder="{{ __('groups.search_placeholder') }}" class="w-full rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="group-department" class="sr-only">{{ __('groups.department') }}</label>
                <select id="group-department" name="department_id" class="w-full rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">{{ __('groups.all_departments') }}</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="group-status" class="sr-only">{{ __('groups.status') }}</label>
                <select id="group-status" name="status" class="w-full rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">{{ __('groups.all_statuses') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('groups.active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('groups.inactive') }}</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-5 py-3 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/20">{{ __('groups.filter') }}</button>
            <a href="{{ route('groups.index') }}" class="rounded-lg border border-gray-700 px-5 py-3 text-center text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('groups.reset') }}</a>
        </form>

        <section class="overflow-hidden rounded-2xl border border-gray-800 bg-[#111a2e] shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-gray-800 bg-slate-900/50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('groups.code') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('groups.name') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('groups.department') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('groups.leader') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('groups.members') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('groups.status') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('groups.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse ($groups as $group)
                            <tr class="text-gray-300">
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-blue-300">{{ $group->code }}</td>
                                <td class="px-5 py-4 font-semibold text-white">{{ $group->name }}</td>
                                <td class="px-5 py-4">{{ $group->department->name }}</td>
                                <td class="px-5 py-4">
                                    @if ($group->leader)
                                        <span class="block text-white">{{ trim($group->leader->first_name.' '.($group->leader->middle_name ? $group->leader->middle_name.' ' : '').$group->leader->last_name) }}</span>
                                        <span class="text-xs text-gray-500">{{ $group->leader->membership_number }}</span>
                                    @else
                                        <span class="text-gray-500">{{ __('groups.no_leader') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">{{ number_format($group->members_count) }}</td>
                                <td class="px-5 py-4">
                                    <span @class([
                                        'rounded-full px-3 py-1 text-xs font-semibold',
                                        'bg-emerald-500/10 text-emerald-300' => $group->is_active,
                                        'bg-slate-700/60 text-slate-300' => ! $group->is_active,
                                    ])>{{ $group->is_active ? __('groups.active') : __('groups.inactive') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('groups.show', $group) }}" class="font-semibold text-blue-300 hover:text-blue-200">{{ __('groups.view') }}</a>
                                        @can('edit groups')
                                            <a href="{{ route('groups.edit', $group) }}" class="font-semibold text-gray-300 hover:text-white">{{ __('groups.edit') }}</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-gray-400">{{ __('groups.no_groups') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($groups->hasPages())
                <div class="border-t border-gray-800 px-5 py-4">{{ $groups->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
