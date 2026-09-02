<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level guard for a `module.widget.action` permission. Paired with the
 * frontend <PermissionGuard>, which hides the widget entirely rather than
 * rendering an empty shell.
 */
class EnsureWidgetPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        return ApiResponse::error(
            'You do not have access to this widget.',
            403,
            ['required_permission' => $permissions],
        );
    }
}
