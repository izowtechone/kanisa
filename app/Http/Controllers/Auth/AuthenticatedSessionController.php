<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginOtpChallenge;
use App\Models\LoginSecurityEvent;
use App\Models\User;
use App\Services\LoginOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();
        LoginSecurityEvent::record('password_verified', $user, $request);

        $request->session()->regenerate();

        if ($user->isSuperAdmin()) {
            return $this->completeLogin($request, $user, $request->boolean('remember'));
        }

        try {
            $challenge = app(LoginOtpService::class)->start($user, $request);
        } catch (\RuntimeException $exception) {
            LoginSecurityEvent::record('otp_unavailable', $user, $request);

            throw ValidationException::withMessages([
                'email' => $exception->getMessage(),
            ]);
        }

        $request->session()->put([
            'login_otp.challenge_id' => $challenge->challenge_id,
            'login_otp.user_id' => $user->id,
            'login_otp.remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('login.otp.show')->with('status', __('auth_pages.otp_sent'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $challengeId = $request->session()->pull('login_otp.challenge_id');

        if (is_string($challengeId)) {
            LoginSecurityEvent::record('otp_cancelled', null, $request);
            LoginOtpChallenge::query()
                ->where('challenge_id', $challengeId)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
        }

        $request->session()->forget(['login_otp.user_id', 'login_otp.remember', 'otp_verified_user_id']);
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::guard('web')->login($user, $remember);
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('otp_verified_user_id', $user->id);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
