<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} | {{ $systemName }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#0b1120] font-sans text-slate-200 antialiased">
        <div
            x-data="{ sidebarOpen: window.innerWidth >= 1024 }"
            @resize.window.debounce.150ms="sidebarOpen = window.innerWidth >= 1024"
            @keydown.escape.window="if (window.innerWidth < 1024) sidebarOpen = false"
            class="min-h-screen"
        >
            <a href="#main-content" class="skip-link">
                {{ __('dashboard.skip_to_content') }}
            </a>

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
                class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-800 bg-[#101827] transition-transform duration-300"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                :inert="!sidebarOpen"
                :aria-hidden="!sidebarOpen"
            >
                <div class="flex h-20 items-center justify-between border-b border-slate-800 px-6">
                    <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 shadow-lg shadow-blue-900/30">
                            <svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M12 3v18M5 9h14M8 21h8"/>
                            </svg>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-lg font-bold tracking-wide text-white">{{ $systemName }}</span>
                            <span class="block text-[10px] uppercase tracking-[2px] text-slate-500">{{ __('dashboard.brand_subtitle') }}</span>
                        </span>
                    </a>

                    <button
                        type="button"
                        @click="sidebarOpen = false"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 lg:hidden"
                        aria-label="{{ __('dashboard.close_menu') }}"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m18 6-12 12M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 space-y-7 overflow-y-auto px-4 py-7" aria-label="{{ __('dashboard.main_menu') }}">
                    <div>
                        <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[2px] text-slate-500">
                            {{ __('dashboard.main_menu') }}
                        </p>

                        <div class="space-y-1">
                            <a
                                href="{{ route('dashboard') }}"
                                @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                @class([
                                    'nav-link',
                                    'nav-link-active' => request()->routeIs('dashboard'),
                                ])
                                @if (request()->routeIs('dashboard')) aria-current="page" @endif
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <rect x="3" y="3" width="8" height="8" rx="1"/>
                                    <rect x="14" y="3" width="7" height="5" rx="1"/>
                                    <rect x="14" y="11" width="7" height="10" rx="1"/>
                                    <rect x="3" y="14" width="8" height="7" rx="1"/>
                                </svg>
                                {{ __('dashboard.dashboard') }}
                            </a>

                            @can('view members')
                                <a
                                    href="{{ route('members.index') }}"
                                    @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                    @class([
                                        'nav-link',
                                        'nav-link-active' => request()->routeIs('members.*'),
                                    ])
                                    @if (request()->routeIs('members.*')) aria-current="page" @endif
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="10" cy="7" r="4"/>
                                        <path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    {{ __('dashboard.members') }}
                                </a>
                            @endcan

                            @can('view departments')
                                <a
                                    href="{{ route('departments.index') }}"
                                    @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                    @class([
                                        'nav-link',
                                        'nav-link-active' => request()->routeIs('departments.*'),
                                    ])
                                    @if (request()->routeIs('departments.*')) aria-current="page" @endif
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h1m4 0h1m-6 4h1m4 0h1m-5 7v-4h4v4"/>
                                    </svg>
                                    {{ __('dashboard.departments') }}
                                </a>
                            @endcan

                            @can('view groups')
                                <a
                                    href="{{ route('groups.index') }}"
                                    @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                    @class([
                                        'nav-link',
                                        'nav-link-active' => request()->routeIs('groups.*'),
                                    ])
                                    @if (request()->routeIs('groups.*')) aria-current="page" @endif
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <circle cx="9" cy="8" r="3"/>
                                        <circle cx="17" cy="9" r="2.5"/>
                                        <path d="M3 20v-1a6 6 0 0 1 12 0v1M15 15a5 5 0 0 1 6 4.8V20"/>
                                    </svg>
                                    {{ __('dashboard.groups') }}
                                </a>
                            @endcan

                            @can('view finances')
                                <a
                                    href="{{ route('finance.index') }}"
                                    @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                    @class([
                                        'nav-link',
                                        'nav-link-active' => request()->routeIs('finance.index', 'finance.transactions.*', 'finance.envelopes.*', 'finance.imports.*', 'finance.categories.*', 'finance.campaigns.*', 'finance.receipts.*', 'finance.sms.*'),
                                    ])
                                    @if (request()->routeIs('finance.index', 'finance.transactions.*', 'finance.envelopes.*', 'finance.imports.*', 'finance.categories.*', 'finance.campaigns.*', 'finance.receipts.*', 'finance.sms.*')) aria-current="page" @endif
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                                        <path d="M3 10h18M7 15h3"/>
                                    </svg>
                                    {{ __('finance.navigation') }}
                                </a>
                            @endcan

                            @can('manage contributions')
                                    <a
                                        href="{{ route('finance.campaigns.index') }}"
                                        @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                        @class([
                                            'nav-link',
                                            'nav-link-active' => request()->routeIs('finance.campaigns.*'),
                                        ])
                                        @if (request()->routeIs('finance.campaigns.*')) aria-current="page" @endif
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M4 20h16M6 17l3-6 3 3 4-8 2 4"/>
                                            <circle cx="18" cy="6" r="2"/>
                                        </svg>
                                        {{ __('finance.campaigns_title') }}
                                    </a>
                            @endcan

                            @can('view receipts')
                                <a href="{{ route('finance.receipts.index') }}" @click="if (window.innerWidth < 1024) sidebarOpen = false" @class(['nav-link', 'nav-link-active' => request()->routeIs('finance.receipts.index', 'finance.receipts.show')]) @if (request()->routeIs('finance.receipts.index', 'finance.receipts.show')) aria-current="page" @endif>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/></svg>
                                    {{ __('finance.receipts_title') }}
                                </a>
                            @endcan

                            @can('manage receipts')
                                <a href="{{ route('finance.receipts.settings.edit') }}" @click="if (window.innerWidth < 1024) sidebarOpen = false" @class(['nav-link', 'nav-link-active' => request()->routeIs('finance.receipts.settings.*')]) @if (request()->routeIs('finance.receipts.settings.*')) aria-current="page" @endif>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16"/><circle cx="8" cy="5" r="2"/><circle cx="16" cy="12" r="2"/></svg>
                                    {{ __('finance.receipt_settings_title') }}
                                </a>
                            @endcan

                            @can('manage SMS settings')
                                <a href="{{ route('settings.edit') }}#sms-notifications" @click="if (window.innerWidth < 1024) sidebarOpen = false" @class(['nav-link', 'nav-link-active' => request()->routeIs('finance.sms.settings.*') || request()->routeIs('settings.*') && Auth::user()->can('manage SMS settings')]) @if (request()->routeIs('finance.sms.settings.*') || request()->routeIs('settings.*') && Auth::user()->can('manage SMS settings')) aria-current="page" @endif>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4h16v13H8l-4 3zM8 9h8M8 13h5"/></svg>
                                    {{ __('finance.sms_settings_title') }}
                                </a>
                            @endcan

                            @can('view SMS history')
                                <a href="{{ route('finance.sms.index') }}" @click="if (window.innerWidth < 1024) sidebarOpen = false" @class(['nav-link', 'nav-link-active' => request()->routeIs('finance.sms.index')]) @if (request()->routeIs('finance.sms.index')) aria-current="page" @endif>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h14v16l-4-2-3 2-3-2-4 2zM9 9h6M9 13h6"/></svg>
                                    {{ __('finance.sms_title') }}
                                </a>
                            @endcan

                            @can('view financial reports')
                                <a
                                    href="{{ route('finance.reports.index') }}"
                                    @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                    @class([
                                        'nav-link',
                                        'nav-link-active' => request()->routeIs('finance.reports.*'),
                                    ])
                                    @if (request()->routeIs('finance.reports.*')) aria-current="page" @endif
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path d="M4 19V5m0 14h16M8 16v-5m4 5V7m4 9v-3m4 3V4"/>
                                    </svg>
                                    {{ __('finance.reports_title') }}
                                </a>
                            @endcan

                            @can('manage financial settings')
                                <a
                                    href="{{ route('finance.settings.edit') }}"
                                    @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                    @class([
                                        'nav-link',
                                        'nav-link-active' => request()->routeIs('finance.settings.*'),
                                    ])
                                    @if (request()->routeIs('finance.settings.*')) aria-current="page" @endif
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="m19.4 15 .1.1a1.7 1.7 0 0 1-2.4 2.4l-.1-.1a1.7 1.7 0 0 0-2.9 1.2v.2a1.7 1.7 0 0 1-3.4 0v-.2a1.7 1.7 0 0 0-2.9-1.2l-.1.1a1.7 1.7 0 1 1-2.4-2.4l.1-.1a1.7 1.7 0 0 0-1.2-2.9H4a1.7 1.7 0 0 1 0-3.4h.2a1.7 1.7 0 0 0 1.2-2.9l-.1-.1a1.7 1.7 0 1 1 2.4-2.4l.1.1a1.7 1.7 0 0 0 2.9-1.2V2a1.7 1.7 0 0 1 3.4 0v.2a1.7 1.7 0 0 0 2.9 1.2l.1-.1a1.7 1.7 0 1 1 2.4 2.4l-.1.1a1.7 1.7 0 0 0 1.2 2.9h.2a1.7 1.7 0 0 1 0 3.4h-.2a1.7 1.7 0 0 0-1.2 2.9Z"/>
                                    </svg>
                                    {{ __('finance.settings_title') }}
                                </a>
                            @endcan
                        </div>
                    </div>

                    @if (Auth::user()->can('manage settings') || Auth::user()->can('manage SMS settings'))
                        <div>
                            <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[2px] text-slate-500">
                                {{ __('dashboard.administration') }}
                            </p>

                            <div class="space-y-1">
                                <a
                                    href="{{ route('settings.edit') }}"
                                    @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                    @class([
                                        'nav-link',
                                        'nav-link-active' => request()->routeIs('settings.*'),
                                    ])
                                    @if (request()->routeIs('settings.*')) aria-current="page" @endif
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <rect x="9" y="3" width="6" height="5" rx="1"/>
                                        <rect x="3" y="16" width="6" height="5" rx="1"/>
                                        <rect x="15" y="16" width="6" height="5" rx="1"/>
                                        <path d="M12 8v4M6 16v-4h12v4"/>
                                    </svg>
                                    {{ __('dashboard.settings') }}
                                </a>
                            </div>
                        </div>
                    @endif
                </nav>

                <div class="border-t border-slate-800 p-5">
                    @if ($churchName)
                        <p class="truncate text-xs font-semibold text-slate-200">{{ $churchName }}</p>
                    @endif
                    <p class="mt-1 truncate text-[10px] text-slate-500">{{ $systemName }} · {{ __('dashboard.version') }}</p>
                </div>
            </aside>

            <div class="min-h-screen lg:pl-72">
                <header class="sticky top-0 z-30 flex min-h-20 items-center justify-between gap-4 border-b border-slate-800 bg-[#101827]/95 px-4 py-3 backdrop-blur-xl sm:px-6 lg:px-9">
                    <div class="flex min-w-0 items-center gap-4">
                        <button
                            type="button"
                            @click="sidebarOpen = true"
                            class="rounded-lg border border-slate-700 p-2 text-slate-300 transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 lg:hidden"
                            aria-controls="app-sidebar"
                            :aria-expanded="sidebarOpen"
                            aria-label="{{ __('dashboard.open_menu') }}"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>

                        <div class="min-w-0">
                            <h1 class="truncate text-base font-bold text-white">{{ $pageTitle }}</h1>
                            @if ($churchName)
                                <p class="hidden truncate text-xs text-slate-500 sm:block">{{ $churchName }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                        <a
                            href="{{ route('profile.edit') }}"
                            @class([
                                'flex max-w-48 items-center gap-3 rounded-xl px-2 py-1.5 transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:px-3',
                                'bg-slate-800' => request()->routeIs('profile.*'),
                            ])
                            @if (request()->routeIs('profile.*')) aria-current="page" @endif
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-blue-500/30 bg-blue-600/20 text-sm font-bold text-blue-300" aria-hidden="true">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden min-w-0 text-right sm:block">
                                <span class="block truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</span>
                                <span class="block text-xs text-slate-500">{{ __('dashboard.profile') }}</span>
                            </span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                title="{{ __('dashboard.logout') }}"
                                aria-label="{{ __('dashboard.logout') }}"
                                class="rounded-lg border border-slate-700 p-2.5 text-slate-400 transition hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-400 focus:outline-none focus:ring-2 focus:ring-red-500"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                    <path d="m16 17 5-5-5-5M21 12H9"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </header>

                <main id="main-content" class="min-w-0 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <style>
            [x-cloak] {
                display: none !important;
            }

            .skip-link {
                position: absolute;
                z-index: 60;
                transform: translateY(-200%);
                border-radius: 0.5rem;
                background: #2563eb;
                padding: 0.5rem 1rem;
                color: white;
            }

            .skip-link:focus {
                position: fixed;
                left: 1rem;
                top: 1rem;
                transform: translateY(0);
            }

            .nav-link {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                border: 1px solid transparent;
                border-radius: 0.75rem;
                padding: 0.75rem 1rem;
                color: #94a3b8;
                font-size: 0.875rem;
                transition: background-color 0.2s, color 0.2s, border-color 0.2s;
            }

            .nav-link:hover {
                background: #1e293b;
                color: #fff;
            }

            .nav-link:focus-visible {
                outline: 2px solid #3b82f6;
                outline-offset: 2px;
            }

            .nav-link-active,
            .nav-link-active:hover {
                border-color: rgb(59 130 246 / 0.2);
                background: rgb(37 99 235 / 0.15);
                color: #60a5fa;
                font-weight: 600;
            }

            .nav-link svg {
                width: 1.25rem;
                height: 1.25rem;
                flex-shrink: 0;
            }
        </style>
    </body>
</html>
