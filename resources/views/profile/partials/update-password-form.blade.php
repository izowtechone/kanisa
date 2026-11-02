<section>
    <header>
        <h3 class="text-lg font-semibold text-white">{{ __('profile.password_title') }}</h3>
        <p class="mt-1 text-sm text-slate-400">{{ __('profile.password_description') }}</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5" x-data="{ loading: false }" @submit="if (loading) { $event.preventDefault() } else { loading = true }">
        @csrf
        @method('put')

        @php($passwordErrors = $errors->updatePassword)

        <div>
            <label for="update_password_current_password" class="field-label">{{ __('profile.current_password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" class="field-input" autocomplete="current-password">
            @foreach ($passwordErrors->get('current_password') as $message)<p class="field-error" role="alert">{{ $message }}</p>@endforeach
        </div>

        <div>
            <label for="update_password_password" class="field-label">{{ __('profile.new_password') }}</label>
            <input id="update_password_password" name="password" type="password" class="field-input" autocomplete="new-password">
            <p class="field-hint">{{ __('auth_pages.password_rules') }}</p>
            @foreach ($passwordErrors->get('password') as $message)<p class="field-error" role="alert">{{ $message }}</p>@endforeach
        </div>

        <div>
            <label for="update_password_password_confirmation" class="field-label">{{ __('profile.confirm_password') }}</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="field-input" autocomplete="new-password">
            @foreach ($passwordErrors->get('password_confirmation') as $message)<p class="field-error" role="alert">{{ $message }}</p>@endforeach
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="btn-primary" :disabled="loading">{{ __('profile.save') }}</button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="text-sm text-emerald-300" role="status">{{ __('profile.saved') }}</p>
            @endif
        </div>
    </form>
</section>
