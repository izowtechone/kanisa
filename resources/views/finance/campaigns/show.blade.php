<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ $campaign->code }}</p>
                <h2 class="mt-2 text-3xl font-bold text-white">{{ $campaign->name }}</h2>
                @if ($campaign->description)<p class="mt-2 max-w-3xl text-sm text-slate-400">{{ $campaign->description }}</p>@endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('finance.campaigns.edit', $campaign) }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200">{{ __('finance.edit_campaign') }}</a>
                <form method="POST" action="{{ route('finance.campaigns.toggle', $campaign) }}" onsubmit="return window.confirm(@js($campaign->status === 'active' ? __('finance.confirm_close_campaign') : __('finance.confirm_reopen_campaign')))">
                    @csrf @method('PATCH')
                    <button class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-2.5 text-sm font-semibold text-amber-300">{{ $campaign->status === 'active' ? __('finance.close_campaign') : __('finance.reopen_campaign') }}</button>
                </form>
            </div>
        </div>

        @if (session('success'))<div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>@endif

        <section class="grid gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5"><p class="text-sm text-slate-400">{{ __('finance.status') }}</p><p class="mt-2 text-xl font-semibold text-white">{{ __('finance.'.$campaign->status) }}</p></article>
            <article class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5"><p class="text-sm text-slate-400">{{ __('finance.campaign_period') }}</p><p class="mt-2 font-semibold text-white">{{ $campaign->starts_on?->format('Y-m-d') ?? '—' }} – {{ $campaign->ends_on?->format('Y-m-d') ?? '—' }}</p></article>
            <article class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5"><p class="text-sm text-slate-400">{{ __('finance.total_collected') }}</p><p class="mt-2 text-xl font-bold text-emerald-300">{{ app(\App\Services\FinancialAmountFormatter::class)->format($totalCollected, $currency) }}</p></article>
        </section>

        <section class="overflow-x-auto rounded-2xl border border-slate-800 bg-[#111a2e]">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">{{ __('finance.transaction_number') }}</th><th class="px-5 py-3">{{ __('finance.member') }}</th><th class="px-5 py-3">{{ __('finance.transaction_date') }}</th><th class="px-5 py-3">{{ __('finance.amount') }}</th><th class="px-5 py-3">{{ __('finance.status') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td class="px-5 py-4"><a href="{{ route('finance.transactions.show', $transaction) }}" class="font-semibold text-blue-300">{{ $transaction->transaction_number }}</a></td>
                            <td class="px-5 py-4 text-slate-300">{{ $transaction->is_anonymous ? __('finance.anonymous') : ($transaction->member_number ?? __('finance.unassigned_member')) }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                            <td class="px-5 py-4 text-slate-200">{{ app(\App\Services\FinancialAmountFormatter::class)->format($transaction->amount, $transaction->currency) }} @if ($transaction->ledger_sign < 0)<span class="text-amber-300">({{ __('finance.reversal') }})</span>@endif</td>
                            <td class="px-5 py-4 text-slate-300">{{ __('finance.statuses.'.$transaction->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('finance.campaign_transactions_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        <div>{{ $transactions->links() }}</div>
    </div>
</x-app-layout>
