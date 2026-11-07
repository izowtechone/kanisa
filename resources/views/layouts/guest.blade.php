<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $systemName }}</title>

        @if ($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

        <style>{!! $brandCss !!}</style>
        <x-pwa-head />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-surface-base font-sans text-slate-200 antialiased">
        <x-page-loader />
        <div class="grid min-h-screen lg:grid-cols-2">
            <aside class="relative hidden flex-col justify-between overflow-hidden border-r border-slate-800 bg-surface-raised p-12 lg:flex">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-600/10 via-transparent to-transparent" aria-hidden="true"></div>

                <div class="relative flex items-center gap-3">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="h-12 w-12 rounded-xl bg-white/5 object-contain p-1">
                    @else
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600 text-white">
                            <x-icon name="church" class="h-7 w-7"/>
                        </span>
                    @endif
                    <div>
                        <p class="text-lg font-bold text-white">{{ $systemName }}</p>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">{{ __('auth_pages.tagline') }}</p>
                    </div>
                </div>

                <div class="relative space-y-6">
                    @if ($churchName)
                        <h2 class="text-3xl font-bold leading-tight text-white">{{ $churchName }}</h2>
                    @endif
                    <ul class="space-y-4 text-slate-300">
                        @foreach (['feature_members', 'feature_finance', 'feature_communication'] as $feature)
                            <li class="flex items-start gap-3">
                                <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-blue-400"/>
                                <span>{{ __('auth_pages.'.$feature) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="relative text-xs text-slate-500">&copy; {{ now()->year }} {{ $churchName ?? $systemName }}</p>
            </aside>

            <main class="flex min-h-screen flex-col px-5 py-6 sm:px-10">
                <div class="flex justify-end">
                    <div class="flex items-center gap-1 rounded-lg border border-slate-700 p-1 text-xs" role="group" aria-label="{{ __('navigation.change_language') }}">
                        @foreach (['sw', 'en'] as $locale)
                            <a
                                href="{{ route('language.switch', $locale) }}"
                                hreflang="{{ $locale }}"
                                @class(['rounded-md px-3 py-1.5 font-semibold uppercase', 'bg-blue-600 text-white' => app()->getLocale() === $locale, 'text-slate-400 hover:text-white' => app()->getLocale() !== $locale])
                                @if (app()->getLocale() === $locale) aria-current="true" @endif
                            >{{ $locale }}</a>
                        @endforeach
                    </div>
                </div>

                <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="" class="h-11 w-11 rounded-xl bg-white/5 object-contain p-1">
                        @else
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-white">
                                <x-icon name="church" class="h-6 w-6"/>
                            </span>
                        @endif
                        <p class="text-lg font-bold text-white">{{ $systemName }}</p>
                    </div>

                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
