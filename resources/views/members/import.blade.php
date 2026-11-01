<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            {{ __('members.bulk_title') }}
        </h2>
    </x-slot>

    <div class="bg-[#0b1120] px-4 py-8 text-gray-100 sm:px-8">
        <div class="mx-auto max-w-5xl space-y-7">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">
                        {{ __('members.eyebrow') }}
                    </p>
                    <p class="mt-2 text-sm text-gray-400">
                        {{ __('members.bulk_description') }}
                    </p>
                </div>
                <a
                    href="{{ route('members.index') }}"
                    class="rounded-lg border border-gray-700 px-5 py-3 text-sm text-gray-300 transition hover:bg-gray-800"
                >
                    {{ __('members.back_to_list') }}
                </a>
            </div>

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-800 bg-red-950/60 p-5 text-red-300">
                    <p class="mb-2 font-semibold">{{ __('members.validation_errors_heading') }}</p>
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="space-y-5 rounded-2xl border border-gray-800 bg-[#111a2e] p-6 shadow-xl sm:p-8">
                @unless ($membershipNumberConfigured)
                    <div role="alert" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-700/60 bg-amber-950/40 p-5 text-sm text-amber-200">
                        <p>{{ __('members.configure_church_short_name') }}</p>
                        @can('manage settings')
                            <a href="{{ route('settings.edit') }}" class="font-semibold text-blue-300 underline underline-offset-4">
                                {{ __('members.open_settings') }}
                            </a>
                        @endcan
                    </div>
                @endunless

                <div>
                    <h3 class="font-semibold text-white">{{ __('members.upload_instructions_title') }}</h3>
                    <div class="mt-3 space-y-2 text-sm leading-6 text-gray-300">
                        <p><span class="font-semibold text-blue-300">English:</span> {{ __('members.upload_instructions') }}</p>
                        <p><span class="font-semibold text-blue-300">Kiswahili:</span> {{ __('members.upload_instructions_sw') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a
                        href="{{ route('members.import.template', 'xlsx') }}"
                        class="rounded-lg border border-gray-700 px-4 py-3 text-sm font-semibold text-gray-200 transition hover:bg-gray-800"
                    >
                        {{ __('members.download_excel_template') }}
                    </a>
                    <a
                        href="{{ route('members.import.template', 'csv') }}"
                        class="rounded-lg border border-gray-700 px-4 py-3 text-sm font-semibold text-gray-200 transition hover:bg-gray-800"
                    >
                        {{ __('members.download_csv_template') }}
                    </a>
                </div>

                <div class="rounded-xl border border-blue-900/70 bg-blue-950/30 p-4 text-sm text-blue-200">
                    <p>{{ __('members.supported_formats') }}</p>
                    <p class="mt-1">{{ __('members.maximum_file_size', ['size' => number_format(\App\Services\MemberImportService::MAX_FILE_SIZE_KILOBYTES / 1024)]) }}</p>
                </div>

                <form
                    x-data="{ uploading: false }"
                    @submit="if (uploading) { $event.preventDefault(); } else { uploading = true; }"
                    action="{{ route('members.import.store') }}"
                    method="POST"
                    enctype="multipart/form-data"
                    class="space-y-5 border-t border-gray-800 pt-6"
                >
                    @csrf
                    <div>
                        <label for="member-file" class="mb-2 block text-sm font-medium text-gray-300">
                            {{ __('members.choose_file') }}
                        </label>
                        <input
                            id="member-file"
                            type="file"
                            name="file"
                            accept=".csv,.xlsx"
                            required
                            class="block w-full rounded-lg border border-gray-700 bg-[#0b1120] text-sm text-gray-300 file:mr-4 file:border-0 file:bg-blue-600 file:px-4 file:py-3 file:font-semibold file:text-white hover:file:bg-blue-500"
                        >
                        @error('file')
                            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            x-bind:disabled="uploading"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span x-show="!uploading">{{ __('members.validate_upload') }}</span>
                            <span x-cloak x-show="uploading" class="inline-flex items-center gap-2">
                                <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                {{ __('members.validating') }}
                            </span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
