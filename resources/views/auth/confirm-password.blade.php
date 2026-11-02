<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-white">{{ __('auth_pages.confirm_title') }}</h1>
        <p class="mt-2 text-slate-400">{{ __('auth_pages.confirm_description') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" x-data="{ loading: false }" @submit="if (loading) { $event.preventDefault() } else { loading = true }" class="space-y-5">
        @csrf

        <x-password-field id="password" name="password" :label="__('auth_pages.password')"/>

        <button type="submit" class="btn-primary w-full py-3" :disabled="loading">{{ __('auth_pages.confirm') }}</button>
    </form>
</x-guest-layout>
