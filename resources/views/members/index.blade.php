<x-app-layout>
    <div class="bg-[#0b1120] px-4 py-8 text-gray-100 sm:px-8">
        <div
            x-data="{ pageMemberIds: @js($memberIds), selectedMemberIds: [] }"
            class="mx-auto max-w-7xl space-y-7"
        >
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">
                        {{ __('members.eyebrow') }}
                    </p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">
                        {{ __('members.title') }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-400">
                        {{ __('members.description') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    @can('export members')
                        <a
                            href="{{ route('members.export', array_filter($filters, static fn ($value): bool => $value !== null && $value !== '')) }}"
                            class="rounded-xl border border-gray-700 px-5 py-3 text-sm font-semibold text-gray-200 transition hover:bg-gray-800"
                        >
                            {{ __('members.export_members') }}
                        </a>
                    @endcan

                    @can('import members')
                        <a
                            href="{{ route('members.import.create') }}"
                            class="rounded-xl border border-blue-500/40 bg-blue-500/10 px-5 py-3 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/20"
                        >
                            {{ __('members.bulk_upload') }}
                        </a>
                    @endcan

                    @canany(['delete members', 'restore members'])
                        <a
                            href="{{ route('members.deleted.index') }}"
                            class="rounded-xl border border-gray-700 px-5 py-3 text-sm font-semibold text-gray-200 transition hover:bg-gray-800"
                        >
                            {{ __('members.view_deleted_members') }}
                        </a>
                    @endcanany

                    @can('create members')
                        <a
                            href="{{ route('members.create') }}"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-950/40 transition hover:bg-blue-500"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ __('members.add_member') }}
                        </a>
                    @endcan
                </div>
            </div>

            @can('delete members')
                <form
                    id="bulk-delete-members-form"
                    method="POST"
                    action="{{ route('members.bulk-destroy') }}"
                    data-confirm="{{ __('members.confirm_bulk_delete') }}"
                    @submit.prevent="if (selectedMemberIds.length > 0 && window.confirm($el.dataset.confirm.replace(':count', selectedMemberIds.length))) $el.submit()"
                >
                    @csrf
                </form>

                <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-800 bg-[#111a2e] px-5 py-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-sm text-gray-300">{{ __('members.bulk_delete_hint') }}</p>
                        <span
                            class="rounded-full border border-blue-500/30 bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-300"
                            data-label="{{ __('members.selected_count') }}"
                            x-text="$el.dataset.label.replace(':count', selectedMemberIds.length)"
                            aria-live="polite"
                        ></span>
                    </div>
                    <button
                        type="submit"
                        form="bulk-delete-members-form"
                        :disabled="selectedMemberIds.length === 0"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm font-semibold text-red-300 transition hover:bg-red-500/20 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/>
                        </svg>
                        {{ __('members.delete_selected') }}
                    </button>
                </div>
            @endcan

            @if (session('success'))
                <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-800 bg-red-950/60 p-5 text-red-300">
                    <p class="mb-2 font-semibold">{{ __('members.validation_errors_heading') }}</p>
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-3">
                @foreach ([
                    ['label' => 'members.total_members', 'value' => $totalMembers, 'color' => 'blue'],
                    ['label' => 'members.active_members', 'value' => $activeMembers, 'color' => 'emerald'],
                    ['label' => 'members.inactive_members', 'value' => $inactiveMembers, 'color' => 'slate'],
                ] as $stat)
                    <div class="rounded-2xl border border-gray-800 bg-[#111a2e] p-6 shadow-xl">
                        <p class="text-sm text-gray-400">{{ __($stat['label']) }}</p>
                        <p class="mt-3 text-4xl font-bold text-white">{{ number_format($stat['value']) }}</p>
                    </div>
                @endforeach
            </div>

            <section class="overflow-hidden rounded-2xl border border-gray-800 bg-[#111a2e] shadow-xl">
                <div class="border-b border-gray-800 px-5 py-5 sm:px-6">
                    <div class="mb-5">
                        <h2 class="font-semibold text-white">{{ __('members.registered_members') }}</h2>
                        <p class="mt-1 text-xs text-gray-500">{{ __('members.description') }}</p>
                    </div>

                    <form method="GET" action="{{ route('members.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <label class="sr-only" for="member-search">{{ __('members.search') }}</label>
                        <input
                            id="member-search"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="{{ __('members.search_placeholder') }}"
                            maxlength="100"
                            class="rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >

                        <label class="sr-only" for="member-gender">{{ __('members.gender') }}</label>
                        <select
                            id="member-gender"
                            name="gender"
                            class="rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">{{ __('members.all_genders') }}</option>
                            <option value="Male" @selected($filters['gender'] === 'Male')>{{ __('members.male') }}</option>
                            <option value="Female" @selected($filters['gender'] === 'Female')>{{ __('members.female') }}</option>
                        </select>

                        <label class="sr-only" for="member-status">{{ __('members.status') }}</label>
                        <select
                            id="member-status"
                            name="status"
                            class="rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">{{ __('members.all_statuses') }}</option>
                            <option value="active" @selected($filters['status'] === 'active')>{{ __('members.active') }}</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('members.inactive') }}</option>
                        </select>

                        <label class="sr-only" for="member-community">{{ __('members.community') }}</label>
                        <select
                            id="member-community"
                            name="community_id"
                            class="rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">{{ __('members.filter_community') }}</option>
                            @foreach ($communities as $community)
                                <option value="{{ $community->id }}" @selected((string) $filters['community_id'] === (string) $community->id)>
                                    {{ $community->code }} · {{ $community->name }}
                                </option>
                            @endforeach
                        </select>

                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                        >
                            {{ __('members.search') }}
                        </button>
                    </form>
                </div>

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
                    <table class="w-full min-w-[1050px] text-left text-sm">
                        <thead class="bg-[#0d1628] text-xs uppercase tracking-wider text-gray-400">
                            <tr>
                                @can('delete members')
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
                                <th scope="col" class="px-5 py-4">{{ __('members.community') }}</th>
                                <th scope="col" class="px-5 py-4">{{ __('members.envelope_number') }}</th>
                                <th scope="col" class="px-5 py-4">{{ __('members.gender') }}</th>
                                <th scope="col" class="px-5 py-4">{{ __('members.phone') }}</th>
                                <th scope="col" class="px-5 py-4">{{ __('members.membership_date') }}</th>
                                <th scope="col" class="px-5 py-4">{{ __('members.status') }}</th>
                                <th scope="col" class="px-5 py-4">{{ __('members.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-800">
                            @forelse ($members as $member)
                                <tr class="transition hover:bg-white/[0.025]">
                                    @can('delete members')
                                        <td class="px-4 py-4">
                                            <input
                                                type="checkbox"
                                                name="member_ids[]"
                                                value="{{ $member->id }}"
                                                form="bulk-delete-members-form"
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
                                    <td class="px-5 py-4 text-gray-300">{{ $member->community?->name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-300">{{ $member->envelope_number }}</td>
                                    <td class="px-5 py-4 text-gray-300">
                                        {{ __('members.'.strtolower($member->gender)) }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-400">{{ $member->phone ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-400">
                                        {{ $member->membership_date?->format('d M Y') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($member->status === 'active')
                                            <span class="rounded-full border border-emerald-800 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-400">
                                                {{ __('members.active') }}
                                            </span>
                                        @else
                                            <span class="rounded-full border border-gray-700 bg-gray-500/10 px-3 py-1 text-xs font-semibold text-gray-400">
                                                {{ __('members.inactive') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @canany(['edit members', 'delete members'])
                                            <div class="flex items-center gap-3">
                                                @can('edit members')
                                                    <a
                                                        href="{{ route('members.edit', $member) }}"
                                                        class="font-semibold text-blue-300 underline underline-offset-4 hover:text-blue-200"
                                                    >
                                                        {{ __('members.edit') }}
                                                    </a>
                                                @endcan

                                                @can('delete members')
                                                    <form
                                                        method="POST"
                                                        action="{{ route('members.destroy', $member) }}"
                                                        data-confirm="{{ __('members.confirm_single_delete', [
                                                            'name' => collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' '),
                                                            'membership_number' => $member->membership_number,
                                                        ]) }}"
                                                        onsubmit="return confirm(this.dataset.confirm)"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-500/30 px-2.5 py-1.5 text-xs font-semibold text-red-300 transition hover:bg-red-500/10 focus:outline-none focus:ring-2 focus:ring-red-500"
                                                        >
                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                                <path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/>
                                                            </svg>
                                                            {{ __('members.delete') }}
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                        @else
                                            <span class="text-gray-600">—</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-6 py-16 text-center" colspan="{{ Auth::user()->can('delete members') ? 9 : 8 }}">
                                        <p class="text-base font-semibold text-gray-300">{{ __('members.no_members') }}</p>
                                        <p class="mt-2 text-sm text-gray-500">{{ __('members.empty_list') }}</p>
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
