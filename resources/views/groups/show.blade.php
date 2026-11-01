<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('groups.title') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ $group->name }}</h2>
                <p class="mt-2 text-sm text-gray-400">{{ $group->code }} · {{ $group->department->name }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('groups.index') }}" class="rounded-lg border border-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('groups.back_to_list') }}</a>
                @can('view groups')
                    <a href="{{ route('groups.members.index', $group) }}" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-2.5 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/20">{{ __('groups.view_members') }}</a>
                @endcan
                @can('edit groups')
                    <a href="{{ route('groups.edit', $group) }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('groups.edit') }}</a>
                @endcan
                @can('delete groups')
                    @if ($group->members_count === 0)
                        <form method="POST" action="{{ route('groups.destroy', $group) }}" onsubmit="return confirm('{{ __('groups.confirm_delete') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm font-semibold text-red-300 transition hover:bg-red-500/20">{{ __('groups.delete') }}</button>
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
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('groups.code') }}</p>
                <p class="mt-2 font-mono font-semibold text-blue-300">{{ $group->code }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('groups.department') }}</p>
                @can('view departments')
                    <a href="{{ route('departments.show', $group->department) }}" class="mt-2 inline-block font-semibold text-white hover:text-blue-300">{{ $group->department->name }}</a>
                @else
                    <p class="mt-2 font-semibold text-white">{{ $group->department->name }}</p>
                @endcan
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('groups.leader') }}</p>
                @if ($group->leader)
                    <p class="mt-2 font-semibold text-white">{{ trim($group->leader->first_name.' '.($group->leader->middle_name ? $group->leader->middle_name.' ' : '').$group->leader->last_name) }}</p>
                    <p class="text-xs text-gray-500">{{ $group->leader->membership_number }}</p>
                @else
                    <p class="mt-2 text-gray-400">{{ __('groups.no_leader') }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('groups.status') }}</p>
                <p class="mt-2 font-semibold text-white">{{ $group->is_active ? __('groups.active') : __('groups.inactive') }}</p>
            </div>
            @if ($group->description)
                <div class="border-t border-gray-800 pt-4 sm:col-span-2 lg:col-span-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('groups.description') }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-300">{{ $group->description }}</p>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-gray-800 bg-[#111a2e] p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-semibold text-white">{{ __('groups.members') }}</h3>
                <span class="rounded-full border border-blue-500/30 bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-300">{{ number_format($group->members_count) }}</span>
            </div>
            @can('view groups')
                <a href="{{ route('groups.members.index', $group) }}" class="mt-4 inline-flex rounded-lg border border-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('groups.view_members') }}</a>
            @endcan
        </section>
    </div>
</x-app-layout>
