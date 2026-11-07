<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            {{ __('settings.title') }}
        </h2>
    </x-slot>

    <div class="bg-[#0b1120] px-4 py-8 text-gray-100 sm:px-8">
        <div class="mx-auto max-w-5xl space-y-7">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-400">
                    {{ __('settings.eyebrow') }}
                </p>
                <p class="max-w-3xl text-sm text-gray-400">
                    {{ __('settings.description') }}
                </p>
            </div>

            @if (session('success'))
                <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-5 py-4 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-800 bg-red-950/60 p-5 text-red-300">
                    <p class="mb-2 font-semibold">{{ __('settings.fix_errors') }}</p>
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($canManageChurchSettings)
                <nav aria-label="{{ __('settings.categories') }}" class="flex flex-wrap gap-2 border-b border-slate-800 pb-4">
                    <a href="#general-settings" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 transition hover:border-blue-500 hover:text-white">{{ __('settings.general') }}</a>
                    <a href="#church-information" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 transition hover:border-blue-500 hover:text-white">{{ __('settings.church_information') }}</a>
                    <a href="#logo-settings" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 transition hover:border-blue-500 hover:text-white">{{ __('settings.logo_settings') }}</a>
                    <a href="#favicon-settings" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 transition hover:border-blue-500 hover:text-white">{{ __('settings.favicon_settings') }}</a>
                    @if ($canManageSmsSettings)
                        <a href="#sms-notifications" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 transition hover:border-blue-500 hover:text-white">{{ __('settings.sms_settings') }}</a>
                    @endif
                </nav>

                <section id="general-settings" class="scroll-mt-24 space-y-5 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-7">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ __('settings.general') }}</h3>
                        <p class="mt-1 text-sm text-slate-400">{{ __('settings.general_description') }}</p>
                    </div>

                    <form action="{{ route('settings.update') }}" method="POST" class="space-y-5">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="section" value="general">

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="system_name" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.system_name') }}</label>
                                <input id="system_name" name="system_name" value="{{ old('system_name', $systemName) }}" maxlength="100" required class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                @error('system_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="system_language" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.system_language') }}</label>
                                <select id="system_language" name="system_language" required class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                    <option value="sw" @selected(old('system_language', $systemLanguage) === 'sw')>{{ __('settings.kiswahili') }}</option>
                                    <option value="en" @selected(old('system_language', $systemLanguage) === 'en')>{{ __('settings.english') }}</option>
                                </select>
                                @error('system_language')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="timezone" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.timezone') }}</label>
                                <select id="timezone" name="timezone" required class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                    @foreach (\App\Services\AppSettings::TIMEZONES as $timezoneOption)
                                        <option value="{{ $timezoneOption }}" @selected(old('timezone', $timezone) === $timezoneOption)>{{ $timezoneOption }}</option>
                                    @endforeach
                                </select>
                                @error('timezone')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="currency" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.currency') }}</label>
                                <select id="currency" name="currency" required class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                    @foreach (\App\Services\AppSettings::CURRENCIES as $currencyOption)
                                        <option value="{{ $currencyOption }}" @selected(old('currency', $currency) === $currencyOption)>{{ $currencyOption }}</option>
                                    @endforeach
                                </select>
                                @error('currency')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="date_format" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.date_format') }}</label>
                                <select id="date_format" name="date_format" required class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                    @foreach (\App\Services\AppSettings::DATE_FORMATS as $formatOption)
                                        <option value="{{ $formatOption }}" @selected(old('date_format', $dateFormat) === $formatOption)>{{ $formatOption }}</option>
                                    @endforeach
                                </select>
                                @error('date_format')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="time_format" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.time_format') }}</label>
                                <select id="time_format" name="time_format" required class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                    @foreach (\App\Services\AppSettings::TIME_FORMATS as $formatOption)
                                        <option value="{{ $formatOption }}" @selected(old('time_format', $timeFormat) === $formatOption)>{{ $formatOption }}</option>
                                    @endforeach
                                </select>
                                @error('time_format')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="community_code_prefix" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.community_code_prefix') }}</label>
                                <input id="community_code_prefix" name="community_code_prefix" value="{{ old('community_code_prefix', $communityCodePrefix) }}" maxlength="20" pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                <p class="mt-1 text-xs text-slate-500">{{ __('settings.community_code_prefix_help') }}</p>
                                @error('community_code_prefix')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-slate-800 pt-4">
                            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('settings.save_settings') }}</button>
                        </div>
                    </form>
                </section>

                <section id="church-information" class="scroll-mt-24 space-y-5 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-7">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ __('settings.church_information') }}</h3>
                        <p class="mt-1 text-sm text-slate-400">{{ __('settings.church_information_description') }}</p>
                    </div>

                    <form action="{{ route('settings.update') }}" method="POST" class="space-y-5">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="section" value="church">

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="church_name" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.church_name') }}</label>
                                <input id="church_name" name="church_name" value="{{ old('church_name', $churchName) }}" maxlength="255" required class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                @error('church_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="church_short_name" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.church_short_name') }}</label>
                                <input id="church_short_name" name="church_short_name" value="{{ old('church_short_name', $churchShortName) }}" maxlength="80" pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*" required aria-describedby="church-short-name-help" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                <p id="church-short-name-help" class="mt-1 text-xs text-slate-500">{{ __('settings.church_short_name_help') }}</p>
                                @error('church_short_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="church_phone" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.church_phone') }}</label>
                                <input id="church_phone" name="church_phone" type="tel" value="{{ old('church_phone', $churchPhone) }}" maxlength="30" autocomplete="tel" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                @error('church_phone')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="church_email" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.church_email') }}</label>
                                <input id="church_email" name="church_email" type="email" value="{{ old('church_email', $churchEmail) }}" maxlength="255" autocomplete="email" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                @error('church_email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="church_address" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.church_address') }}</label>
                                <textarea id="church_address" name="church_address" rows="3" maxlength="500" autocomplete="street-address" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-4 py-3 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">{{ old('church_address', $churchAddress) }}</textarea>
                                @error('church_address')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-slate-800 pt-4">
                            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('settings.save_settings') }}</button>
                        </div>
                    </form>
                </section>

                <section id="logo-settings" class="scroll-mt-24 space-y-5 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-7">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ __('settings.logo_settings') }}</h3>
                        <p class="mt-1 text-sm text-slate-400">{{ __('settings.logo_description') }}</p>
                    </div>

                    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="section" value="logo">

                        @if ($churchLogoUrl)
                            <div class="inline-flex min-h-28 min-w-40 items-center justify-center rounded-xl border border-slate-700 bg-[#0b1120] p-4">
                                <img src="{{ $churchLogoUrl }}" alt="{{ __('settings.current_logo') }}" class="max-h-24 max-w-56 object-contain">
                            </div>
                        @else
                            <p class="text-sm text-slate-400">{{ __('settings.no_logo') }}</p>
                        @endif

                        <div>
                            <label for="church_logo" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.upload_logo') }}</label>
                            <input id="church_logo" name="church_logo" type="file" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-3 py-2.5 text-sm text-slate-300 file:mr-4 file:rounded-md file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white">
                            <p class="mt-1 text-xs text-slate-500">{{ __('settings.logo_file_help') }}</p>
                            @error('church_logo')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        @if ($churchLogoUrl)
                            <label class="flex items-center gap-2 text-sm text-slate-300">
                                <input type="checkbox" name="remove_church_logo" value="1" class="rounded border-slate-600 bg-[#0b1120] text-blue-500 focus:ring-blue-500">
                                {{ __('settings.remove_current_logo') }}
                            </label>
                        @endif

                        <div class="flex justify-end border-t border-slate-800 pt-4">
                            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('settings.save_logo') }}</button>
                        </div>
                    </form>
                </section>

                <section id="favicon-settings" class="scroll-mt-24 space-y-5 rounded-2xl border border-slate-800 bg-[#111a2e] p-5 sm:p-7">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ __('settings.favicon_settings') }}</h3>
                        <p class="mt-1 text-sm text-slate-400">{{ __('settings.favicon_description') }}</p>
                    </div>

                    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="section" value="favicon">

                        @if ($churchFaviconUrl)
                            <div class="inline-flex min-h-16 min-w-16 items-center justify-center rounded-xl border border-slate-700 bg-[#0b1120] p-3">
                                <img src="{{ $churchFaviconUrl }}" alt="{{ __('settings.current_favicon') }}" class="h-10 w-10 object-contain">
                            </div>
                        @else
                            <p class="text-sm text-slate-400">{{ __('settings.no_favicon') }}</p>
                        @endif

                        <div>
                            <label for="church_favicon" class="mb-2 block text-sm font-medium text-slate-300">{{ __('settings.upload_favicon') }}</label>
                            <input id="church_favicon" name="church_favicon" type="file" accept=".ico,.png" class="w-full rounded-lg border border-slate-700 bg-[#0b1120] px-3 py-2.5 text-sm text-slate-300 file:mr-4 file:rounded-md file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white">
                            <p class="mt-1 text-xs text-slate-500">{{ __('settings.favicon_file_help') }}</p>
                            @error('church_favicon')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        @if ($churchFaviconUrl)
                            <label class="flex items-center gap-2 text-sm text-slate-300">
                                <input type="checkbox" name="remove_church_favicon" value="1" class="rounded border-slate-600 bg-[#0b1120] text-blue-500 focus:ring-blue-500">
                                {{ __('settings.remove_current_favicon') }}
                            </label>
                        @endif

                        <div class="flex justify-end border-t border-slate-800 pt-4">
                            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">{{ __('settings.save_favicon') }}</button>
                        </div>
                    </form>
                </section>
            @endif

            @if ($canManageSmsSettings)
                @include('settings.partials.sms-notifications')
            @endif
        </div>
    </div>
</x-app-layout>
