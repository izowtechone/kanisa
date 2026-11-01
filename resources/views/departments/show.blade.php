<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('departments.title') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ $department->name }}</h2>
                <p class="mt-2 text-sm text-gray-400">{{ $department->code }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('departments.index') }}" class="rounded-lg border border-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('departments.back_to_list') }}</a>
                @can('edit departments')
                    <a href="{{ route('departments.edit', $department) }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('departments.edit') }}</a>
                @endcan
                @can('create groups')
                    <a href="{{ route('groups.create', ['department_id' => $department->id]) }}" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-2.5 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/20">{{ __('departments.add_group') }}</a>
                @endcan
                @can('delete departments')
                    @if ($groups->total() === 0)
                        <form method="POST" action="{{ route('departments.destroy', $department) }}" onsubmit="return confirm('{{ __('departments.confirm_delete') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm font-semibold text-red-300 transition hover:bg-red-500/20">{{ __('departments.delete') }}</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">{{ session('error') }}</div>
        @endif

        <section class="grid gap-4 rounded-2xl border border-gray-800 bg-[#111a2e] p-6 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('departments.code') }}</p>
                <p class="mt-2 font-mono font-semibold text-blue-300">{{ $department->code }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('departments.leader') }}</p>
                @if ($department->leader)
                    <p class="mt-2 font-semibold text-white">{{ trim($department->leader->first_name.' '.($department->leader->middle_name ? $department->leader->middle_name.' ' : '').$department->leader->last_name) }}</p>
                    <p class="text-xs text-gray-500">{{ $department->leader->membership_number }}</p>
                @else
                    <p class="mt-2 text-gray-400">{{ __('departments.no_leader') }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('departments.status') }}</p>
                <p class="mt-2 font-semibold text-white">{{ $department->is_active ? __('departments.active') : __('departments.inactive') }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('departments.groups') }}</p>
                <p class="mt-2 font-semibold text-white">{{ number_format($groups->total()) }}</p>
            </div>
            @if ($department->description)
                <div class="border-t border-gray-800 pt-4 sm:col-span-2 lg:col-span-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('departments.description') }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-300">{{ $department->description }}</p>
                </div>
            @endif
        </section>

        @can('view groups')
            <section class="overflow-hidden rounded-2xl border border-gray-800 bg-[#111a2e] shadow-xl">
                <div class="flex items-center justify-between gap-4 border-b border-gray-800 px-5 py-4">
                    <h3 class="font-semibold text-white">{{ __('departments.groups') }}</h3>
                    <a href="{{ route('groups.index', ['department_id' => $department->id]) }}" class="text-sm font-semibold text-blue-300 hover:text-blue-200">{{ __('departments.view_groups') }}</a>
                </div>
                @if ($groups->isEmpty())
                    <p class="px-5 py-10 text-center text-sm text-gray-400">{{ __('departments.no_groups') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[650px] text-left text-sm">
                            <thead class="border-b border-gray-800 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th scope="col" class="px-5 py-3">{{ __('groups.code') }}</th>
                                    <th scope="col" class="px-5 py-3">{{ __('groups.name') }}</th>
                                    <th scope="col" class="px-5 py-3">{{ __('groups.leader') }}</th>
                                    <th scope="col" class="px-5 py-3">{{ __('groups.members') }}</th>
                                    <th scope="col" class="px-5 py-3">{{ __('groups.status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @foreach ($groups as $group)
                                    <tr>
                                        <td class="px-5 py-4 font-mono text-blue-300">{{ $group->code }}</td>
                                        <td class="px-5 py-4"><a href="{{ route('groups.show', $group) }}" class="font-semibold text-white hover:text-blue-300">{{ $group->name }}</a></td>
                                        <td class="px-5 py-4 text-gray-300">{{ $group->leader?->membership_number ?? __('groups.no_leader') }}</td>
                                        <td class="px-5 py-4 text-gray-300">{{ number_format($group->members_count) }}</td>
                                        <td class="px-5 py-4 text-gray-300">{{ $group->is_active ? __('groups.active') : __('groups.inactive') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($groups->hasPages())
                        <div class="border-t border-gray-800 px-5 py-4">{{ $groups->links() }}</div>
                    @endif
                @endif
            </section>
        @endcan
    </div>
</x-app-layout>
