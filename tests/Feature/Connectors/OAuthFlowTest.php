<?php

declare(strict_types=1);

use App\Domain\Connectors\Support\OAuthState;
use App\Enums\ConnectorStatus;
use App\Models\Connector;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.shopify.client_id' => 'shopify-client',
        'services.shopify.client_secret' => 'shopify-secret',
        'services.meta.client_id' => 'meta-app',
        'services.meta.client_secret' => 'meta-secret',
    ]);

    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant, [
        'connectors.index.view', 'connectors.credentials.manage', 'connectors.sync_health.manage',
    ]);
});

function shopifyHmac(array $query, string $secret = 'shopify-secret'): string
{
    unset($query['hmac']);
    ksort($query);

    return hash_hmac('sha256', urldecode(http_build_query($query)), $secret);
}

it('sends the merchant to Shopify with the right scopes and a state', function (): void {
    $response = $this->actingAs($this->user)
        ->get('/connectors/oauth/shopify/redirect?shop_domain=kaira.myshopify.com');

    $response->assertRedirect();
    $target = $response->headers->get('Location');

    expect($target)->toStartWith('https://kaira.myshopify.com/admin/oauth/authorize')
        ->and($target)->toContain('client_id=shopify-client')
        ->and($target)->toContain('read_orders')
        ->and($target)->toContain('state=');

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
    expect($query['redirect_uri'])->toEndWith('/connectors/oauth/shopify/callback');
});

it('refuses to redirect when the OAuth app is not configured', function (): void {
    config(['services.meta.client_id' => null]);

    $this->actingAs($this->user)
        ->get('/connectors/oauth/meta/redirect')
        ->assertRedirect('/connectors')
        ->assertSessionHas('error');
});

it('will not start a Shopify redirect without the shop domain', function (): void {
    $this->actingAs($this->user)
        ->get('/connectors/oauth/shopify/redirect')
        ->assertRedirect('/connectors')
        ->assertSessionHas('error');
});

it('completes the Shopify callback and registers webhooks', function (): void {
    Http::fake([
        '*/admin/oauth/access_token' => Http::response(['access_token' => 'shpat_live', 'scope' => 'read_orders']),
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']]),
        '*/webhooks.json' => Http::response(['webhooks' => []]),
    ]);

    $state = OAuthState::issue($this->tenant->id, $this->user->id, 'shopify', ['shop_domain' => 'kaira.myshopify.com']);
    $query = ['code' => 'auth-code', 'shop' => 'kaira.myshopify.com', 'state' => $state, 'timestamp' => '1788000000'];
    $query['hmac'] = shopifyHmac($query);

    $this->get('/connectors/oauth/shopify/callback?'.http_build_query($query))
        ->assertRedirect('/connectors?connected=shopify');

    $connector = Connector::query()->withoutGlobalScopes()->where('connector_id', 'shopify')->firstOrFail();

    expect($connector->status)->toBe(ConnectorStatus::Connected)
        ->and($connector->account_label)->toBe('Kaira Living')
        ->and($connector->credentials['access_token'])->toBe('shpat_live')
        ->and($connector->credentials['shop_domain'])->toBe('kaira.myshopify.com')
        // Stored encrypted, never in the clear.
        ->and($connector->getRawOriginal('credentials'))->not->toContain('shpat_live');

    // Webhooks registered so orders arrive live.
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'webhooks.json') && $request->method() === 'POST');
});

it('rejects a Shopify callback whose HMAC does not verify', function (): void {
    Http::fake();

    $state = OAuthState::issue($this->tenant->id, $this->user->id, 'shopify', ['shop_domain' => 'kaira.myshopify.com']);

    $this->get('/connectors/oauth/shopify/callback?'.http_build_query([
        'code' => 'auth-code', 'shop' => 'kaira.myshopify.com', 'state' => $state, 'hmac' => 'forged',
    ]))->assertRedirect('/connectors')->assertSessionHas('error');

    expect(Connector::query()->withoutGlobalScopes()->first()->status)->toBe(ConnectorStatus::Error);
});

