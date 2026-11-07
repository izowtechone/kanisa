<x-app-layout :title="$memberImport->status === 'completed' ? __('members.import_complete') : __('members.preview_title')">
    <div class="bg-[#0b1120] px-4 py-8 text-gray-100 sm:px-8">
        <div class="mx-auto max-w-7xl space-y-7">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">
                        {{ __('members.eyebrow') }}
                    </p>
                    <p class="mt-2 text-sm text-gray-400">
                        {{ $memberImport->status === 'completed' ? __('members.import_summary') : __('members.preview_description') }}
                    </p>
                </div>
                <a
                    href="{{ route('members.import.create') }}"
                    class="rounded-lg border border-gray-700 px-5 py-3 text-sm text-gray-300 transition hover:bg-gray-800"
                >
                    {{ __('members.back_to_upload') }}
                </a>
            </div>

            @if (session('success'))
                <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-700/60 bg-amber-950/40 p-5 text-sm text-amber-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    @can('manage settings')
                        <a href="{{ route('settings.edit') }}" class="font-semibold text-blue-300 underline underline-offset-4">
                            {{ __('members.open_settings') }}
                        </a>
                    @endcan
                </div>
            @endif

            @if ($memberImport->status === 'completed')
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['label' => 'members.total_rows', 'value' => $memberImport->total_rows],
                        ['label' => 'members.imported_rows', 'value' => $memberImport->imported_rows],
                        ['label' => 'members.failed_rows', 'value' => $memberImport->failed_rows],
                        ['label' => 'members.skipped_rows', 'value' => $memberImport->skipped_rows],
                    ] as $stat)
                        <div class="rounded-2xl border border-gray-800 bg-[#111a2e] p-6 shadow-xl">
                            <p class="text-sm text-gray-400">{{ __($stat['label']) }}</p>
                            <p class="mt-3 text-4xl font-bold text-white">{{ number_format($stat['value']) }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($memberImport->skipped_rows + $memberImport->failed_rows > 0)
                    <a data-no-loading
                        href="{{ route('members.import.report', $memberImport) }}"
                        class="inline-flex rounded-lg border border-blue-500/40 bg-blue-500/10 px-5 py-3 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/20"
                    >
                        {{ __('members.download_rejected_rows') }}
                    </a>
                @endif
            @else
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['label' => 'members.total_rows', 'value' => $memberImport->total_rows],
                        ['label' => 'members.valid_rows', 'value' => $memberImport->valid_rows],
                        ['label' => 'members.invalid_rows', 'value' => $memberImport->invalid_rows],
                        ['label' => 'members.duplicate_rows', 'value' => $memberImport->duplicate_rows],
                    ] as $stat)
                        <div class="rounded-2xl border border-gray-800 bg-[#111a2e] p-6 shadow-xl">
                            <p class="text-sm text-gray-400">{{ __($stat['label']) }}</p>
                            <p class="mt-3 text-4xl font-bold text-white">{{ number_format($stat['value']) }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($memberImport->invalid_rows > 0)
                    <div class="overflow-hidden rounded-2xl border border-gray-800 bg-[#111a2e] shadow-xl">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-800 px-5 py-5 sm:px-6">
                            <div>
                                <h3 class="font-semibold text-white">{{ __('members.validation_errors') }}</h3>
                                <p class="mt-1 text-xs text-gray-500">{{ __('members.invalid_rows') }}: {{ $memberImport->invalid_rows }}</p>
                            </div>
                            <a data-no-loading
                                href="{{ route('members.import.report', $memberImport) }}"
                                class="text-sm font-semibold text-blue-300 underline underline-offset-4"
                            >
                                {{ __('members.download_rejected_rows') }}
                            </a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[700px] text-left text-sm">
                                <thead class="bg-[#0d1628] text-xs uppercase tracking-wider text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-5 py-4">{{ __('members.excel_row') }}</th>
                                        <th scope="col" class="px-5 py-4">{{ __('members.full_name') }}</th>
                                        <th scope="col" class="px-5 py-4">{{ __('members.envelope_number') }}</th>
                                        <th scope="col" class="px-5 py-4">{{ __('members.validation_errors') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    @foreach ($rows as $row)
                                        @if ($row['errors'] !== [])
                                            <tr>
                                                <td class="whitespace-nowrap px-5 py-4 text-gray-400">{{ $row['row_number'] }}</td>
                                                <td class="px-5 py-4 text-gray-200">
                                                    {{ collect([$row['values']['first_name'], $row['values']['middle_name'], $row['values']['last_name']])->filter()->implode(' ') ?: '—' }}
                                                </td>
                                                <td class="px-5 py-4 text-gray-300">{{ $row['values']['envelope_number'] ?? '—' }}</td>
                                                <td class="px-5 py-4 text-red-300">
                                                    <ul class="list-inside list-disc space-y-1">
                                                        @foreach ($row['errors'] as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-800 bg-[#111a2e] p-5">
                    <p class="text-sm text-gray-400">
                        {{ $memberImport->valid_rows > 0 ? __('members.preview_description') : __('members.no_valid_rows') }}
                    </p>
                    @if ($memberImport->valid_rows > 0 && $membershipNumberConfigured)
                        <form
                            x-data="{ importing: false }"
                            @submit="if (importing) { $event.preventDefault(); } else { importing = true; }"
                            action="{{ route('members.import.confirm', $memberImport) }}"
                            method="POST"
                        >
                            @csrf
                            <button
                                type="submit"
                                x-bind:disabled="importing"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-6 py-3 font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span x-show="!importing">{{ __('members.confirm_import') }}</span>
                                <span x-cloak x-show="importing" class="inline-flex items-center gap-2">
                                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                    {{ __('members.importing') }}
                                </span>
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
