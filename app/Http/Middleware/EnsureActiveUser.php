<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();

            return $request->expectsJson()
                ? ApiResponse::error('This account has been disabled.', 403)
                : redirect()->route('login')->withErrors(['email' => 'This account has been disabled.']);
        }

        return $next($request);
    }
}
