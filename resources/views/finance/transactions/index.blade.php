<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><h2 class="text-3xl font-bold text-white">{{ __('finance.transactions_title') }}</h2><p class="mt-2 text-sm text-slate-400">{{ __('finance.description') }}</p></div>
            <div class="flex flex-wrap gap-2">
                @canany(['record envelope offerings', 'manage offerings'])<a href="{{ route('finance.envelopes.create') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800">{{ __('finance.record_by_envelope') }}</a>@endcanany
                @can('import envelope offerings')<a href="{{ route('finance.imports.create') }}" class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-2.5 text-sm font-semibold text-blue-300 hover:bg-blue-500/20">{{ __('finance.import_envelope_offerings') }}</a>@endcan
            </div>
        </div>
        @if (session('success'))<div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>@endif
        <form method="GET" action="{{ route('finance.transactions.index') }}" class="grid gap-3 rounded-2xl border border-slate-800 bg-[#111a2e] p-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.search') }}</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('finance.search_placeholder') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100 placeholder:text-slate-500"></label>
            <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.type') }}</span><select name="type" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"><option value="">{{ __('finance.all_types') }}</option>@foreach ($types as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ __('finance.types.'.$type) }}</option>@endforeach</select></label>
            <label class="space-y-2 text-sm text-slate-300"><span>{{ __('finance.status') }}</span><select name="status" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-slate-100"><option value="">{{ __('finance.all_statuses') }}</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('finance.statuses.'.$status) }}</option>@endforeach</select></label>
            <div class="flex items-end gap-2"><button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.filter') }}</button><a href="{{ route('finance.transactions.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-800">{{ __('finance.reset') }}</a></div>
        </form>
        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-[#111a2e]">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">{{ __('finance.transaction_number') }}</th><th class="px-4 py-3">{{ __('finance.type') }}</th><th class="px-4 py-3">{{ __('finance.member') }}</th><th class="px-4 py-3">{{ __('finance.category') }}</th><th class="px-4 py-3">{{ __('finance.campaign') }}</th><th class="px-4 py-3">{{ __('finance.transaction_date') }}</th><th class="px-4 py-3">{{ __('finance.amount') }}</th><th class="px-4 py-3">{{ __('finance.status') }}</th><th class="px-4 py-3">{{ __('finance.actions') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-4"><a href="{{ route('finance.transactions.show', $transaction) }}" class="font-semibold text-blue-300 hover:text-blue-200">{{ $transaction->transaction_number }}</a><span class="mt-1 block text-xs text-slate-500">{{ $transaction->receipt_number }}</span></td>
                            <td class="px-4 py-4 text-slate-300">{{ __('finance.types.'.$transaction->type) }}</td>
                            <td class="px-4 py-4 text-slate-300">{{ $transaction->is_anonymous ? __('finance.anonymous') : ($transaction->member_number ?? __('finance.unassigned_member')) }}</td>
                            <td class="px-4 py-4 text-slate-300">{{ $transaction->category?->localizedName() ?? '—' }}</td>
                            <td class="px-4 py-4 text-slate-300">{{ $transaction->campaign?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-300">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                            <td class="whitespace-nowrap px-4 py-4 font-medium text-slate-200">{{ app(\App\Services\FinancialAmountFormatter::class)->format($transaction->amount, $transaction->currency) }}</td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300">{{ __('finance.statuses.'.$transaction->status) }}</span></td>
                            <td class="whitespace-nowrap px-4 py-4"><a href="{{ route('finance.transactions.show', $transaction) }}" class="font-semibold text-blue-300 hover:text-blue-200">{{ __('finance.transaction_details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-slate-400">{{ __('finance.no_transactions') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $transactions->links() }}</div>
    </div>
</x-app-layout>
