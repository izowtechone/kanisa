<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-white">{{ __('auth_pages.forgot_title') }}</h1>
        <p class="mt-2 text-slate-400">{{ __('auth_pages.forgot_description') }}</p>
    </div>

    @if (session('status'))
        <div class="alert-success mb-5" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" x-data="{ loading: false }" @submit="if (loading) { $event.preventDefault() } else { loading = true }" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="field-label">{{ __('auth_pages.email') }}<span class="text-red-400" aria-hidden="true"> *</span></label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="field-input" required autofocus autocomplete="username">
            @error('email')
                <p class="field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-primary w-full py-3" :disabled="loading">
            <span x-show="! loading">{{ __('auth_pages.send_reset_link') }}</span>
            <span x-show="loading" x-cloak>{{ __('auth_pages.sending') }}</span>
        </button>
    </form>

    <a href="{{ route('login') }}" class="mt-6 inline-flex text-sm font-medium text-blue-400 hover:text-blue-300">
        &larr; {{ __('auth_pages.back_to_login') }}
    </a>
</x-guest-layout>
