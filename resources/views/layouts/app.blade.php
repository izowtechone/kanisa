<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} | {{ $systemName }}</title>

        @if ($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}" @if ($faviconType) type="{{ $faviconType }}" @endif>
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

        <style>{!! $brandCss !!}</style>
        <x-pwa-head />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-surface-base font-sans text-slate-200 antialiased">
        <div
            x-data="{ sidebarOpen: window.innerWidth >= 1024 }"
            @resize.window.debounce.150ms="sidebarOpen = window.innerWidth >= 1024"
            @keydown.escape.window="if (window.innerWidth < 1024) sidebarOpen = false"
            class="min-h-screen"
        >
            <x-page-loader />
            <a href="#main-content" class="skip-link">{{ __('dashboard.skip_to_content') }}</a>

            <div
                x-cloak
                x-show="sidebarOpen && window.innerWidth < 1024"
                x-transition.opacity
                @click="sidebarOpen = false"
                class="fixed inset-0 z-40 bg-black/70 lg:hidden"
                aria-hidden="true"
            ></div>

            <aside
                id="app-sidebar"
                class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-800 bg-surface-raised transition-transform duration-300"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                :inert="!sidebarOpen"
                :aria-hidden="!sidebarOpen"
            >
                <div class="flex h-20 items-center justify-between border-b border-slate-800 px-5">
                    <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 rounded-lg">
                        @if ($logoUrl && $showSidebarLogo)
                            <img src="{{ $logoUrl }}" alt="" class="h-11 w-11 shrink-0 rounded-xl bg-white/5 object-contain p-1">
                        @else
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white">
                                <x-icon name="church" class="h-6 w-6"/>
                            </span>
                        @endif
                        <span class="min-w-0">
                            <span class="block truncate text-base font-bold tracking-wide text-white">{{ $systemName }}</span>
                            <span class="block truncate text-[10px] uppercase tracking-[0.18em] text-slate-500">{{ __('dashboard.brand_subtitle') }}</span>
                        </span>
                    </a>

                    <button
                        type="button"
                        @click="sidebarOpen = false"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white lg:hidden"
                        aria-label="{{ __('dashboard.close_menu') }}"
                    >
                        <x-icon name="x" class="h-6 w-6"/>
                    </button>
                </div>

                <nav class="flex-1 space-y-2 overflow-y-auto px-4 py-5" aria-label="{{ __('navigation.main_menu') }}">
                    @foreach ($navigation as $group)
                        <div
                            x-data="{ open: {{ $group['active'] || $group['key'] === 'dashboard' ? 'true' : 'JSON.parse(localStorage.getItem(\'nav-'.$group['key'].'\') ?? \'false\')' }} }"
                            x-init="$watch('open', value => localStorage.setItem('nav-{{ $group['key'] }}', JSON.stringify(value)))"
                        >
                            @if ($group['key'] === 'dashboard')
                                @foreach ($group['items'] as $item)
                                    <a
                                        href="{{ $item['url'] }}"
                                        @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                        @class(['nav-link', 'nav-link-active' => $item['active']])
                                        @if ($item['active']) aria-current="page" @endif
                                    >
                                        <x-icon :name="$item['icon']"/>
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            @else
                                <button
                                    type="button"
                                    class="nav-group-button"
                                    @click="open = ! open"
                                    :aria-expanded="open"
                                    aria-controls="nav-group-{{ $group['key'] }}"
                                    aria-label="{{ __('navigation.toggle_group') }}: {{ $group['label'] }}"
                                >
                                    <span>{{ $group['label'] }}</span>
                                    <x-icon name="chevron-down" class="h-4 w-4 transition-transform" ::class="open ? '' : '-rotate-90'"/>
                                </button>

                                <div id="nav-group-{{ $group['key'] }}" x-show="open" x-cloak class="mt-1 space-y-1">
                                    @foreach ($group['items'] as $item)
                                        <a
                                            href="{{ $item['url'] }}"
                                            @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                            @class(['nav-link', 'nav-link-active' => $item['active']])
                                            @if ($item['active']) aria-current="page" @endif
                                        >
                                            <x-icon :name="$item['icon']"/>
                                            {{ $item['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </nav>

                <div class="border-t border-slate-800 p-4">
                    <x-pwa-install />
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-control p-2 transition hover:bg-slate-800">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-blue-500/30 bg-blue-600/20 text-sm font-bold text-blue-300" aria-hidden="true">
                            {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ Auth::user()->getRoleNames()->first() ?? Auth::user()->email }}</span>
                        </span>
                    </a>
                </div>
            </aside>

            <div class="min-h-screen lg:pl-72">
                <header class="sticky top-0 z-30 flex min-h-16 items-center justify-between gap-4 border-b border-slate-800 bg-surface-raised/95 px-4 py-2.5 backdrop-blur-xl sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            @click="sidebarOpen = true"
                            class="rounded-lg border border-slate-700 p-2 text-slate-300 transition hover:bg-slate-800 lg:hidden"
                            aria-controls="app-sidebar"
                            :aria-expanded="sidebarOpen"
                            aria-label="{{ __('dashboard.open_menu') }}"
                        >
                            <x-icon name="menu"/>
                        </button>

                        <nav class="min-w-0" aria-label="{{ __('navigation.breadcrumb') }}">
                            <ol class="flex min-w-0 items-center gap-1.5 text-sm">
                                @unless (request()->routeIs('dashboard'))
                                    <li class="hidden items-center gap-1.5 sm:flex">
                                        <a href="{{ route('dashboard') }}" class="text-slate-400 hover:text-white">{{ __('navigation.home') }}</a>
                                        <x-icon name="chevron-right" class="h-4 w-4 text-slate-600"/>
                                    </li>
                                @endunless
                                <li class="min-w-0 truncate font-semibold text-white" aria-current="page">{{ $pageTitle }}</li>
                            </ol>
                        </nav>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <div x-data="{ open: false }" class="relative" @keydown.escape="open = false" @click.outside="open = false">
                            <button
                                type="button"
                                @click="open = ! open"
                                :aria-expanded="open"
                                aria-haspopup="true"
                                class="flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 transition hover:bg-slate-800"
                                aria-label="{{ __('navigation.change_language') }}"
                            >
                                <x-icon name="globe" class="h-4 w-4"/>
                                <span class="hidden uppercase sm:inline">{{ app()->getLocale() }}</span>
                            </button>
                            <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-44 rounded-control border border-slate-700 bg-surface-raised p-1 shadow-xl">
                                @foreach (['sw', 'en'] as $locale)
                                    <a
                                        href="{{ route('language.switch', $locale) }}"
                                        @class(['flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-slate-800', 'font-semibold text-blue-400' => app()->getLocale() === $locale, 'text-slate-300' => app()->getLocale() !== $locale])
                                        hreflang="{{ $locale }}"
                                    >
                                        {{ __('navigation.language_'.$locale) }}
                                        @if (app()->getLocale() === $locale)
                                            <x-icon name="check-circle" class="h-4 w-4"/>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <div x-data="{ open: false }" class="relative" @keydown.escape="open = false" @click.outside="open = false">
                            <button
                                type="button"
                                @click="open = ! open"
                                :aria-expanded="open"
                                aria-haspopup="true"
                                aria-label="{{ __('navigation.user_menu') }}"
                                class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-slate-800"
                            >
                                <span class="flex h-9 w-9 items-center justify-center rounded-full border border-blue-500/30 bg-blue-600/20 text-sm font-bold text-blue-300" aria-hidden="true">
                                    {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                                </span>
                                <span class="hidden max-w-36 truncate text-sm font-semibold text-white md:block">{{ Auth::user()->name }}</span>
                                <x-icon name="chevron-down" class="hidden h-4 w-4 text-slate-500 md:block"/>
                            </button>
                            <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-60 rounded-control border border-slate-700 bg-surface-raised p-1 shadow-xl">
                                <div class="border-b border-slate-800 px-3 py-2">
                                    <p class="truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ Auth::user()->email }}</p>
                                </div>
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                                    <x-icon name="user" class="h-4 w-4"/> {{ __('navigation.my_profile') }}
                                </a>
                                <a href="{{ route('profile.edit') }}#password" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                                    <x-icon name="key" class="h-4 w-4"/> {{ __('navigation.change_password') }}
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-300 hover:bg-red-500/10">
                                        <x-icon name="log-out" class="h-4 w-4"/> {{ __('navigation.logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <main id="main-content" class="min-w-0 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
