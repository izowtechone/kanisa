<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold text-white">{{ __('finance.sms_title') }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('finance.sms_log_description') }}</p>
            </div>
            @can('send bulk SMS')
                <a href="{{ route('finance.sms.campaigns.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">
                    {{ __('finance.bulk_sms_create') }}
                </a>
            @endcan
            @can('view SMS campaigns')
                <a href="{{ route('finance.sms.campaigns.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">
                    {{ __('finance.bulk_sms_history') }}
                </a>
            @endcan
        </div>

        <p role="note" class="rounded-xl border border-blue-900/70 bg-blue-950/30 p-4 text-sm text-blue-200">{{ __('finance.sms_delivery_tracking_note') }}</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.swala_provider') }}</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ __('finance.swala_provider') }}</p>
                <p class="mt-2 text-xs text-slate-400">
                    {{ __('finance.swala_connection') }}:
                    @if ($lastCheckStatus === 'ok')
                        <span class="text-emerald-300">{{ __('finance.swala_status_connected') }}</span>
                    @elseif ($lastCheckStatus === 'failed')
                        <span class="text-rose-300">{{ __('finance.swala_status_disconnected') }}</span>
                    @else
                        <span>{{ __('finance.swala_status_not_checked') }}</span>
                    @endif
                </p>
                <p class="mt-1 truncate text-xs text-slate-400">{{ __('finance.swala_sender_id') }}: {{ $senderId ?: '—' }}</p>
                @if ($lastBalance !== null)
                    <p class="mt-1 text-xs text-slate-400">{{ __('finance.swala_last_balance', ['balance' => $lastBalance]) }}</p>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.sms_total_messages') }}</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ number_format($totalMessages) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.sms_pending_messages') }}</p>
                <p class="mt-2 text-2xl font-bold text-amber-200">{{ number_format($pendingCount) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.delivery_statuses.delivered') }}</p>
                <p class="mt-2 text-2xl font-bold text-emerald-300">{{ number_format((int) ($deliveryCounts['delivered'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.sms_statuses.failed') }}</p>
                <p class="mt-2 text-2xl font-bold text-rose-300">{{ number_format($failedCount) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('finance.sms_failed_jobs') }}</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ $failedJobCount === null ? '—' : number_format((int) $failedJobCount) }}</p>
            </div>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('finance.sms.index') }}" class="space-y-4 rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
            <h3 class="text-sm font-semibold text-white">{{ __('finance.sms_history_filters') }}</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <label class="block space-y-2 text-xs font-medium text-slate-400 lg:col-span-2">
                    <span>{{ __('finance.search') }}</span>
                    <input type="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="{{ __('finance.search_sms_placeholder') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100 placeholder:text-slate-500">
                </label>
                <label class="block space-y-2 text-xs font-medium text-slate-400">
                    <span>{{ __('finance.sms_type') }}</span>
                    <select name="type" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                        <option value="">{{ __('finance.all_sms_types') }}</option>
                        <option value="financial_receipt" @selected(request('type') === 'financial_receipt')>{{ __('finance.financial_receipt') }}</option>
                        <option value="sunday_offering" @selected(request('type') === 'sunday_offering')>{{ __('finance.sunday_offering') }}</option>
                        <option value="test_sms" @selected(request('type') === 'test_sms')>{{ __('finance.test_message') }}</option>
                    </select>
                </label>
                <label class="block space-y-2 text-xs font-medium text-slate-400">
                    <span>{{ __('finance.status') }}</span>
                    <select name="status" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                        <option value="">{{ __('finance.all_sms_statuses') }}</option>
                        @foreach (['queued', 'sending', 'accepted', 'test_only', 'not_configured', 'no_phone', 'failed', 'cancelled'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('finance.sms_statuses.'.$status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block space-y-2 text-xs font-medium text-slate-400">
                    <span>{{ __('finance.delivery_status') }}</span>
                    <select name="delivery_status" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                        <option value="">{{ __('finance.all_delivery_statuses') }}</option>
                        @foreach (['queued', 'processing', 'sent', 'delivered', 'failed', 'scheduled'] as $deliveryStatus)
                            <option value="{{ $deliveryStatus }}" @selected(request('delivery_status') === $deliveryStatus)>{{ __('finance.delivery_statuses.'.$deliveryStatus) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block space-y-2 text-xs font-medium text-slate-400">
                    <span>{{ __('finance.from_date') }}</span>
                    <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                </label>
                <label class="block space-y-2 text-xs font-medium text-slate-400">
                    <span>{{ __('finance.to_date') }}</span>
                    <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-lg border-slate-700 bg-[#0b1120] text-sm text-slate-100">
                </label>
            </div>
            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('finance.sms.index') }}" class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-slate-800">{{ __('finance.reset') }}</a>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('finance.filter') }}</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-[#111a2e]">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">{{ __('finance.sms_type') }}</th>
                        <th class="px-5 py-3">{{ __('finance.transaction_number') }}</th>
                        <th class="px-5 py-3">{{ __('finance.phone') }}</th>
                        <th class="px-5 py-3">{{ __('finance.status') }}</th>
                        <th class="px-5 py-3">{{ __('finance.delivery_status') }}</th>
                        <th class="px-5 py-3">{{ __('finance.attempts') }}</th>
                        <th class="px-5 py-3">{{ __('finance.provider_reference') }}</th>
                        <th class="px-5 py-3">{{ __('finance.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-5 py-4 text-slate-300">{{ __('finance.'.match ($log->notification_type) { 'test_sms' => 'test_message', 'bulk_campaign' => 'bulk_sms', 'sunday_offering' => 'sunday_offering', default => 'financial_receipt' }) }}</td>
                            <td class="px-5 py-4">
                                @if ($log->transaction)
                                    <a class="font-semibold text-blue-300 hover:text-blue-200" href="{{ route('finance.transactions.show', $log->transaction) }}">{{ $log->transaction->transaction_number }}</a>
                                @else
                                    <span class="text-slate-500">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-mono text-slate-300">{{ $log->maskedPhone() }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ __('finance.sms_statuses.'.$log->status) }}</td>
                            <td class="px-5 py-4 text-slate-300">
                                {{ $log->delivery_status ? __('finance.delivery_statuses.'.$log->delivery_status) : '—' }}
                                @if ($log->provider_failure_reason)
                                    <span class="block text-xs text-rose-300">{{ $log->provider_failure_reason }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-300">{{ $log->attempts }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-400">{{ $log->provider_message_id ?? '—' }}</td>
                            <td class="space-y-2 px-5 py-4">
                                @can('view SMS history')
                                    @if ($log->provider === 'swalasms' && $log->provider_message_id && ! $log->isFinalDelivery())
                                        <form method="POST" action="{{ route('finance.sms.refresh-status', $log) }}">
                                            @csrf
                                            <button type="submit" class="font-semibold text-slate-300 hover:text-white">{{ __('finance.refresh_status') }}</button>
                                        </form>
                                    @endif
                                @endcan
                                @can('retry failed SMS')
                                    @if (in_array($log->status, ['failed', 'not_configured'], true) || ($log->status === 'accepted' && $log->delivery_status === 'failed'))
                                        <form method="POST" action="{{ route('finance.sms.retry', $log) }}">
                                            @csrf
                                            <button type="submit" class="font-semibold text-blue-300 hover:text-blue-200">{{ __('finance.retry') }}</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                        @if ($log->smsAttempts->isNotEmpty())
                            <tr>
                                <td colspan="8" class="px-5 pb-4">
                                    <details class="text-xs text-slate-400">
                                        <summary class="cursor-pointer font-semibold">{{ __('finance.audit_history') }}</summary>
                                        <ul class="mt-3 space-y-2">
                                            @foreach ($log->smsAttempts as $attempt)
                                                <li>
                                                    {{ $attempt->attempt_number }} · {{ __('finance.sms_statuses.'.$attempt->status) }} · {{ $attempt->started_at->format('Y-m-d H:i:s') }}
                                                    @if ($attempt->error_message)
                                                        · {{ $attempt->error_message }}
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-slate-400">{{ __('finance.sms_history_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $logs->links() }}</div>
    </div>
</x-app-layout>
