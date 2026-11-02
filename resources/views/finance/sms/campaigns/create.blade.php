<x-app-layout>
    <div
        x-data="smsCampaignSelector(
            @js($validPhoneIds),
            {{ $eligibleCount }},
            @js(__('finance.sms_campaign_confirm_send'))
        )"
        class="mx-auto max-w-7xl space-y-6"
    >
        <div>
            <h2 class="text-3xl font-bold text-white">{{ __('finance.sms_campaign_create_title') }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ __('finance.sms_campaign_create_description') }}</p>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $smsEnabled || $testMode)
            <div role="alert" class="rounded-xl border border-amber-700/60 bg-amber-950/40 p-4 text-sm text-amber-200">
                {{ __('finance.bulk_sms_requires_live_configuration') }}
                @can('manage SMS settings')
                    <a href="{{ route('settings.edit') }}#sms-notifications" class="ml-1 font-semibold underline">{{ __('members.open_settings') }}</a>
                @endcan
            </div>
        @endif

        <div class="rounded-xl border border-blue-900/70 bg-blue-950/30 p-4 text-sm text-blue-200">
            {{ __('finance.sms_campaign_consent_notice') }}
        </div>

        <form method="GET" action="{{ route('finance.sms.campaigns.create') }}" class="space-y-4 rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
            <h3 class="font-semibold text-white">{{ __('finance.sms_campaign_filters') }}</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="sr-only" for="member-search">{{ __('finance.search') }}</label>
                <input id="member-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="{{ __('finance.sms_campaign_search') }}" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                <label class="sr-only" for="department-filter">{{ __('finance.sms_campaign_department') }}</label>
                <select id="department-filter" name="department_id" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                    <option value="">{{ __('finance.sms_campaign_all_departments') }}</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="group-filter">{{ __('finance.sms_campaign_group') }}</label>
                <select id="group-filter" name="group_id" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                    <option value="">{{ __('finance.sms_campaign_all_groups') }}</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected((string) ($filters['group_id'] ?? '') === (string) $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="member-status">{{ __('finance.status') }}</label>
                <select id="member-status" name="status" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                    <option value="">{{ __('finance.sms_campaign_all_statuses') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('finance.sms_campaign_active') }}</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('finance.sms_campaign_inactive') }}</option>
                </select>
                <label class="sr-only" for="member-gender">{{ __('members.gender') }}</label>
                <select id="member-gender" name="gender" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                    <option value="">{{ __('finance.sms_campaign_all_genders') }}</option>
                    <option value="Male" @selected(($filters['gender'] ?? '') === 'Male')>{{ __('members.male') }}</option>
                    <option value="Female" @selected(($filters['gender'] ?? '') === 'Female')>{{ __('members.female') }}</option>
                </select>
                <label class="block space-y-1 text-xs text-slate-400">
                    <span>{{ __('finance.sms_campaign_registration_from') }}</span>
                    <input type="date" name="registered_from" value="{{ $filters['registered_from'] ?? '' }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                </label>
                <label class="block space-y-1 text-xs text-slate-400">
                    <span>{{ __('finance.sms_campaign_registration_to') }}</span>
                    <input type="date" name="registered_to" value="{{ $filters['registered_to'] ?? '' }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                </label>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">{{ __('finance.sms_campaign_filter') }}</button>
            </div>
        </form>

        <form
            method="POST"
            action="{{ route('finance.sms.campaigns.store') }}"
            @submit="confirmSubmit($event)"
            class="space-y-6"
        >
            @csrf
            @foreach ($filters as $key => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <template x-for="memberId in selectedIds" :key="memberId">
                <input type="hidden" name="member_ids[]" :value="memberId">
            </template>

            <section class="space-y-4 rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold text-white">{{ __('finance.sms_campaign_recipients') }}</h3>
                    <span
                        class="rounded-full border border-blue-500/30 bg-blue-500/10 px-3 py-1 text-sm text-blue-200"
                        aria-live="polite"
                        x-text="@js(__('finance.sms_campaign_selected', ['count' => ':count'])).replace(':count', recipientCount)"
                    ></span>
                </div>
                <p class="text-sm text-slate-400">{{ __('finance.sms_campaign_eligible', ['count' => number_format($eligibleCount)]) }}</p>
                <div class="flex flex-wrap gap-3">
                    <button type="button" @click="selectPage(@js($validPhoneIds))" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800">{{ __('finance.sms_campaign_select_page') }}</button>
                    <button type="button" @click="clearSelection()" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800">{{ __('finance.sms_campaign_clear_selection') }}</button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-800">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="w-12 px-4 py-3">
                                    <input type="checkbox" aria-label="{{ __('finance.sms_campaign_select_page') }}" @change="$event.target.checked ? selectPage(@js($validPhoneIds)) : @js($validPhoneIds).forEach(id => toggleMember(id, false))" class="rounded border-slate-600 bg-[#0b1120] text-blue-500">
                                </th>
                                <th class="px-4 py-3">{{ __('finance.sms_campaign_recipient') }}</th>
                                <th class="px-4 py-3">{{ __('members.membership_number') }}</th>
                                <th class="px-4 py-3">{{ __('members.envelope_number') }}</th>
                                <th class="px-4 py-3">{{ __('members.phone') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse ($members as $member)
                                @php($isValidPhone = in_array($member->id, $validPhoneIds, true))
                                <tr>
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            :checked="selectedIds.includes('{{ $member->id }}')"
                                            :disabled="{{ $isValidPhone ? 'false' : 'true' }}"
                                            @change="toggleMember('{{ $member->id }}', $event.target.checked)"
                                            aria-label="{{ __('finance.sms_campaign_recipient') }}: {{ $member->first_name }} {{ $member->last_name }}"
                                            class="rounded border-slate-600 bg-[#0b1120] text-blue-500 disabled:opacity-40"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-slate-200">{{ $member->first_name }} {{ $member->middle_name }} {{ $member->last_name }}</td>
                                    <td class="px-4 py-3 font-mono text-slate-300">{{ $member->membership_number }}</td>
                                    <td class="px-4 py-3 font-mono text-slate-300">{{ $member->envelope_number }}</td>
                                    <td class="px-4 py-3 text-slate-300">
                                        {{ $member->phone }}
                                        @unless ($isValidPhone)
                                            <span class="block text-xs text-amber-300">{{ __('finance.sms_campaign_invalid_phone') }}</span>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('finance.sms_campaign_no_members') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div>{{ $members->links() }}</div>
                <label class="flex items-start gap-3 rounded-lg border border-slate-800 bg-[#0b1120] p-4 text-sm text-slate-300">
                    <input type="radio" name="selection_mode" value="selected" x-model="mode" class="mt-1 border-slate-600 bg-[#0b1120] text-blue-500">
                    <span>{{ __('finance.sms_campaign_select_individual') }}</span>
                </label>
                <label class="flex items-start gap-3 rounded-lg border border-blue-900/60 bg-blue-950/20 p-4 text-sm text-slate-300">
                    <input type="radio" name="selection_mode" value="filters" x-model="mode" class="mt-1 border-slate-600 bg-[#0b1120] text-blue-500">
                    <span>{{ __('finance.sms_campaign_select_all_matching') }}</span>
                </label>
            </section>

            <section class="space-y-5 rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>{{ __('finance.sms_campaign_name') }}</span>
                        <input name="name" value="{{ old('name') }}" maxlength="150" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                        @error('name')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                    </label>
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>{{ __('finance.sms_campaign_language') }}</span>
                        <select name="language" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                            <option value="sw" @selected(old('language', app()->getLocale()) === 'sw')>{{ __('members.kiswahili') }}</option>
                            <option value="en" @selected(old('language', app()->getLocale()) === 'en')>{{ __('members.english') }}</option>
                        </select>
                    </label>
                </div>
                <label class="block space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.sms_campaign_message') }}</span>
                    <textarea name="message" x-model="message" rows="5" maxlength="3000" required class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">{{ old('message') }}</textarea>
                    <span class="block text-xs text-slate-500">{{ __('finance.sms_campaign_placeholders') }}</span>
                    @error('message')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <div class="grid gap-3 sm:grid-cols-3">
                    <p class="rounded-lg border border-slate-800 bg-[#0b1120] p-3 text-sm text-slate-300" x-text="@js(__('finance.sms_campaign_characters', ['count' => ':count'])).replace(':count', Array.from(message).length)"></p>
                    <p class="rounded-lg border border-slate-800 bg-[#0b1120] p-3 text-sm text-slate-300">{{ __('finance.sms_campaign_estimated_segments') }}: <span x-text="segmentsPerRecipient"></span></p>
                    <p class="rounded-lg border border-slate-800 bg-[#0b1120] p-3 text-sm text-slate-300">{{ __('finance.sms_campaign_estimated_usage') }}: <span x-text="segmentsPerRecipient * recipientCount"></span></p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-[#0b1120] p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('finance.sms_campaign_preview') }}</p>
                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-200" x-text="message.replaceAll('@{{member_name}}', 'Amina Mushi').replaceAll('@{{member_number}}', 'KKKT-KANISA-0001').replaceAll('@{{church_name}}', 'Church')"></p>
                </div>
                <label class="block space-y-2 text-sm text-slate-300">
                    <span>{{ __('finance.sms_campaign_schedule') }}</span>
                    <input type="datetime-local" name="scheduled_at" min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}" value="{{ old('scheduled_at') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100 sm:max-w-md">
                    @error('scheduled_at')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="flex items-start gap-3 text-sm text-slate-300">
                    <input type="checkbox" name="confirmation" value="1" required class="mt-1 rounded border-slate-600 bg-[#0b1120] text-blue-500">
                    <span>{{ __('finance.sms_campaign_confirmation') }}</span>
                </label>
                <div class="flex justify-end">
                    <button type="submit" :disabled="submitting || recipientCount < 1 || !message.trim() || {{ (! $smsEnabled || $testMode) ? 'true' : 'false' }}" class="rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!submitting">{{ __('finance.sms_campaign_send') }}</span>
                        <span x-cloak x-show="submitting">{{ __('members.saving') }}</span>
                    </button>
                </div>
            </section>
        </form>
    </div>
</x-app-layout>
