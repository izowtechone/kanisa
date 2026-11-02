<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('finance.navigation') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ __('finance.sunday_offerings_title') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('finance.sunday_offerings_description') }}</p>
            </div>
            @can('create Sunday offerings')
                <a href="{{ route('finance.sunday-offerings.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">
                    {{ __('finance.record_sunday_offering') }}
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="GET" action="{{ route('finance.sunday-offerings.index') }}" class="grid gap-3 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_13rem_13rem_13rem_auto_auto]">
            <label class="space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.search') }}</span>
                <input name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="{{ __('finance.search_placeholder') }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white placeholder:text-slate-500">
            </label>
            <label class="space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.status') }}</span>
                <select name="status" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white">
                    <option value="">{{ __('finance.all_statuses') }}</option>
                    @foreach (\App\Models\FinancialTransaction::STATUSES as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __('finance.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.from_date') }}</span>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white [color-scheme:dark]">
            </label>
            <label class="space-y-2 text-sm text-slate-300">
                <span>{{ __('finance.to_date') }}</span>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white [color-scheme:dark]">
            </label>
            <button type="submit" class="self-end rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.filter') }}</button>
            <a href="{{ route('finance.sunday-offerings.index') }}" class="self-end rounded-lg border border-slate-700 px-5 py-3 text-center text-sm font-semibold text-slate-300 hover:bg-slate-800">{{ __('finance.reset') }}</a>
        </form>

        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-[#111a2e] shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-sm">
                    <thead class="border-b border-slate-800 bg-slate-900/50 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('finance.receipt_number') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('finance.member') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('communities.navigation') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('finance.transaction_date') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('finance.amount') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('finance.status') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('finance.sms_title') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($transactions as $transaction)
                            <tr class="text-slate-300">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <a href="{{ route('finance.sunday-offerings.show', $transaction) }}" class="font-mono font-semibold text-blue-300 hover:text-blue-200">{{ $transaction->receipt_number }}</a>
                                    <span class="mt-1 block text-xs text-slate-500">{{ $transaction->transaction_number }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="block font-medium text-white">{{ $transaction->member_name ?? __('finance.unassigned_member') }}</span>
                                    <span class="font-mono text-xs text-slate-500">{{ $transaction->member_number ?? '—' }}</span>
                                </td>
                                <td class="px-5 py-4 text-slate-400">{{ $transaction->member?->community?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 font-medium text-white">{{ app(\App\Services\FinancialAmountFormatter::class)->format($transaction->amount, $transaction->currency) }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ __('finance.statuses.'.$transaction->status) }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @if ($transaction->smsLog)
                                        {{ __('finance.sms_statuses.'.$transaction->smsLog->status) }}
                                    @else
                                        <span class="text-slate-500">{{ __('finance.sunday_offering_sms_not_queued') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">{{ __('finance.sunday_offerings_empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($transactions->hasPages())
                <div class="border-t border-slate-800 px-5 py-4">{{ $transactions->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