it('rejects a callback with an unknown state', function (): void {
    $this->get('/connectors/oauth/shopify/callback?code=x&state=never-issued')
        ->assertRedirect('/connectors')
        ->assertSessionHas('error');

    expect(Connector::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('will not let a state be replayed', function (): void {
    Http::fake([
        '*/admin/oauth/access_token' => Http::response(['access_token' => 'shpat_live']),
        '*/shop.json' => Http::response(['shop' => ['name' => 'Kaira Living']]),
        '*/webhooks.json' => Http::response(['webhooks' => []]),
    ]);

    $state = OAuthState::issue($this->tenant->id, $this->user->id, 'shopify', ['shop_domain' => 'kaira.myshopify.com']);
    $query = ['code' => 'auth-code', 'shop' => 'kaira.myshopify.com', 'state' => $state];
    $query['hmac'] = shopifyHmac($query);
    $url = '/connectors/oauth/shopify/callback?'.http_build_query($query);

    $this->get($url)->assertRedirect('/connectors?connected=shopify');
    $this->get($url)->assertRedirect('/connectors')->assertSessionHas('error');
});

it('holds Meta in needs_setup until an ad account is chosen', function (): void {
    Http::fake([
        'https://graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'meta-token', 'expires_in' => 5184000]),
        'https://graph.facebook.com/*/me?*' => Http::response(['name' => 'Kaira Business']),
        'https://graph.facebook.com/*/me*' => Http::response(['name' => 'Kaira Business']),
    ]);

    $state = OAuthState::issue($this->tenant->id, $this->user->id, 'meta');

    $this->get('/connectors/oauth/meta/callback?code=abc&state='.$state)
        ->assertRedirect('/connectors?connected=meta');

    $connector = Connector::query()->withoutGlobalScopes()->where('connector_id', 'meta')->firstOrFail();

    // A token alone is not enough — we do not know which ad account is theirs.
    expect($connector->status)->toBe(ConnectorStatus::NeedsSetup)
        ->and($connector->credentials['access_token'])->toBe('meta-token')
        ->and($connector->credentials['token_expires_at'])->not->toBeNull();
});

it('lists the ad accounts the token can see, then connects once one is picked', function (): void {
    Http::fake([
        'https://graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'meta-token', 'expires_in' => 5184000]),
        'https://graph.facebook.com/*/me/adaccounts*' => Http::response(['data' => [
            ['id' => 'act_111', 'name' => 'Kaira Prospecting', 'currency' => 'INR', 'account_status' => 1],
            ['id' => 'act_222', 'name' => 'Old Account', 'currency' => 'INR', 'account_status' => 2],
        ]]),
        'https://graph.facebook.com/*/me/accounts*' => Http::response(['data' => [
            ['id' => '555', 'name' => 'Kaira Living', 'instagram_business_account' => ['id' => '999', 'username' => 'kairaliving']],
        ]]),
        'https://graph.facebook.com/*' => Http::response(['name' => 'Kaira Business', 'data' => []]),
    ]);

    $state = OAuthState::issue($this->tenant->id, $this->user->id, 'meta');
    $this->get('/connectors/oauth/meta/callback?code=abc&state='.$state);

    $options = $this->actingAs($this->user)->getJson('/api/connectors/meta/resources/ad_account_id');
    $options->assertOk()
        ->assertJsonPath('data.label', 'Ad account')
        ->assertJsonPath('data.options.0.id', 'act_111')
        ->assertJsonPath('data.options.0.label', 'Kaira Prospecting');

    $ig = $this->actingAs($this->user)->getJson('/api/connectors/meta/resources/ig_user_id');
    $ig->assertOk()->assertJsonPath('data.options.0.label', '@kairaliving');

    // One selection outstanding still leaves it in needs_setup.
    $this->actingAs($this->user)
        ->postJson('/api/connectors/meta/select', ['ad_account_id' => 'act_111'])
        ->assertOk()
        ->assertJsonPath('data.status', 'needs_setup');

    $this->actingAs($this->user)
        ->postJson('/api/connectors/meta/select', ['ig_user_id' => '999', 'page_id' => '555'])
        ->assertOk()
        ->assertJsonPath('data.status', 'connected');

    $connector = Connector::query()->withoutGlobalScopes()->where('connector_id', 'meta')->firstOrFail();
    expect($connector->credentials['ad_account_id'])->toBe('act_111')
        ->and($connector->status)->toBe(ConnectorStatus::Connected);
});

it('ignores selection keys the connector did not ask for', function (): void {
    Http::fake([
        'https://graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'meta-token']),
        'https://graph.facebook.com/*' => Http::response(['name' => 'Kaira Business']),
    ]);

    $state = OAuthState::issue($this->tenant->id, $this->user->id, 'meta');
    $this->get('/connectors/oauth/meta/callback?code=abc&state='.$state);

    $this->actingAs($this->user)
        ->postJson('/api/connectors/meta/select', ['access_token' => 'attacker-token', 'ad_account_id' => 'act_111'])
        ->assertOk();

    $connector = Connector::query()->withoutGlobalScopes()->where('connector_id', 'meta')->firstOrFail();

    // The token must not be overwritable through the selection endpoint.
    expect($connector->credentials['access_token'])->toBe('meta-token');
});

it('exposes oauth capability and outstanding selections to the UI', function (): void {
    $response = $this->actingAs($this->user)->getJson('/api/connectors');
    $response->assertOk();

    $shopify = collect($response->json('data.live'))->firstWhere('id', 'shopify');
    $judgeme = collect($response->json('data.live'))->firstWhere('id', 'judgeme');

    expect($shopify['is_oauth'])->toBeTrue()
        ->and($shopify['oauth_configured'])->toBeTrue()
        ->and($shopify['pre_auth_fields'])->toHaveKey('shop_domain')
        ->and($judgeme['is_oauth'])->toBeFalse();
});
