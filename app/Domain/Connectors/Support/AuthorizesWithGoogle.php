<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Support;

use App\Domain\Connectors\DTOs\ConnectionResult;
use Illuminate\Support\Facades\Http;

/**
 * The Google half of the OAuth dance, shared by GA4 and Google Ads.
 *
 * `access_type=offline` with `prompt=consent` is what makes Google return a
 * refresh token — without both, a re-authorising user gets an access token that
 * expires in an hour and no way to renew it.
 */
trait AuthorizesWithGoogle
{
    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function preAuthFields(): array
    {
        return [];
    }

    /** @param array<string, mixed> $context */
    public function authorizationUrl(string $state, string $redirectUri, array $context = []): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->oauthScopes()),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $context
     */
    public function exchangeCode(array $query, string $redirectUri, array $context = []): ConnectionResult
    {
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'code' => (string) ($query['code'] ?? ''),
        ]);

        if ($response->failed()) {
            return ConnectionResult::failure('Google refused the authorisation code: '.($response->json('error_description') ?? 'unknown error'));
        }

        $refreshToken = $response->json('refresh_token');

        if (blank($refreshToken)) {
            return ConnectionResult::failure(
                'Google did not return a refresh token. Remove this app at myaccount.google.com/permissions and authorise again so it re-prompts for offline access.',
            );
        }

        $profile = Http::withToken((string) $response->json('access_token'))
            ->timeout(20)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        return ConnectionResult::success((string) ($profile->json('email') ?? 'Google account'), [
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'refresh_token' => (string) $refreshToken,
            'authorised_email' => $profile->json('email'),
        ]);
    }

    public function afterConnect(): void
    {
        //
    }

    protected function googleAccessToken(): string
    {
        return app(GoogleOAuth::class)->accessToken(
            (string) $this->credential('client_id', config('services.google.client_id')),
            (string) $this->credential('client_secret', config('services.google.client_secret')),
            (string) $this->credential('refresh_token'),
        );
    }
}
