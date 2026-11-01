<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            {{ __('members.settings_title') }}
        </h2>
    </x-slot>

    <div class="bg-[#0b1120] px-4 py-8 text-gray-100 sm:px-8">
        <div class="mx-auto max-w-4xl space-y-7">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">
                    {{ __('members.eyebrow') }}
                </p>
                <p class="mt-2 text-sm text-gray-400">
                    {{ __('members.settings_description') }}
                </p>
            </div>

            @if (session('success'))
                <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-800 bg-red-950/60 p-5 text-red-300">
                    <p class="mb-2 font-semibold">{{ __('members.fix_errors') }}</p>
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($canManageChurchSettings)
                <form
                    x-data="{ saving: false }"
                    @submit="if (saving) { $event.preventDefault(); } else { saving = true; }"
                    action="{{ route('settings.update') }}"
                    method="POST"
                    enctype="multipart/form-data"
                    class="space-y-6 rounded-2xl border border-gray-800 bg-[#111a2e] p-6 shadow-xl sm:p-8"
                >
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="church_short_name" class="mb-2 block text-sm font-medium text-gray-300">
                            {{ __('members.church_short_name') }}
                        </label>
                        <input
                            id="church_short_name"
                            type="text"
                            name="church_short_name"
                            value="{{ old('church_short_name', $churchShortName) }}"
                            maxlength="80"
                            pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*"
                            autocomplete="off"
                            required
                            aria-describedby="church-short-name-help"
                            class="w-full rounded-lg border bg-[#0b1120] px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('church_short_name') ? 'border-red-600' : 'border-gray-700' }}"
                        >
                        <p id="church-short-name-help" class="mt-2 text-xs text-gray-500">
                            {{ __('members.church_short_name_help') }}
                        </p>
                        @error('church_short_name')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="church_logo" class="mb-2 block text-sm font-medium text-gray-300">{{ __('members.church_logo') }}</label>
                        @if ($churchLogo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($churchLogo) }}" alt="{{ __('members.church_logo') }}" class="mb-3 h-20 max-w-48 rounded-lg border border-gray-700 object-contain p-2">
                        @endif
                        <input id="church_logo" type="file" name="church_logo" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-lg border border-gray-700 bg-[#0b1120] px-4 py-3 text-white">
                        <p class="mt-2 text-xs text-gray-500">{{ __('members.church_logo_help') }}</p>
                        @error('church_logo')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        @if ($churchLogo)
                            <label class="mt-3 flex items-center gap-2 text-sm text-gray-300"><input type="checkbox" name="remove_church_logo" value="1" class="rounded border-gray-600 bg-[#0b1120] text-blue-500">{{ __('members.remove_church_logo') }}</label>
                        @endif
                    </div>

                    <div>
                        <label for="system_language" class="mb-2 block text-sm font-medium text-gray-300">
                            {{ __('members.system_language') }}
                        </label>
                        <select
                            id="system_language"
                            name="system_language"
                            required
                            class="w-full rounded-lg border bg-[#0b1120] px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('system_language') ? 'border-red-600' : 'border-gray-700' }}"
                        >
                            <option value="sw" @selected(old('system_language', $systemLanguage) === 'sw')>
                                {{ __('members.kiswahili') }}
                            </option>
                            <option value="en" @selected(old('system_language', $systemLanguage) === 'en')>
                                {{ __('members.english') }}
                            </option>
                        </select>
                        @error('system_language')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end border-t border-gray-800 pt-5">
                        <button
                            type="submit"
                            x-bind:disabled="saving"
                            class="rounded-lg bg-blue-600 px-7 py-3 font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span x-show="!saving">{{ __('members.save_settings') }}</span>
                            <span x-cloak x-show="saving">{{ __('members.saving') }}</span>
                        </button>
                    </div>
                </form>
            @endif

            @can('manage SMS settings')
                @include('settings.partials.sms-notifications')
            @endcan
        </div>
    </div>
</x-app-layout>
