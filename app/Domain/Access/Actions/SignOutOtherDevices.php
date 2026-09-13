<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Drops a user's browser sessions, optionally keeping the one asking.
 *
 * A password change that leaves old devices signed in has not really changed
 * anything, so this runs whenever a password is set — by the user themselves or
 * by an admin handing over a new one.
 */
class SignOutOtherDevices
{
    public function handle(User $user, ?string $keepSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
