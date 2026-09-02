<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => true,
            'demoCredentials' => app()->isLocal()
                ? ['email' => 'owner@kairaliving.test', 'password' => 'password']
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $throttleKey = str($credentials['email'])->lower()->append('|'.$request->ip())->toString();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$this->seconds($throttleKey)} seconds.",
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Auth::validate(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => 'Those credentials do not match our records.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'This account has been disabled. Ask an admin to re-enable it.']);
        }

        RateLimiter::clear($throttleKey);

        // MFA-enabled accounts get a challenge before the session is established.
        if ($user->mfa_enabled) {
            $request->session()->put('mfa.user_id', $user->id);
            $request->session()->put('mfa.remember', (bool) ($credentials['remember'] ?? false));

            return redirect()->route('mfa.challenge');
        }

        Auth::login($user, (bool) ($credentials['remember'] ?? false));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended($user->must_change_password ? route('password.change') : route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function seconds(string $key): int
    {
        return RateLimiter::availableIn($key);
    }
}
