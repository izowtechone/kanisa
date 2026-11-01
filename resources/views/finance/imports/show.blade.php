<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><h2 class="text-3xl font-bold text-white">{{ __('finance.preview_title') }}</h2><p class="mt-2 break-all text-sm text-slate-400">{{ __('finance.import_file') }}: {{ $financialImport->file_name }}</p></div><a href="{{ route('finance.imports.create') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300">{{ __('finance.import_title') }}</a></div>
        @if (session('success'))<div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 p-5 text-sm text-red-300"><ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @php
            $stats = $financialImport->status === 'completed'
                ? [
                    ['finance.total_rows', $financialImport->total_rows],
                    ['finance.imported_rows', $financialImport->valid_rows],
                    ['finance.failed_rows', 0],
                    ['finance.skipped_rows', $financialImport->invalid_rows],
                    ['finance.duplicate_rows', $duplicateRows],
                ]
                : [
                    ['finance.total_rows', $financialImport->total_rows],
                    ['finance.valid_rows', $financialImport->valid_rows],
                    ['finance.invalid_rows', $financialImport->invalid_rows],
                    ['finance.duplicate_rows', $duplicateRows],
                ];
        @endphp
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($stats as [$label, $value])
                <article class="rounded-xl border border-slate-800 bg-[#111a2e] p-5"><p class="text-sm text-slate-400">{{ __($label) }}</p><p class="mt-2 text-2xl font-bold text-white">{{ $value }}</p></article>
            @endforeach
        </section>
        <div class="flex flex-wrap gap-3">
            @if ($financialImport->invalid_rows > 0)<a href="{{ route('finance.imports.report', $financialImport) }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800">{{ __('finance.download_rejected_rows') }}</a>@endif
            @if ($financialImport->status === 'preview' && $financialImport->valid_rows > 0)
                <form method="POST" action="{{ route('finance.imports.confirm', $financialImport) }}" onsubmit="return window.confirm(@js(__('finance.confirm_import').' ('.$financialImport->valid_rows.')'))">@csrf<button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.confirm_import') }}</button></form>
            @endif
        </div>
        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-[#111a2e]">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">{{ __('finance.excel_row') }}</th><th class="px-4 py-3">{{ __('finance.envelope_code') }}</th><th class="px-4 py-3">{{ __('finance.amount') }}</th><th class="px-4 py-3">{{ __('finance.payment_method') }}</th><th class="px-4 py-3">{{ __('finance.transaction_date') }}</th><th class="px-4 py-3">{{ __('finance.status') }}</th><th class="px-4 py-3">{{ __('finance.validation_errors') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-800">@foreach ($financialImport->rows as $row)<tr><td class="px-4 py-3 text-slate-300">{{ $row['row_number'] }}</td><td class="px-4 py-3 text-slate-200">{{ $row['data']['envelope_code'] }}</td><td class="px-4 py-3 text-slate-200">{{ $row['data']['amount'] }}</td><td class="px-4 py-3 text-slate-300">{{ $row['data']['payment_method'] }}</td><td class="px-4 py-3 text-slate-300">{{ $row['data']['transaction_date'] }}</td><td class="px-4 py-3"><span class="{{ $row['is_valid'] ? 'text-emerald-300' : 'text-rose-300' }}">{{ $row['is_valid'] ? __('finance.valid') : __('finance.invalid') }}</span></td><td class="max-w-md px-4 py-3 text-xs text-rose-300">{{ implode(' ', $row['errors']) }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    </div>
</x-app-layout>
