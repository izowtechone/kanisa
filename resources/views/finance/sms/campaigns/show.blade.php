<x-app-layout>
    @can('retry failed SMS')
        @if ($senderVerification['status'] !== 'approved')
            <p role="alert" class="mx-auto max-w-7xl rounded-xl border border-amber-700/60 bg-amber-950/40 p-4 text-sm text-amber-200">{{ __('finance.manual_sms_sender', ['sender' => $senderId ?: '—']) }} {{ __('finance.swala_sender_send_warning') }}</p>
        @endif
    @endcan
    @php
        $acceptedCount = $campaign->recipients()->where('status', 'accepted')->count();
        $failedCount = $campaign->recipients()->where('status', 'failed')->count();
        $pendingCount = $campaign->recipients()->whereIn('status', ['queued', 'sending'])->count();
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                @can('view SMS campaigns')
                    <a href="{{ route('finance.sms.campaigns.index') }}" class="text-sm font-semibold text-blue-300 hover:text-blue-200">{{ __('finance.bulk_sms_history') }}</a>
                @endcan
                <h2 class="mt-2 text-3xl font-bold text-white">{{ $campaign->name }}</h2>
                <p class="mt-2 text-sm text-slate-400">{{ __('finance.sms_campaign_status') }}: {{ __('finance.sms_campaign_statuses.'.$campaign->status) }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @can('send bulk SMS')
                    <a href="{{ route('finance.sms.campaigns.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.bulk_sms_create') }}</a>
                @endcan
                @if (in_array($campaign->status, ['scheduled', 'queued', 'processing'], true))
                    @can('cancel SMS campaigns')
                        <form method="POST" action="{{ route('finance.sms.campaigns.cancel', $campaign) }}" onsubmit="return window.confirm(@js(__('finance.sms_campaign_confirm_cancel')))">
                            @csrf
                            <button type="submit" class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-2.5 text-sm font-semibold text-rose-200 hover:bg-rose-500/20">{{ __('finance.sms_campaign_cancel') }}</button>
                        </form>
                    @endcan
                @endif
                @if ($failedCount > 0)
                    @can('retry failed SMS')
                        <form method="POST" action="{{ route('finance.sms.campaigns.retry-failed', $campaign) }}" onsubmit="return window.confirm(@js(__('finance.confirm_retry_sms')))">
                            @csrf
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">{{ __('finance.sms_campaign_retry_failed') }}</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/50 px-5 py-4 text-sm text-red-300">
                <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['finance.sms_campaign_count', $campaign->recipient_count],
                ['finance.sms_campaign_submitted', $acceptedCount],
                ['finance.sms_campaign_failed', $failedCount],
                ['finance.sms_campaign_pending', $pendingCount],
                ['finance.sms_campaign_invalid_phones', $campaign->invalid_phone_count],
                ['finance.sms_campaign_duplicates', $duplicateCount],
                ['finance.sms_campaign_segments', $campaign->sms_segments],
                ['finance.sms_campaign_created_at', $campaign->created_at->format('Y-m-d H:i')],
            ] as [$label, $value])
                <div class="rounded-xl border border-slate-800 bg-[#111a2e] p-4">
                    <p class="text-xs text-slate-400">{{ __($label) }}</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @if ($campaign->error_message && $campaign->status === 'failed')
            <div role="alert" class="rounded-xl border border-amber-700/60 bg-amber-950/30 p-4 text-sm text-amber-200">{{ __('finance.sms_campaign_preparation_failed') }}</div>
        @endif

        <section class="space-y-4 rounded-2xl border border-slate-800 bg-[#111a2e] p-5">
            <h3 class="font-semibold text-white">{{ __('finance.sms_campaign_recipients') }}</h3>
            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('finance.sms_campaign_recipient') }}</th>
                            <th class="px-4 py-3">{{ __('finance.sms_campaign_phone') }}</th>
                            <th class="px-4 py-3">{{ __('finance.status') }}</th>
                            <th class="px-4 py-3">{{ __('finance.sms_campaign_segments') }}</th>
                            <th class="px-4 py-3">{{ __('finance.sms_campaign_provider_reference') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($recipients as $recipient)
                            <tr>
                                <td class="px-4 py-3 text-slate-200">
                                    @if ($recipient->member)
                                        {{ trim($recipient->member->first_name.' '.$recipient->member->middle_name.' '.$recipient->member->last_name) }}
                                        <span class="mt-1 block font-mono text-xs text-slate-500">{{ $recipient->member->membership_number }}</span>
                                    @else
                                        {{ __('finance.anonymous') }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-300">{{ $recipient->maskedPhone() }}</td>
                                <td class="px-4 py-3 text-slate-300">
                                    {{ __('finance.sms_statuses.'.$recipient->status) }}
                                    @if ($recipient->provider_error_code === 'DUPLICATE_RECIPIENT')
                                        <span class="block text-xs text-amber-300">{{ __('finance.sms_campaign_duplicate_recipient') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ $recipient->sms_segments }}</td>
                                <td class="px-4 py-3 font-mono text-slate-400">{{ $recipient->provider_message_id ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('finance.sms_campaign_no_members') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $recipients->links() }}</div>
        </section>
    </div>
</x-app-layout>
