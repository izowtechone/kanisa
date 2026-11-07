<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-400">{{ __('security.eyebrow') }}</p>
            <h1 class="mt-2 text-3xl font-bold text-white">{{ __('security.title') }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ __('security.description') }}</p>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-[#111a2e]">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-slate-800 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-4">{{ __('security.time') }}</th>
                            <th class="px-5 py-4">{{ __('security.account') }}</th>
                            <th class="px-5 py-4">{{ __('security.event') }}</th>
                            <th class="px-5 py-4">{{ __('security.ip') }}</th>
                            <th class="px-5 py-4">{{ __('security.details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($events as $event)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-300">{{ $event->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="px-5 py-4 text-slate-200">{{ $event->user?->email ?? __('security.unknown_account') }}</td>
                                <td class="px-5 py-4 font-medium text-white">{{ __('security.events.'.$event->event) }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ $event->ip_address ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-400">{{ class_basename($event->metadata['exception'] ?? '') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">{{ __('security.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 p-4">{{ $events->links() }}</div>
        </section>
    </div>
</x-app-layout>
