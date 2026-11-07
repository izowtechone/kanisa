<x-guest-layout>
    <div
        x-data="{ expiresIn: {{ $expiresInSeconds }}, resendIn: {{ $resendInSeconds }} }"
        x-init="const timer = setInterval(() => { if (expiresIn > 0) expiresIn--; if (resendIn > 0) resendIn--; if (expiresIn === 0 && resendIn === 0) clearInterval(timer); }, 1000)"
        class="space-y-6"
    >
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-400">{{ __('auth_pages.otp_eyebrow') }}</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-white">{{ __('auth_pages.otp_title') }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-400">
                {{ __('auth_pages.otp_description') }}
                <span class="font-semibold text-slate-200">{{ $maskedPhone }}</span>
            </p>
        </div>

        @if (session('status'))
            <div role="status" class="rounded-xl border border-emerald-800 bg-emerald-950/50 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-800 bg-red-950/60 p-4 text-sm text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-300">
            <p>{{ __('auth_pages.otp_expiration', ['minutes' => config('login_security.otp_expiration_minutes')]) }}</p>
            <p class="mt-2 font-semibold text-white" aria-live="polite">
                <span x-text="`${Math.floor(expiresIn / 60)}:${String(expiresIn % 60).padStart(2, '0')}`"></span>
            </p>
        </div>

        <form method="POST" action="{{ route('login.otp.verify') }}" class="space-y-5">
            @csrf
            <div>
                <label for="code" class="field-label">{{ __('auth_pages.otp_code') }}</label>
                <input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    minlength="6"
                    maxlength="6"
                    required
                    autofocus
                    class="field-input text-center text-2xl font-bold tracking-[0.5em]"
                    @if ($errors->has('code')) aria-invalid="true" aria-describedby="otp-error" @endif
                >
                @error('code')
                    <p id="otp-error" class="field-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary w-full py-3" x-bind:disabled="expiresIn === 0">
                {{ __('auth_pages.otp_verify') }}
            </button>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-800 pt-5">
            <form method="POST" action="{{ route('login.otp.resend') }}">
                @csrf
                <button type="submit" class="text-sm font-semibold text-blue-400 hover:text-blue-300 disabled:cursor-not-allowed disabled:opacity-40" x-bind:disabled="resendIn > 0 || expiresIn === 0">
                    {{ __('auth_pages.otp_resend') }}
                    <span x-show="resendIn > 0">(<span x-text="resendIn"></span>s)</span>
                </button>
            </form>
            <form method="POST" action="{{ route('login.otp.cancel') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-slate-400 hover:text-white">{{ __('auth_pages.otp_cancel') }}</button>
            </form>
        </div>
    </div>
</x-guest-layout>
