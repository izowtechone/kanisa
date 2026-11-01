<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('dashboard.administration') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ __('departments.title') }}</h2>
                <p class="mt-2 text-sm text-gray-400">{{ __('departments.list_description') }}</p>
            </div>
            @can('create departments')
                <a href="{{ route('departments.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">
                    {{ __('departments.add_department') }}
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('departments.index') }}" class="grid gap-3 rounded-2xl border border-gray-800 bg-[#111a2e] p-5 sm:grid-cols-[minmax(0,1fr)_12rem_auto_auto]">
            <div>
                <label for="department-search" class="sr-only">{{ __('departments.search') }}</label>
                <input id="department-search" name="search" value="{{ $filters['search'] }}" placeholder="{{ __('departments.search_placeholder') }}" class="w-full rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="department-status" class="sr-only">{{ __('departments.status') }}</label>
                <select id="department-status" name="status" class="w-full rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">{{ __('departments.all_statuses') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('departments.active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('departments.inactive') }}</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-5 py-3 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/20">{{ __('departments.filter') }}</button>
            <a href="{{ route('departments.index') }}" class="rounded-lg border border-gray-700 px-5 py-3 text-center text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('departments.reset') }}</a>
        </form>

        <section class="overflow-hidden rounded-2xl border border-gray-800 bg-[#111a2e] shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="border-b border-gray-800 bg-slate-900/50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('departments.code') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('departments.name') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('departments.leader') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('departments.groups') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('departments.status') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('departments.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse ($departments as $department)
                            <tr class="text-gray-300">
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-blue-300">{{ $department->code }}</td>
                                <td class="px-5 py-4 font-semibold text-white">{{ $department->name }}</td>
                                <td class="px-5 py-4">
                                    @if ($department->leader)
                                        <span class="block text-white">{{ trim($department->leader->first_name.' '.($department->leader->middle_name ? $department->leader->middle_name.' ' : '').$department->leader->last_name) }}</span>
                                        <span class="text-xs text-gray-500">{{ $department->leader->membership_number }}</span>
                                    @else
                                        <span class="text-gray-500">{{ __('departments.no_leader') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">{{ number_format($department->groups_count) }}</td>
                                <td class="px-5 py-4">
                                    <span @class([
                                        'rounded-full px-3 py-1 text-xs font-semibold',
                                        'bg-emerald-500/10 text-emerald-300' => $department->is_active,
                                        'bg-slate-700/60 text-slate-300' => ! $department->is_active,
                                    ])>{{ $department->is_active ? __('departments.active') : __('departments.inactive') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('departments.show', $department) }}" class="font-semibold text-blue-300 hover:text-blue-200">{{ __('departments.view') }}</a>
                                        @can('edit departments')
                                            <a href="{{ route('departments.edit', $department) }}" class="font-semibold text-gray-300 hover:text-white">{{ __('departments.edit') }}</a>
                                        @endcan
                                        @can('delete departments')
                                            @if ($department->groups_count === 0)
                                                <form method="POST" action="{{ route('departments.destroy', $department) }}" onsubmit="return confirm('{{ __('departments.confirm_delete') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="font-semibold text-red-300 hover:text-red-200">{{ __('departments.delete') }}</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-400">{{ __('departments.no_departments') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($departments->hasPages())
                <div class="border-t border-gray-800 px-5 py-4">{{ $departments->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
