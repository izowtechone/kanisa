<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-white">{{ __('auth_pages.welcome_heading') }}</h1>
        <p class="mt-2 text-slate-400">{{ __('auth_pages.welcome_subheading') }}</p>
    </div>

    @if (session('status'))
        <div class="alert-success mb-5" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" x-data="{ loading: false }" @submit="if (loading) { $event.preventDefault() } else { loading = true }" class="space-y-5" novalidate>
        @csrf

        <div>
            <label for="email" class="field-label">{{ __('auth_pages.email') }}<span class="text-red-400" aria-hidden="true"> *</span></label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                class="field-input"
                required
                autofocus
                autocomplete="username"
                @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif
            >
            @error('email')
                <p id="email-error" class="field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <x-password-field id="password" name="password" :label="__('auth_pages.password')"/>

        <div class="flex items-center justify-between gap-4">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate-300">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-600 bg-slate-900 text-blue-600 focus:ring-blue-500">
                {{ __('auth_pages.remember_me') }}
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-blue-400 hover:text-blue-300">
                    {{ __('auth_pages.forgot_password') }}
                </a>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full py-3" :disabled="loading">
            <span x-show="! loading">{{ __('auth_pages.login') }}</span>
            <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
                {{ __('auth_pages.logging_in') }}
            </span>
        </button>
    </form>

    <p class="mt-8 flex items-start gap-2 text-xs text-slate-500">
        <x-icon name="lock" class="mt-0.5 h-4 w-4 shrink-0"/>
        {{ __('auth_pages.secure_notice') }}
    </p>
</x-guest-layout>
