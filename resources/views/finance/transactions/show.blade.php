<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('finance.transaction_details') }}</p><h2 class="mt-2 text-3xl font-bold text-white">{{ $transaction->transaction_number }}</h2></div><div class="flex flex-wrap gap-2"><a href="{{ route('finance.receipts.show', $transaction->receipt_number) }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800">{{ __('finance.receipt') }}</a><a href="{{ route('finance.transactions.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-800">{{ __('finance.transactions_title') }}</a></div></div>
        @if (session('success'))<div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="grid gap-4 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:grid-cols-2 sm:p-7">
            @foreach ([
                ['finance.receipt_number', $transaction->receipt_number],
                ['finance.type', __('finance.types.'.$transaction->type)],
                ['finance.category', $transaction->category?->name ?? '—'],
                ['finance.campaign', $transaction->campaign?->name ?? '—'],
                ['finance.member', $transaction->is_anonymous ? __('finance.anonymous') : ($transaction->member_number ?? __('finance.unassigned_member'))],
                ['finance.envelope_code', $transaction->envelope_code ?? '—'],
                ['finance.payee', $transaction->payee ?? '—'],
                ['finance.transaction_date', $transaction->transaction_date->format('Y-m-d')],
                ['finance.amount', app(\App\Services\FinancialAmountFormatter::class)->format($transaction->amount, $transaction->currency)],
                ['finance.payment_method', __('finance.payment_methods.'.$transaction->payment_method)],
                ['finance.reference_number', $transaction->reference_number ?? '—'],
                ['finance.status', __('finance.statuses.'.$transaction->status)],
                ['finance.created_by', $transaction->creator?->name ?? '—'],
                ['finance.approved_by', $transaction->approver?->name ?? '—'],
            ] as [$label, $value])
                <div class="border-b border-slate-800 pb-3"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __($label) }}</p><p class="mt-1 break-words font-medium text-slate-100">{{ $value }}</p></div>
            @endforeach
            @if ($transaction->description)<div class="sm:col-span-2"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.description_field') }}</p><p class="mt-1 text-slate-200">{{ $transaction->description }}</p></div>@endif
            @if ($transaction->notes)<div class="sm:col-span-2"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.notes') }}</p><p class="mt-1 whitespace-pre-line text-slate-200">{{ $transaction->notes }}</p></div>@endif
        </section>
        <section class="flex flex-wrap gap-3">
            @can('approve financial transactions')
                @if ($transaction->status === 'pending')
                    <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}" onsubmit="return window.confirm(@js(__('finance.confirm_approve')))">@csrf<button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">{{ __('finance.approve') }}</button></form>
                @endif
            @endcan
            @can('void financial transactions')
                @if ($transaction->status === 'pending')
                    <form method="POST" action="{{ route('finance.transactions.void', $transaction) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" onsubmit="return window.confirm(@js(__('finance.confirm_void')))">
                        @csrf
                        <label class="space-y-1 text-xs text-slate-400"><span>{{ __('finance.void_reason') }}</span><input required name="reason" maxlength="500" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100"></label>
                        <button class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-2.5 text-sm font-semibold text-rose-300 hover:bg-rose-500/20">{{ __('finance.void') }}</button>
                    </form>
                @endif
            @endcan
            @can('reverse financial transactions')
                @if ($transaction->status === 'approved')
                    <form method="POST" action="{{ route('finance.transactions.reverse', $transaction) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" onsubmit="return window.confirm(@js(__('finance.confirm_reverse')))">
                        @csrf
                        <label class="space-y-1 text-xs text-slate-400"><span>{{ __('finance.reverse_reason') }}</span><input required name="reason" maxlength="500" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100"></label>
                        <button class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-2.5 text-sm font-semibold text-amber-300 hover:bg-amber-500/20">{{ __('finance.reverse') }}</button>
                    </form>
                @endif
            @endcan
        </section>
        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-[#111a2e]">
            <div class="border-b border-slate-800 px-5 py-4"><h3 class="font-semibold text-white">{{ __('finance.audit_history') }}</h3></div>
            <ul class="divide-y divide-slate-800">
                @forelse ($transaction->auditEvents as $event)
                    <li class="flex flex-wrap justify-between gap-2 px-5 py-4 text-sm"><span class="font-medium text-slate-200">{{ __('finance.audit_actions.'.$event->action, [], app()->getLocale()) }}</span><span class="text-slate-400">{{ $event->actor_name ?? $event->user?->name ?? '—' }} · {{ $event->created_at->format('Y-m-d H:i:s') }}</span></li>
                    @if (is_string($event->details['reason'] ?? null) && $event->details['reason'] !== '')
                        <li class="px-5 pb-4 text-sm text-slate-400">{{ __('finance.reason') }}: {{ $event->details['reason'] }}</li>
                    @endif
                @empty
                    <li class="px-5 py-4 text-sm text-slate-400">—</li>
                @endforelse
            </ul>
        </section>
        @if ($transaction->smsLog)
            <p class="text-sm text-slate-400">{{ __('finance.sms_log_description') }} <span class="text-slate-200">{{ __('finance.sms_'.$transaction->smsLog->status) }}</span></p>
        @endif
    </div>
</x-app-layout>
