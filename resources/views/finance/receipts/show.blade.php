<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-5">
        <div class="flex flex-wrap justify-between gap-3 print:hidden"><a href="{{ route('finance.receipts.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300">{{ __('finance.receipts_title') }}</a><button type="button" onclick="window.print()" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">{{ __('finance.print_receipt') }}</button></div>
        <article class="rounded-2xl border border-slate-800 bg-[#111a2e] p-6 sm:p-10 print:border-0 print:bg-white print:text-black">
            <div class="border-b border-slate-700 pb-5 text-center">
                @if ($churchLogo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($churchLogo) }}" alt="{{ __('finance.church_name') }}" class="mx-auto mb-4 h-20 max-w-48 object-contain">
                @endif
                <p class="text-xl font-bold text-white print:text-black">{{ $churchName }}</p>
                @if ($churchAddress)<p class="mt-1 text-sm text-slate-400 print:text-gray-600">{{ $churchAddress }}</p>@endif
                @if ($churchPhone)<p class="text-sm text-slate-400 print:text-gray-600">{{ $churchPhone }}</p>@endif
                @if ($churchEmail)<p class="text-sm text-slate-400 print:text-gray-600">{{ $churchEmail }}</p>@endif
                <h2 class="mt-5 text-lg font-bold uppercase tracking-[0.15em] text-blue-300 print:text-black">{{ __('finance.receipt') }}</h2>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.receipt_number') }}</p><p class="mt-1 font-semibold text-white print:text-black">{{ $transaction->receipt_number }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.transaction_date') }}</p><p class="mt-1 font-semibold text-white print:text-black">{{ $transaction->transaction_date->format('Y-m-d') }}</p></div>
                <div class="sm:col-span-2"><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ $transaction->type === 'expense' ? __('finance.paid_to') : __('finance.receipt_for') }}</p><p class="mt-1 font-semibold text-white print:text-black">{{ $transaction->type === 'expense' ? $transaction->payee : ($transaction->is_anonymous ? __('finance.anonymous') : ($transaction->member_name ?? __('finance.unassigned_member'))) }}</p>@if ($transaction->member_number)<p class="mt-1 text-sm text-slate-400 print:text-gray-600">{{ $transaction->member_number }}</p>@endif</div>
                <div><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.type') }}</p><p class="mt-1 font-medium text-slate-200 print:text-black">{{ __('finance.types.'.$transaction->type) }}</p></div>
                @if ($transaction->category)<div><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.category') }}</p><p class="mt-1 font-medium text-slate-200 print:text-black">{{ $transaction->category->localizedName() }}</p></div>@endif
                @if ($transaction->campaign)<div><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.campaign') }}</p><p class="mt-1 font-medium text-slate-200 print:text-black">{{ $transaction->campaign->name }}</p></div>@endif
                <div><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.payment_method') }}</p><p class="mt-1 font-medium text-slate-200 print:text-black">{{ __('finance.payment_methods.'.$transaction->payment_method) }}</p></div>
                @if ($transaction->reference_number)<div><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.reference_number') }}</p><p class="mt-1 font-medium text-slate-200 print:text-black">{{ $transaction->reference_number }}</p></div>@endif
                @if ($transaction->description)<div class="sm:col-span-2"><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.description_field') }}</p><p class="mt-1 font-medium text-slate-200 print:text-black">{{ $transaction->description }}</p></div>@endif
                <div class="sm:col-span-2"><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.received_by') }}</p><p class="mt-1 font-medium text-slate-200 print:text-black">{{ $transaction->creator?->name ?? '—' }}</p></div>
                <div class="sm:col-span-2"><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.amount') }}</p><p class="mt-1 text-2xl font-bold text-white print:text-black">{{ app(\App\Services\FinancialAmountFormatter::class)->format($transaction->amount, $transaction->currency) }}</p></div>
                <div class="sm:col-span-2"><p class="text-xs uppercase tracking-wide text-slate-500 print:text-gray-600">{{ __('finance.status') }}</p><p class="mt-1 font-medium text-slate-200 print:text-black">{{ __('finance.statuses.'.$transaction->status) }}</p></div>
            </div>
            <p class="mt-8 border-t border-slate-700 pt-5 text-center text-sm text-slate-400 print:text-gray-600">{{ __('finance.receipt_footer') }}</p>
            <p class="mt-4 break-all text-center text-xs text-slate-500 print:text-gray-600">{{ __('finance.verification_link') }}: {{ route('receipts.verify', $transaction->receipt_number) }}</p>
        </article>
    </div>
</x-app-layout>
