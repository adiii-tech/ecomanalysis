<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The `state` parameter that protects the OAuth round trip from CSRF.
 *
 * The value is opaque and single-use: it is minted before the redirect, stored
 * server-side against the tenant and connector it belongs to, and consumed on
 * the way back. A callback carrying an unknown or already-used state is
 * rejected, so an attacker cannot bolt their own account onto someone's tenant.
 */
final class OAuthState
{
    private const PREFIX = 'oauth-state:';

    private const TTL_MINUTES = 15;

    /** @param array<string, mixed> $context */
    public static function issue(int $tenantId, int $userId, string $connectorId, array $context = []): string
    {
        $state = Str::random(48);

        Cache::put(self::PREFIX.$state, [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'connector_id' => $connectorId,
            'context' => $context,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $state;
    }

    /**
     * Consume the state, returning what it was issued for. Null means the state
     * is unknown, expired, or already used.
     *
     * @return array{tenant_id: int, user_id: int, connector_id: string, context: array<string, mixed>}|null
     */
    public static function consume(string $state): ?array
    {
        $key = self::PREFIX.$state;
        $payload = Cache::get($key);

        // Single use: pull it regardless of what happens next.
        Cache::forget($key);

        return is_array($payload) ? $payload : null;
    }
}
