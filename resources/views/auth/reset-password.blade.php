<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-white">{{ __('auth_pages.reset_title') }}</h1>
        <p class="mt-2 text-slate-400">{{ __('auth_pages.reset_description') }}</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" x-data="{ loading: false }" @submit="if (loading) { $event.preventDefault() } else { loading = true }" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="field-label">{{ __('auth_pages.email') }}<span class="text-red-400" aria-hidden="true"> *</span></label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" class="field-input" required autofocus autocomplete="username">
            @error('email')
                <p class="field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <x-password-field id="password" name="password" :label="__('auth_pages.new_password')" autocomplete="new-password" :hint="__('auth_pages.password_rules')"/>
        <x-password-field id="password_confirmation" name="password_confirmation" :label="__('auth_pages.confirm_password')" autocomplete="new-password"/>

        <button type="submit" class="btn-primary w-full py-3" :disabled="loading">
            <span x-show="! loading">{{ __('auth_pages.reset_password') }}</span>
            <span x-show="loading" x-cloak>{{ __('auth_pages.saving') }}</span>
        </button>
    </form>
</x-guest-layout>
