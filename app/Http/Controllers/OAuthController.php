<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Connectors\Actions\CompleteOAuth;
use App\Domain\Connectors\ConnectorRegistry;
use App\Domain\Connectors\Contracts\SupportsOAuth;
use App\Domain\Connectors\Support\OAuthState;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The OAuth round trip.
 *
 * These are browser redirects rather than API calls, so they live on the web
 * routes and rely on the session for auth — except the callback, which is
 * authorised by the single-use `state` it carries, because the provider may
 * return the user in a context where the session cookie is not sent.
 */
class OAuthController extends Controller
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
        private readonly TenantContext $context,
    ) {}

    public function redirect(Request $request, string $connector): RedirectResponse
    {
        $driver = $this->registry->has($connector) ? $this->registry->make($connector) : null;

        if (! $driver instanceof SupportsOAuth) {
            return redirect('/connectors')->with('error', 'That connector does not authorise by redirect.');
        }

        if (blank($this->clientId($connector))) {
            return redirect('/connectors')->with(
                'error',
                sprintf('%s is not configured on this server yet — an admin needs to add its OAuth client id and secret.', $driver->label()),
            );
        }

        $user = $request->user();

        // Whatever the connector needed before redirecting (Shopify's shop domain)
        // rides along in the state, so the callback can still see it.
        $preAuth = [];
        foreach (array_keys($driver->preAuthFields()) as $field) {
            $preAuth[$field] = $request->string($field)->toString();
        }

        $missing = array_keys(array_filter(
            $driver->preAuthFields(),
            fn (array $config, string $field): bool => ($config['required'] ?? false) && blank($preAuth[$field] ?? null),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($missing !== []) {
            return redirect('/connectors')->with('error', 'Missing required field: '.implode(', ', $missing).'.');
        }

        $state = OAuthState::issue($user->tenant_id, $user->id, $connector, $preAuth);

        return redirect()->away($driver->authorizationUrl($state, $this->callbackUrl($connector), $preAuth));
    }

    public function callback(Request $request, string $connector, CompleteOAuth $complete): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect('/connectors')->with(
                'error',
                'Authorisation was declined: '.($request->string('error_description')->toString() ?: $request->string('error')->toString()),
            );
        }

        $payload = OAuthState::consume($request->string('state')->toString());

        if ($payload === null || $payload['connector_id'] !== $connector) {
            return redirect('/connectors')->with('error', 'That authorisation link has expired or was already used. Start again.');
        }

        $tenant = Tenant::query()->find($payload['tenant_id']);

        if ($tenant === null) {
            return redirect('/connectors')->with('error', 'The tenant this authorisation belonged to no longer exists.');
        }

        $result = $this->context->runAs($tenant, fn () => $complete->handle(
            $tenant,
            $connector,
            $request->query(),
            $this->callbackUrl($connector),
            $payload['context'],
        ));

        return $result->ok
            ? redirect('/connectors?connected='.$connector)->with('success', ($result->accountLabel ?? 'Account').' authorised.')
            : redirect('/connectors')->with('error', $result->error ?? 'Could not complete authorisation.');
    }

    private function callbackUrl(string $connector): string
    {
        return url("/connectors/oauth/{$connector}/callback");
    }

    private function clientId(string $connector): ?string
    {
        return match ($connector) {
            'shopify' => config('services.shopify.client_id'),
            'meta' => config('services.meta.client_id'),
            'google_ads', 'ga4' => config('services.google.client_id'),
            default => null,
        };
    }
}
