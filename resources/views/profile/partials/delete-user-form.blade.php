<section class="space-y-5" x-data="{ confirming: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
    <header>
        <h3 class="text-lg font-semibold text-white">{{ __('profile.delete_title') }}</h3>
        <p class="mt-1 text-sm text-slate-400">{{ __('profile.delete_description') }}</p>
    </header>

    <button type="button" class="btn-danger" @click="confirming = true">{{ __('profile.delete_button') }}</button>

    <div x-cloak x-show="confirming" class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-account-title" @keydown.escape.window="confirming = false">
        <div class="absolute inset-0 bg-black/70" @click="confirming = false"></div>
        <form method="post" action="{{ route('profile.destroy') }}" class="relative w-full max-w-md rounded-card border border-slate-700 bg-surface-raised p-6">
            @csrf
            @method('delete')

            <h3 id="delete-account-title" class="text-lg font-semibold text-white">{{ __('profile.delete_confirm_title') }}</h3>
            <p class="mt-2 text-sm text-slate-400">{{ __('profile.delete_confirm_description') }}</p>

            <div class="mt-5">
                <label for="delete_password" class="field-label">{{ __('auth_pages.password') }}</label>
                <input id="delete_password" name="password" type="password" class="field-input" autocomplete="current-password" required>
                @foreach ($errors->userDeletion->get('password') as $message)<p class="field-error" role="alert">{{ $message }}</p>@endforeach
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="btn-secondary" @click="confirming = false">{{ __('profile.cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('profile.delete_button') }}</button>
            </div>
        </form>
    </div>
</section>
