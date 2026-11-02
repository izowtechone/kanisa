<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-white">{{ __('auth_pages.verify_title') }}</h1>
        <p class="mt-2 text-slate-400">{{ __('auth_pages.verify_description') }}</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert-success mb-5" role="status">{{ __('auth_pages.verification_sent') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}" x-data="{ loading: false }" @submit="if (loading) { $event.preventDefault() } else { loading = true }">
            @csrf
            <button type="submit" class="btn-primary" :disabled="loading">{{ __('auth_pages.resend_verification') }}</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm font-medium text-slate-400 hover:text-white">{{ __('auth_pages.logout') }}</button>
        </form>
    </div>
</x-guest-layout>
