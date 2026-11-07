<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyLoginOtpRequest;
use App\Models\LoginOtpChallenge;
use App\Models\LoginSecurityEvent;
use App\Services\LoginOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginOtpController extends Controller
{
    public function show(Request $request, LoginOtpService $otp): View|RedirectResponse
    {
        $challengeId = $request->session()->get('login_otp.challenge_id');
        $challenge = is_string($challengeId) ? $otp->findPending($challengeId) : null;

        if ($challenge === null
            || $challenge->expires_at->isPast()
            || ! $otp->isPhoneVerifiedAndUsable($challenge->user)) {
            $this->clearPendingChallenge($request, $challengeId);

            return redirect()->route('login')->withErrors([
                'email' => __('auth_pages.otp_expired'),
            ]);
        }

        $phone = (string) $challenge->user->phone;
        $resendAt = $challenge->last_sent_at->addSeconds((int) config('login_security.otp_resend_cooldown_seconds'));

        return view('auth.verify-otp', [
            'maskedPhone' => $otp->maskPhone($phone),
            'expiresInSeconds' => max(0, now()->diffInSeconds($challenge->expires_at, false)),
            'resendInSeconds' => max(0, now()->diffInSeconds($resendAt, false)),
        ]);
    }

    public function verify(VerifyLoginOtpRequest $request, LoginOtpService $otp): RedirectResponse
    {
        $challengeId = (string) $request->session()->get('login_otp.challenge_id');
        $challenge = $otp->findPending($challengeId);

        if ($challenge === null) {
            $this->clearPendingChallenge($request, $challengeId);

            return redirect()->route('login')->withErrors([
                'email' => __('auth_pages.otp_expired'),
            ]);
        }

        $result = $otp->verify($challengeId, $request->string('code')->toString(), $request);

        if ($result !== 'verified') {
            if ($result === 'expired' || $result === 'locked') {
                $this->clearPendingChallenge($request, $challengeId);

                return redirect()->route('login')->withErrors([
                    'email' => $result === 'locked'
                        ? __('auth_pages.otp_locked')
                        : __('auth_pages.otp_expired'),
                ]);
            }

            return back()->withErrors(['code' => __('auth_pages.otp_invalid')]);
        }

        $user = $challenge->user;
        $remember = (bool) $request->session()->pull('login_otp.remember', false);
        $request->session()->forget(['login_otp.challenge_id', 'login_otp.user_id']);
        $user->forceFill(['last_login_at' => now()])->save();
        Auth::guard('web')->login($user, $remember);
        $request->session()->put('otp_verified_user_id', $user->id);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('status', __('auth_pages.otp_verified'));
    }

    public function resend(Request $request, LoginOtpService $otp): RedirectResponse
    {
        $challengeId = $request->session()->get('login_otp.challenge_id');

        if (! is_string($challengeId)) {
            return redirect()->route('login');
        }

        try {
            $otp->resend($challengeId, $request);
        } catch (\RuntimeException $exception) {
            return redirect()->route('login.otp.show')->withErrors(['code' => $exception->getMessage()]);
        }

        return redirect()->route('login.otp.show')->with('status', __('auth_pages.otp_sent'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $challengeId = $request->session()->pull('login_otp.challenge_id');

        if (is_string($challengeId)) {
            LoginOtpChallenge::query()
                ->where('challenge_id', $challengeId)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
            LoginSecurityEvent::record('otp_cancelled', null, $request);
        }

        $request->session()->forget(['login_otp.user_id', 'login_otp.remember']);
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function clearPendingChallenge(Request $request, mixed $challengeId): void
    {
        if (is_string($challengeId)) {
            LoginOtpChallenge::query()
                ->where('challenge_id', $challengeId)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
        }

        $request->session()->forget(['login_otp.challenge_id', 'login_otp.user_id', 'login_otp.remember']);
    }
}
