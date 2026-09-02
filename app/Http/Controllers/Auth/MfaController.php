<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Services\TotpService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MfaController extends Controller
{
    public function __construct(private readonly TotpService $totp) {}

    public function challenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('mfa.user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/mfa-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'min:6', 'max:19']]);

        $userId = $request->session()->get('mfa.user_id');

        if ($userId === null) {
            return redirect()->route('login');
        }

        $throttleKey = 'mfa:'.$userId.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again shortly.']);
        }

        $user = User::query()->findOrFail($userId);
        $code = (string) $request->string('code');

        $recoveryCodes = $user->mfa_recovery_codes ?? [];
        $usedRecovery = in_array(strtoupper(trim($code)), $recoveryCodes, true);

        if (! $usedRecovery && ! $this->totp->verify((string) $user->mfa_secret, $code)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        if ($usedRecovery) {
            $user->mfa_recovery_codes = array_values(array_diff($recoveryCodes, [strtoupper(trim($code))]));
            $user->save();
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) $request->session()->pull('mfa.remember', false);
        $request->session()->forget('mfa.user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    /** Re-issues the challenge screen; TOTP has nothing to resend, so we say so. */
    public function resend(Request $request): RedirectResponse
    {
        return back()->with('success', 'Open your authenticator app for the current code — TOTP codes rotate every 30 seconds and are not sent by email or SMS.');
    }

    public function setup(Request $request): Response
    {
        $user = $request->user();
        $secret = $user->mfa_secret ?? $this->totp->generateSecret();

        if ($user->mfa_secret === null) {
            $user->forceFill(['mfa_secret' => $secret])->save();
        }

        return Inertia::render('auth/mfa-setup', [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $user->email, config('app.name')),
            'enabled' => $user->mfa_enabled,
            'recoveryCodes' => $user->mfa_enabled ? $user->mfa_recovery_codes : null,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = $request->user();

        if (! $this->totp->verify((string) $user->mfa_secret, (string) $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Check your authenticator app.']);
        }

        $user->forceFill([
            'mfa_enabled' => true,
            'mfa_confirmed_at' => now(),
            'mfa_recovery_codes' => $this->totp->generateRecoveryCodes(),
        ])->save();

        return back()->with('success', 'Two-factor authentication is on. Save your recovery codes somewhere safe.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_confirmed_at' => null,
            'mfa_recovery_codes' => null,
        ])->save();

        return back()->with('success', 'Two-factor authentication turned off.');
    }
}
