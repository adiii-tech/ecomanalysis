<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The user's own account: identity, appearance, password, two-factor state,
 * the devices they are signed in on, and their API tokens.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::ok([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roleName(),
                'theme' => $user->theme,
                'accent_color' => $user->accent_color,
                'mfa_enabled' => $user->mfa_enabled,
                'must_change_password' => $user->must_change_password,
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'ai' => [
                'credits_used' => $user->ai_credits_used,
                'credit_limit' => $user->ai_credit_limit,
                'credits_remaining' => $user->aiCreditsRemaining(),
            ],
            'sessions' => $this->sessions($request),
            'tokens' => $user->tokens()->latest('id')->get()->map(static fn ($token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
            'accent_color' => ['sometimes', Rule::in(['indigo', 'emerald', 'rose', 'amber', 'cyan', 'violet'])],
        ]);

        $user->forceFill($validated)->save();

        activity('profile')->performedOn($user)->withProperties(array_keys($validated))->log('profile.updated');

        return ApiResponse::ok(null, message: 'Profile updated.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return ApiResponse::error('That is not your current password.', 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        // A password change should not leave old devices signed in.
        $this->flushOtherSessions($request);

        activity('profile')->performedOn($user)->log('profile.password_changed');

        return ApiResponse::ok(null, message: 'Password changed. Other devices have been signed out.');
    }

    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $token = $request->user()->createToken($validated['name']);

        activity('profile')->performedOn($request->user())
            ->withProperties(['token' => $validated['name']])
            ->log('profile.token_created');

        return ApiResponse::ok([
            // Shown once; it is not recoverable afterwards.
            'plain_text_token' => $token->plainTextToken,
            'id' => $token->accessToken->getKey(),
        ], message: 'Token created. Copy it now — it is not shown again.');
    }

    public function revokeToken(Request $request, int $token): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($token)->delete();

        if ($deleted === 0) {
            return ApiResponse::error('Token not found.', 404);
        }

        activity('profile')->performedOn($request->user())->log('profile.token_revoked');

        return ApiResponse::ok(null, message: 'Token revoked.');
    }

    public function signOutOtherDevices(Request $request): JsonResponse
    {
        $this->flushOtherSessions($request);

        activity('profile')->performedOn($request->user())->log('profile.sessions_cleared');

        return ApiResponse::ok(null, message: 'Signed out everywhere else.');
    }

    /** @return list<array<string, mixed>> */
    private function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get()
            ->map(fn (object $session): array => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'device' => $this->describeAgent((string) $session->user_agent),
                // A token-authenticated call has no session of its own, so
                // nothing is "current" from its point of view.
                'is_current' => $session->id === $this->currentSessionId($request),
                'last_active' => date('c', (int) $session->last_activity),
            ])
            ->all();
    }

    /**
     * Enough of the user-agent to recognise your own devices in the list. This
     * is deliberately coarse — a full UA parser is a dependency this screen
     * does not need.
     */
    private function describeAgent(string $userAgent): string
    {
        $platform = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Brave') => 'Brave',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };

        $label = trim(implode(' · ', array_filter([$platform, $browser])));

        return $label !== '' ? $label : 'Unknown device';
    }

    private function currentSessionId(Request $request): ?string
    {
        return $request->hasSession() ? $request->session()->getId() : null;
    }

    private function flushOtherSessions(Request $request): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $current = $this->currentSessionId($request);

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            // Called with an API token there is no session to keep, so every
            // browser session goes — which is what a password change should do.
            ->when($current !== null, fn ($query) => $query->where('id', '!=', $current))
            ->delete();
    }
}
