<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold text-white">{{ __('finance.sms_campaigns_title') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('finance.sms_campaigns_description') }}</p>
            </div>
            @can('send bulk SMS')
                <a href="{{ route('finance.sms.campaigns.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">
                    {{ __('finance.bulk_sms_create') }}
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('finance.sms.campaigns.index') }}" class="grid gap-3 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:grid-cols-3">
            <label class="sr-only" for="campaign-search">{{ __('finance.search') }}</label>
            <input id="campaign-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="{{ __('finance.sms_campaign_name') }}" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
            <label class="sr-only" for="campaign-status">{{ __('finance.sms_campaign_status') }}</label>
            <select id="campaign-status" name="status" class="rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                <option value="">{{ __('finance.all_statuses') }}</option>
                @foreach (['draft', 'scheduled', 'queued', 'processing', 'completed', 'partially_failed', 'failed', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('finance.sms_campaign_statuses.'.$status) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">{{ __('finance.filter') }}</button>
        </form>

        @if ($campaigns->isEmpty())
            <div class="rounded-2xl border border-slate-800 bg-[#111a2e] p-8 text-center text-slate-400">{{ __('finance.campaigns_empty') }}</div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-[#111a2e]">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">{{ __('finance.sms_campaign_name') }}</th>
                            <th class="px-5 py-3">{{ __('finance.sms_campaign_creator') }}</th>
                            <th class="px-5 py-3">{{ __('finance.sms_campaign_count') }}</th>
                            <th class="px-5 py-3">{{ __('finance.sms_campaign_submitted') }}</th>
                            <th class="px-5 py-3">{{ __('finance.sms_campaign_failed') }}</th>
                            <th class="px-5 py-3">{{ __('finance.sms_campaign_status') }}</th>
                            <th class="px-5 py-3">{{ __('finance.sms_campaign_created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($campaigns as $campaign)
                            <tr>
                                <td class="px-5 py-4"><a href="{{ route('finance.sms.campaigns.show', $campaign) }}" class="font-semibold text-blue-300 hover:text-blue-200">{{ $campaign->name }}</a></td>
                                <td class="px-5 py-4 text-slate-300">{{ $campaign->creator?->name ?? __('finance.anonymous') }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ number_format($campaign->recipient_count) }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ number_format($campaign->submitted_count) }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ number_format($campaign->failed_count) }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ __('finance.sms_campaign_statuses.'.$campaign->status) }}</td>
                                <td class="px-5 py-4 text-slate-400">{{ $campaign->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $campaigns->links() }}</div>
        @endif
    </div>
</x-app-layout>
