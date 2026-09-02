<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Connectors\Queries\SyncHealthQuery;
use App\Models\Channel;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roleName(),
                    'accent_color' => $user->accent_color,
                    'theme' => $user->theme,
                    'is_demo' => $user->is_demo,
                    'permissions_version' => $user->permissions_version,
                    'ai_credits_used' => $user->ai_credits_used,
                    'ai_credit_limit' => $user->ai_credit_limit,
                    'ai_credits_remaining' => $user->aiCreditsRemaining(),
                    'mfa_enabled' => $user->mfa_enabled,
                ],
                'permissions' => $user?->flatPermissions() ?? [],
            ],
            'tenant' => $tenant === null ? null : [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'currency' => $tenant->currency,
                'timezone' => $tenant->timezone,
                'plan' => $tenant->plan->value,
                'is_demo' => $tenant->is_demo,
                'onboarding_state' => $tenant->onboarding_state,
            ],
            'channels' => fn () => $user === null ? [] : Channel::query()
                ->where('is_active', true)
                ->orderBy('type')
                ->get(['id', 'name', 'code', 'type', 'color']),
            'syncHealth' => fn () => $user === null ? null : app(SyncHealthQuery::class)->summary(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
