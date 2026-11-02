<section>
    <header>
        <h3 class="text-lg font-semibold text-white">{{ __('profile.info_title') }}</h3>
        <p class="mt-1 text-sm text-slate-400">{{ __('profile.info_description') }}</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5" x-data="{ loading: false }" @submit="if (loading) { $event.preventDefault() } else { loading = true }">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="field-label">{{ __('profile.name') }}<span class="text-red-400" aria-hidden="true"> *</span></label>
            <input id="name" name="name" type="text" class="field-input" value="{{ old('name', $user->name) }}" required autocomplete="name">
            @error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="field-label">{{ __('profile.email') }}<span class="text-red-400" aria-hidden="true"> *</span></label>
            <input id="email" name="email" type="email" class="field-input" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <p class="mt-2 text-sm text-amber-300">
                    {{ __('profile.email_unverified') }}
                    <button form="send-verification" class="font-medium text-blue-400 underline hover:text-blue-300">{{ __('profile.resend_verification') }}</button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium text-emerald-300" role="status">{{ __('profile.verification_sent') }}</p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="btn-primary" :disabled="loading">{{ __('profile.save') }}</button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="text-sm text-emerald-300" role="status">{{ __('profile.saved') }}</p>
            @endif
        </div>
    </form>
</section>
