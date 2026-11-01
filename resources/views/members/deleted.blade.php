<x-app-layout>
    <div
        x-data="{ pageMemberIds: @js($memberIds), selectedMemberIds: [] }"
        class="bg-[#0b1120] px-4 py-8 text-gray-100 sm:px-8"
    >
        <div class="mx-auto max-w-7xl space-y-7">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">
                        {{ __('members.eyebrow') }}
                    </p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">
                        {{ __('members.deleted_members') }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-400">
                        {{ __('members.deleted_description') }}
                    </p>
                </div>

                <a
                    href="{{ route('members.index') }}"
                    class="rounded-xl border border-gray-700 px-5 py-3 text-sm font-semibold text-gray-200 transition hover:bg-gray-800"
                >
                    {{ __('members.back_to_list') }}
                </a>
            </div>

            @if (session('success'))
                <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-800 bg-red-950/60 p-5 text-red-300">
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @can('restore members')
                <form
                    id="bulk-restore-members-form"
                    method="POST"
                    action="{{ route('members.deleted.bulk-restore') }}"
                    data-confirm="{{ __('members.confirm_bulk_restore') }}"
                    @submit.prevent="if (selectedMemberIds.length > 0 && window.confirm($el.dataset.confirm.replace(':count', selectedMemberIds.length))) $el.submit()"
                >
                    @csrf
                </form>

                <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-800 bg-[#111a2e] px-5 py-4">
                    <span
                        class="rounded-full border border-blue-500/30 bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-300"
                        data-label="{{ __('members.selected_count') }}"
                        x-text="$el.dataset.label.replace(':count', selectedMemberIds.length)"
                        aria-live="polite"
                    ></span>
                    <button
                        type="submit"
                        form="bulk-restore-members-form"
                        :disabled="selectedMemberIds.length === 0"
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5m4-1v5l3 2"/>
                        </svg>
                        {{ __('members.restore_selected') }}
                    </button>
                </div>
            @endcan

            <section class="overflow-hidden rounded-2xl border border-gray-800 bg-[#111a2e] shadow-xl">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-800 px-5 py-4 sm:px-6">
                    <p class="text-xs text-gray-500">
                        {{ __('members.showing', [
                            'first' => $members->firstItem() ?? 0,
                            'last' => $members->lastItem() ?? 0,
                            'total' => $members->total(),
                        ]) }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-[#0d1628] text-xs uppercase tracking-wider text-gray-400">
                            <tr>
                                @can('restore members')
                                    <th scope="col" class="w-12 px-4 py-4">
                                        <input
                                            type="checkbox"
                                            aria-label="{{ __('members.select_all_members') }}"
                                            :checked="pageMemberIds.length > 0 && selectedMemberIds.length === pageMemberIds.length"
                                            @change="selectedMemberIds = $event.target.checked ? [...pageMemberIds] : []"
                                            class="rounded border-gray-600 bg-[#0b1120] text-blue-500 focus:ring-blue-500"
                                        >
                                    </th>
                                @endcan
                                <th scope="col" class="px-5 py-4">{{ __('members.membership_number') }}</th>
                                <th scope="col" class="px-5 py-4">{{ __('members.full_name') }}</th>
                                <th scope="col" class="px-5 py-4">{{ __('members.envelope_number') }}</th>
                                <th scope="col" class="px-5 py-4">{{ __('members.deleted_at') }}</th>
                                @can('restore members')
                                    <th scope="col" class="px-5 py-4">{{ __('members.actions') }}</th>
                                @endcan
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-800">
                            @forelse ($members as $member)
                                <tr class="transition hover:bg-white/[0.025]">
                                    @can('restore members')
                                        <td class="px-4 py-4">
                                            <input
                                                type="checkbox"
                                                name="member_ids[]"
                                                value="{{ $member->id }}"
                                                form="bulk-restore-members-form"
                                                x-model="selectedMemberIds"
                                                aria-label="{{ __('members.select_member') }}: {{ $member->membership_number }}"
                                                class="rounded border-gray-600 bg-[#0b1120] text-blue-500 focus:ring-blue-500"
                                            >
                                        </td>
                                    @endcan
                                    <td class="whitespace-nowrap px-5 py-4 font-semibold text-blue-400">
                                        {{ $member->membership_number }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 font-medium text-white">
                                        {{ collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' ') }}
                                    </td>
                                    <td class="px-5 py-4 text-gray-300">{{ $member->envelope_number }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-400">
                                        {{ $member->deleted_at?->format('d M Y H:i') }}
                                    </td>
                                    @can('restore members')
                                        <td class="px-5 py-4">
                                            <form
                                                method="POST"
                                                action="{{ route('members.restore', $member) }}"
                                                data-confirm="{{ __('members.confirm_single_restore', [
                                                    'name' => collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' '),
                                                    'membership_number' => $member->membership_number,
                                                ]) }}"
                                                onsubmit="return confirm(this.dataset.confirm)"
                                            >
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-500/30 px-2.5 py-1.5 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/10 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                        <path d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5m4-1v5l3 2"/>
                                                    </svg>
                                                    {{ __('members.restore') }}
                                                </button>
                                            </form>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        class="px-6 py-16 text-center"
                                        colspan="@can('restore members') 6 @else 4 @endcan"
                                    >
                                        <p class="text-base font-semibold text-gray-300">{{ __('members.no_deleted_members') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-800 px-5 py-5 sm:px-6">
                    {{ $members->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
