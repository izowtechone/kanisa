<x-app-layout>
    @php
        $oldMemberIds = old('member_ids', []);
        $oldMemberIds = is_array($oldMemberIds) ? array_map('strval', $oldMemberIds) : [];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ $group->department->name }} · {{ $group->code }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ $group->name }}</h2>
                <p class="mt-2 text-sm text-gray-400">{{ __('groups.members_title') }}</p>
            </div>
            <a href="{{ route('groups.show', $group) }}" class="rounded-lg border border-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-300 transition hover:bg-gray-800">{{ __('groups.back_to_list') }}</a>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-red-300">
                <p class="mb-2 font-semibold">{{ __('groups.validation_heading') }}</p>
                <ul class="list-inside list-disc space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @can('assign group members')
            <form method="POST" action="{{ route('groups.members.assign', $group) }}" class="space-y-4 rounded-2xl border border-gray-800 bg-[#111a2e] p-5">
                @csrf
                <div>
                    <label for="member-ids" class="mb-2 block text-sm font-medium text-gray-300">{{ __('groups.choose_members') }}</label>
                    @if ($availableMembers->isEmpty())
                        <p class="rounded-lg border border-gray-800 bg-[#0b1120] px-4 py-3 text-sm text-gray-400">{{ __('groups.no_available_members') }}</p>
                    @else
                        <select id="member-ids" name="member_ids[]" multiple size="6" aria-describedby="member-ids-help" class="w-full rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-sm text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach ($availableMembers as $member)
                                <option value="{{ $member->id }}" @selected(in_array((string) $member->id, $oldMemberIds, true))>
                                    {{ trim($member->first_name.' '.($member->middle_name ? $member->middle_name.' ' : '').$member->last_name) }} · {{ $member->membership_number }}
                                </option>
                            @endforeach
                        </select>
                        <p id="member-ids-help" class="mt-2 text-xs text-gray-500">{{ __('groups.select_multiple_hint') }}</p>
                    @endif
                </div>
                @error('member_ids')<p class="text-sm text-red-300">{{ $message }}</p>@enderror
                @error('member_ids.*')<p class="text-sm text-red-300">{{ $message }}</p>@enderror
                <button type="submit" @disabled($availableMembers->isEmpty()) class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50">{{ __('groups.assign_members') }}</button>
            </form>
        @endcan

        <section class="overflow-hidden rounded-2xl border border-gray-800 bg-[#111a2e] shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-gray-800 bg-slate-900/50 text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('groups.member_number') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('groups.name') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('groups.phone') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('groups.membership_status') }}</th>
                            @can('assign group members')
                                <th scope="col" class="px-5 py-4">{{ __('groups.actions') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse ($members as $member)
                            <tr class="text-gray-300">
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-blue-300">{{ $member->membership_number }}</td>
                                <td class="px-5 py-4 font-semibold text-white">{{ trim($member->first_name.' '.($member->middle_name ? $member->middle_name.' ' : '').$member->last_name) }}</td>
                                <td class="px-5 py-4">{{ $member->phone ?: '—' }}</td>
                                <td class="px-5 py-4">{{ $member->status === 'active' ? __('groups.active') : __('groups.inactive') }}</td>
                                @can('assign group members')
                                    <td class="px-5 py-4">
                                        <form method="POST" action="{{ route('groups.members.remove', [$group, $member]) }}" onsubmit="return confirm('{{ __('groups.confirm_remove_member') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-semibold text-red-300 hover:text-red-200">{{ __('groups.remove_member') }}</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="@can('assign group members') 5 @else 4 @endcan" class="px-5 py-12 text-center text-gray-400">{{ __('groups.no_members') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($members->hasPages())
                <div class="border-t border-gray-800 px-5 py-4">{{ $members->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
