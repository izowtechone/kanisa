<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold text-white">{{ __('finance.campaigns_title') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('finance.campaigns_description') }}</p>
            </div>
            <a href="{{ route('finance.campaigns.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.campaign_create') }}</a>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="GET" action="{{ route('finance.campaigns.index') }}" class="grid gap-3 rounded-2xl border border-slate-800 bg-[#111a2e] p-4 sm:grid-cols-3">
            <label class="space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.search') }}</span>
                <input name="search" value="{{ $filters['search'] ?? '' }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
            </label>
            <label class="space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.status') }}</span>
                <select name="status" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100">
                    <option value="">{{ __('finance.all_statuses') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('finance.active') }}</option>
                    <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>{{ __('finance.closed') }}</option>
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">{{ __('finance.filter') }}</button>
                <a href="{{ route('finance.campaigns.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300">{{ __('finance.reset') }}</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-[#111a2e]">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">{{ __('finance.campaign_code') }}</th>
                        <th class="px-5 py-3">{{ __('finance.campaign_name') }}</th>
                        <th class="px-5 py-3">{{ __('finance.campaign_period') }}</th>
                        <th class="px-5 py-3">{{ __('finance.total_collected') }}</th>
                        <th class="px-5 py-3">{{ __('finance.status') }}</th>
                        <th class="px-5 py-3">{{ __('finance.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-slate-300">{{ $campaign->code }}</td>
                            <td class="px-5 py-4 font-medium text-slate-100">{{ $campaign->name }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-300">{{ $campaign->starts_on?->format('Y-m-d') ?? '—' }} – {{ $campaign->ends_on?->format('Y-m-d') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-200">{{ app(\App\Services\FinancialAmountFormatter::class)->format((string) ($totals[$campaign->id] ?? '0.00'), $currency) }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ __('finance.'.$campaign->status) }}</td>
                            <td class="whitespace-nowrap px-5 py-4"><a href="{{ route('finance.campaigns.show', $campaign) }}" class="font-semibold text-blue-300">{{ __('finance.view_campaign') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('finance.campaigns_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $campaigns->links() }}</div>
    </div>
</x-app-layout>
