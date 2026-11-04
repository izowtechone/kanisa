<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ __('finance.sunday_offering_details') }}</p>
                <h2 class="mt-2 text-3xl font-bold text-white">{{ $transaction->receipt_number }}</h2>
                <p class="mt-2 font-mono text-sm text-slate-400">{{ $transaction->transaction_number }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('view receipts')
                    <a href="{{ route('finance.receipts.show', $transaction->receipt_number) }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800">{{ __('finance.receipt') }}</a>
                @endcan
                <a href="{{ route('finance.sunday-offerings.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-800">{{ __('finance.sunday_offerings_title') }}</a>
            </div>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="grid gap-4 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:grid-cols-2 sm:p-7">
            @foreach ([
                [__('finance.member'), $transaction->member_name ?? __('finance.unassigned_member')],
                [__('finance.membership_number'), $transaction->member_number ?? '—'],
                [__('finance.sunday_offering_community'), $transaction->member?->community?->name ?? '—'],
                [__('finance.envelope_code'), $transaction->envelope_code ?? '—'],
                [__('finance.category'), $transaction->category?->localizedName() ?? '—'],
                [__('finance.transaction_date'), $transaction->transaction_date->format('Y-m-d')],
                [__('finance.amount'), app(\App\Services\FinancialAmountFormatter::class)->format($transaction->amount, $transaction->currency)],
                [__('finance.payment_method'), __('finance.payment_methods.'.$transaction->payment_method)],
                [__('finance.reference_number'), $transaction->reference_number ?? '—'],
                [__('finance.status'), __('finance.statuses.'.$transaction->status)],
                [__('finance.created_by'), $transaction->creator?->name ?? '—'],
                [__('finance.approved_by'), $transaction->approver?->name ?? '—'],
            ] as [$label, $value])
                <div class="border-b border-slate-800 pb-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-1 break-words font-medium text-slate-100">{{ $value }}</p>
                </div>
            @endforeach
            @if ($transaction->description)
                <div class="sm:col-span-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.description_field') }}</p>
                    <p class="mt-1 whitespace-pre-line text-slate-200">{{ $transaction->description }}</p>
                </div>
            @endif
            <div class="sm:col-span-2">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.sms_title') }}</p>
                @if ($transaction->smsLog)
                    <p class="mt-1 font-medium text-slate-100">{{ __('finance.sms_statuses.'.$transaction->smsLog->status) }}</p>
                    @if ($transaction->smsLog->error_message)
                        <p class="mt-1 text-sm text-amber-300">{{ $transaction->smsLog->error_message }}</p>
                    @endif
                    @can('retry failed SMS')
                        @if (in_array($transaction->smsLog->status, ['failed', 'not_configured'], true))
                            <form method="POST" action="{{ route('finance.sms.retry', $transaction->smsLog) }}" class="mt-3" onsubmit="return window.confirm(@js(__('finance.confirm_sms_retry')))">
                                @csrf
                                <button class="rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-2 text-sm font-semibold text-blue-300 hover:bg-blue-500/20">{{ __('finance.retry') }}</button>
                            </form>
                        @endif
                    @endcan
                @else
                    <p class="mt-1 text-sm text-slate-400">{{ __('finance.sunday_offering_sms_not_queued') }}</p>
                @endif
                <p class="mt-2 text-xs text-slate-500">{{ __('finance.sms_log_description') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ __('finance.sunday_offering_sms_recording_note') }}</p>
            </div>
        </section>

        <section class="flex flex-wrap gap-3">
            @can('approve financial transactions')
                @if ($transaction->status === 'pending')
                    <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}" onsubmit="return window.confirm(@js(__('finance.confirm_approve')))">
                        @csrf
                        <button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">{{ __('finance.approve') }}</button>
                    </form>
                @endif
            @endcan
            @can('void financial transactions')
                @if ($transaction->status === 'pending')
                    <form method="POST" action="{{ route('finance.transactions.void', $transaction) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" onsubmit="return window.confirm(@js(__('finance.confirm_void')))">
                        @csrf
                        <label class="space-y-1 text-xs text-slate-400">
                            <span>{{ __('finance.void_reason') }}</span>
                            <input required name="reason" maxlength="500" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                        </label>
                        <button class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-2.5 text-sm font-semibold text-rose-300 hover:bg-rose-500/20">{{ __('finance.void') }}</button>
                    </form>
                @endif
            @endcan
        </section>
    </div>
</x-app-layout>
