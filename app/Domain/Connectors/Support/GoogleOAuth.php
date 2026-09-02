<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Exchanges a refresh token for a short-lived access token, cached until just
 * before expiry so every Google call does not pay the round trip.
 */
class GoogleOAuth
{
    public function accessToken(string $clientId, string $clientSecret, string $refreshToken): string
    {
        $cacheKey = 'google_oauth:'.hash('sha256', $clientId.$refreshToken);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($clientId, $clientSecret, $refreshToken): string {
            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Google OAuth refresh failed: '.($response->json('error_description') ?? $response->status()));
            }

            return (string) $response->json('access_token');
        });
    }
}
