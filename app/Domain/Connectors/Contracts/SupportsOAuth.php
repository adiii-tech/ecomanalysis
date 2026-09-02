<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Contracts;

use App\Domain\Connectors\DTOs\ConnectionResult;

/**
 * A connector that authorises through an OAuth redirect rather than a pasted
 * token.
 *
 * Most providers hand back a token that is not yet usable on its own — Meta
 * still needs an ad account, Google an analytics property. `pendingSelections()`
 * declares what the user must still choose, and the connector stays in
 * `needs_setup` until they have.
 */
interface SupportsOAuth
{
    /** @return list<string> */
    public function oauthScopes(): array;

    /**
     * Where to send the browser to start authorisation.
     *
     * @param  array<string, mixed>  $context  values collected before redirect (e.g. the Shopify shop domain)
     */
    public function authorizationUrl(string $state, string $redirectUri, array $context = []): string;

    /**
     * Turn the callback into stored credentials.
     *
     * @param  array<string, mixed>  $query  the full callback query string
     * @param  array<string, mixed>  $context
     */
    public function exchangeCode(array $query, string $redirectUri, array $context = []): ConnectionResult;

    /**
     * Fields the user must pick before this connector can sync.
     *
     * @return array<string, string> credential key => human label
     */
    public function pendingSelections(): array;

    /**
     * Options for one pending selection, read from the provider with the token
     * we just obtained.
     *
     * @return list<array{id: string, label: string, meta?: string}>
     */
    public function availableResources(string $key): array;

    /**
     * Anything to do once the connector is fully configured — registering
     * webhooks, for instance.
     */
    public function afterConnect(): void;

    /**
     * Values the user must supply before the redirect (Shopify needs the shop
     * domain, since its OAuth endpoint lives on the shop itself).
     *
     * @return array<string, array{label: string, type: string, required: bool, help?: string}>
     */
    public function preAuthFields(): array;
}
