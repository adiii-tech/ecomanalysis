<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's tenant into the request. Every scoped query
 * and the spatie team-scoped permission lookup read from here.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->tenant_id !== null) {
            $tenant = $user->tenant()->first();

            $this->context->set($tenant);
            setPermissionsTeamId($user->tenant_id);

            if ($tenant !== null) {
                config(['app.timezone_display' => $tenant->timezone]);
            }
        }

        return $next($request);
    }
}
