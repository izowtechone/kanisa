<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-7">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('finance.navigation') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ __('finance.dashboard') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('finance.description') }}</p>
            </div>
            <a href="{{ route('finance.transactions.index') }}" class="rounded-xl border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">
                {{ __('finance.view_all_transactions') }}
            </a>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['label' => 'finance.month_income', 'amount' => $summary['income'], 'tone' => 'text-emerald-300'],
                ['label' => 'finance.month_expenses', 'amount' => $summary['expenses'], 'tone' => 'text-rose-300'],
                ['label' => 'finance.month_net', 'amount' => $summary['net'], 'tone' => 'text-blue-300'],
            ] as $stat)
                <article class="rounded-2xl border border-slate-800 bg-[#111a2e] p-6 shadow-xl">
                    <p class="text-sm text-slate-400">{{ __($stat['label']) }}</p>
                    <p class="mt-3 text-2xl font-bold {{ $stat['tone'] }}">
                        {{ app(\App\Services\FinancialAmountFormatter::class)->format($stat['amount'], $currency) }}
                    </p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @can('manage offerings')
                <a href="{{ route('finance.transactions.create', 'offering') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.record_offering') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.recording_guidance') }}</p>
                </a>
            @endcan
            @can('manage tithes')
                <a href="{{ route('finance.transactions.create', 'tithe') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.record_tithe') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.recording_guidance') }}</p>
                </a>
            @endcan
            @can('manage contributions')
                <a href="{{ route('finance.transactions.create', 'contribution') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.record_contribution') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.recording_guidance') }}</p>
                </a>
            @endcan
            @can('manage other income')
                <a href="{{ route('finance.transactions.create', 'other_income') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.record_other_income') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.recording_guidance') }}</p>
                </a>
            @endcan
            @can('manage expenses')
                <a href="{{ route('finance.transactions.create', 'expense') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.record_expense') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.recording_guidance') }}</p>
                </a>
            @endcan
            @canany(['record envelope offerings', 'manage offerings'])
                <a href="{{ route('finance.envelopes.create') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.record_by_envelope') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.envelope_entry_description') }}</p>
                </a>
            @endcanany
            @can('import envelope offerings')
                <a href="{{ route('finance.imports.create') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.import_envelope_offerings') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.import_description') }}</p>
                </a>
            @endcan
            @can('view financial receipts')
                <a href="{{ route('finance.receipts.index') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.receipts_title') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.receipts_description') }}</p>
                </a>
            @endcan
            @can('view financial reports')
                <a href="{{ route('finance.reports.index') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.reports_title') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.reports_description') }}</p>
                </a>
            @endcan
            @can('manage finance categories')
                @foreach ($types as $type)
                    <a href="{{ route('finance.categories.index', $type) }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                        <p class="font-semibold text-white">{{ __('finance.manage_categories') }} · {{ __('finance.types.'.$type) }}</p>
                    </a>
                @endforeach
            @endcan
            @can('manage financial settings')
                <a href="{{ route('finance.sms.index') }}" class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5 transition hover:border-blue-500/50 hover:bg-slate-800/60">
                    <p class="font-semibold text-white">{{ __('finance.manage_sms') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('finance.sms_log_description') }}</p>
                </a>
            @endcan
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-[#111a2e]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-5 py-4">
                <h3 class="font-semibold text-white">{{ __('finance.recent_transactions') }}</h3>
                <a href="{{ route('finance.transactions.index') }}" class="text-sm font-semibold text-blue-300 hover:text-blue-200">{{ __('finance.view_all_transactions') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">{{ __('finance.transaction_number') }}</th><th class="px-5 py-3">{{ __('finance.type') }}</th><th class="px-5 py-3">{{ __('finance.amount') }}</th><th class="px-5 py-3">{{ __('finance.status') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($recentTransactions as $transaction)
                            <tr>
                                <td class="px-5 py-4"><a class="font-semibold text-blue-300 hover:text-blue-200" href="{{ route('finance.transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a></td>
                                <td class="px-5 py-4 text-slate-300">{{ __('finance.types.'.$transaction->type) }}</td>
                                <td class="px-5 py-4 text-slate-200">{{ app(\App\Services\FinancialAmountFormatter::class)->format($transaction->amount, $transaction->currency) }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ __('finance.statuses.'.$transaction->status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">{{ __('finance.no_transactions') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
