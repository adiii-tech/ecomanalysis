<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers;

use App\Domain\Connectors\Contracts\SupportsOAuth;
use App\Domain\Connectors\DTOs\ConnectionResult;
use App\Domain\Connectors\DTOs\HealthResult;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Domain\Connectors\Support\AbstractConnector;
use App\Domain\Sales\Actions\UpsertShopifyOrder;
use App\Enums\AuthType;
use App\Enums\PaymentMode;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shopify Admin REST connector. Uses `updated_at_min` cursors plus Link-header
 * page_info pagination so every sync is incremental and idempotent.
 */
class ShopifyConnector extends AbstractConnector implements SupportsOAuth
{
    private const API_VERSION = '2025-01';

    public function id(): string
    {
        return 'shopify';
    }

    public function label(): string
    {
        return 'Shopify';
    }

    public function summary(): string
    {
        return 'Your D2C store: orders, line items, customers, products, inventory, refunds, transactions, payouts, discount codes and abandoned checkouts.';
    }

    public function authType(): AuthType
    {
        return AuthType::OAuth;
    }

    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function credentialFields(): array
    {
        return [
            'shop_domain' => ['label' => 'Shop domain', 'type' => 'text', 'required' => true, 'help' => 'yourbrand.myshopify.com'],
            'access_token' => ['label' => 'Admin API access token', 'type' => 'password', 'required' => true, 'help' => 'Custom app token starting with shpat_'],
            'webhook_secret' => ['label' => 'Webhook signing secret', 'type' => 'password', 'required' => false],
        ];
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['products', 'inventory', 'customers', 'orders', 'refunds', 'transactions', 'discount_codes', 'abandoned_checkouts'];
    }

    /** @return array<string, int> */
    public function syncCadence(): array
    {
        return [
            'orders' => 15,
            'refunds' => 30,
            'transactions' => 60,
            'inventory' => 30,
            'products' => 360,
            'customers' => 120,
            'discount_codes' => 720,
            'abandoned_checkouts' => 60,
        ];
    }

    /** @return list<string> */
    public function webhookTopics(): array
    {
        return ['orders/create', 'orders/updated', 'orders/cancelled', 'refunds/create', 'inventory_levels/update'];
    }

    /** @return list<string> */
    public function oauthScopes(): array
    {
        return [
            'read_orders', 'read_all_orders', 'read_products', 'read_customers',
            'read_inventory', 'read_locations', 'read_price_rules', 'read_discounts',
            'read_checkouts', 'read_returns', 'read_fulfillments',
        ];
    }

    /**
     * Shopify authorises on the merchant's own domain, so the shop has to be
     * known before we can send them anywhere.
     *
     * @return array<string, array{label: string, type: string, required: bool, help?: string}>
     */
    public function preAuthFields(): array
    {
        return [
            'shop_domain' => [
                'label' => 'Shop domain',
                'type' => 'text',
                'required' => true,
                'help' => 'yourbrand.myshopify.com',
            ],
        ];
    }

    /** @param array<string, mixed> $context */
    public function authorizationUrl(string $state, string $redirectUri, array $context = []): string
    {
        $shop = $this->normaliseDomain((string) ($context['shop_domain'] ?? ''));

        return "https://{$shop}/admin/oauth/authorize?".http_build_query([
            'client_id' => (string) config('services.shopify.client_id'),
            'scope' => implode(',', $this->oauthScopes()),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $context
     */
    public function exchangeCode(array $query, string $redirectUri, array $context = []): ConnectionResult
    {
        $shop = $this->normaliseDomain((string) ($query['shop'] ?? $context['shop_domain'] ?? ''));

        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*\.myshopify\.com$/', $shop)) {
            return ConnectionResult::failure('That does not look like a Shopify shop domain.');
        }

        // Shopify signs the callback; an unsigned or mis-signed one is forged.
        if (! $this->callbackIsAuthentic($query)) {
            return ConnectionResult::failure('Shopify callback failed signature verification.');
        }

        $response = Http::asForm()->timeout(20)->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => (string) config('services.shopify.client_id'),
            'client_secret' => (string) config('services.shopify.client_secret'),
            'code' => (string) ($query['code'] ?? ''),
        ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            return ConnectionResult::failure('Shopify refused the authorisation code.');
        }

        $token = (string) $response->json('access_token');
        $shopName = $this->http("https://{$shop}/admin/api/".self::API_VERSION.'/', ['X-Shopify-Access-Token' => $token])
            ->get('shop.json')
            ->json('shop.name');

        return ConnectionResult::success((string) ($shopName ?? $shop), [
            'shop_domain' => $shop,
            'access_token' => $token,
            'scope' => $response->json('scope'),
            // Shopify signs webhooks with the app secret, so the receiver can
            // verify them without any extra setup from the merchant.
            'webhook_secret' => (string) config('services.shopify.client_secret'),
        ]);
    }

    /** @return array<string, string> */
    public function pendingSelections(): array
    {
        // A Shopify token is scoped to one shop; nothing left to choose.
        return [];
    }

    /** @return list<array{id: string, label: string, meta?: string}> */
    public function availableResources(string $key): array
    {
        return [];
    }

    /**
     * Register the webhooks we know how to handle, so orders arrive live
     * instead of waiting for the next scheduled sync. Existing subscriptions
     * are replaced rather than duplicated.
     */
    public function afterConnect(): void
    {
        $client = $this->clientFromModel();
        $callback = route('webhooks.shopify');

        $existing = collect($client->get('webhooks.json')->json('webhooks', []))
            ->filter(fn (array $hook): bool => ($hook['address'] ?? '') === $callback)
            ->keyBy('topic');

        foreach ($this->webhookTopics() as $topic) {
            if ($existing->has($topic)) {
                continue;
            }

            $client->post('webhooks.json', [
                'webhook' => ['topic' => $topic, 'address' => $callback, 'format' => 'json'],
            ]);
        }
    }

    /**
     * Shopify HMACs the callback query with the app secret.
     *
     * @param  array<string, mixed>  $query
     */
    private function callbackIsAuthentic(array $query): bool
    {
        $secret = (string) config('services.shopify.client_secret');
        $provided = (string) ($query['hmac'] ?? '');

        if ($secret === '' || $provided === '') {
            return false;
        }

        unset($query['hmac'], $query['signature']);
        ksort($query);

        $message = urldecode(http_build_query($query));

        return hash_equals(hash_hmac('sha256', $message, $secret), $provided);
    }

    private function normaliseDomain(string $domain): string
    {
        return strtolower(trim(str_replace(['https://', 'http://', '/'], '', $domain)));
    }

    /** @param array<string, mixed> $credentials */
    public function connect(array $credentials): ConnectionResult
    {
        $domain = trim((string) ($credentials['shop_domain'] ?? ''));
        $token = trim((string) ($credentials['access_token'] ?? ''));

        if ($domain === '' || $token === '') {
            return ConnectionResult::failure('Shop domain and access token are both required.');
        }

        $domain = str_replace(['https://', 'http://', '/'], '', $domain);

        try {
            $response = $this->client($domain, $token)->get('shop.json');
        } catch (Throwable $e) {
            return ConnectionResult::failure('Could not reach Shopify: '.$e->getMessage());
        }

        if ($response->failed()) {
            return ConnectionResult::failure('Shopify rejected the credentials ('.$response->status().').');
        }

        $shop = $response->json('shop', []);

        return ConnectionResult::success(
            (string) ($shop['name'] ?? $domain),
            ['shop_domain' => $domain, 'access_token' => $token, 'webhook_secret' => $credentials['webhook_secret'] ?? null],
            ['currency' => $shop['currency'] ?? 'INR', 'timezone' => $shop['iana_timezone'] ?? 'Asia/Kolkata'],
        );
    }

    public function testConnection(): HealthResult
    {
        $start = hrtime(true);

        try {
            $response = $this->clientFromModel()->get('shop.json');
        } catch (Throwable $e) {
            return HealthResult::fail('Shopify unreachable: '.$e->getMessage());
        }

        $latency = (int) ((hrtime(true) - $start) / 1_000_000);

        if ($response->failed()) {
            return HealthResult::fail('Shopify returned HTTP '.$response->status().'.');
        }

        return HealthResult::ok('Connected to '.($response->json('shop.name') ?? 'Shopify').'.', [
            'plan' => $response->json('shop.plan_name'),
            'currency' => $response->json('shop.currency'),
        ], $latency);
    }

    protected function syncProducts(SyncContext $ctx): SyncReport
    {
        $since = $ctx->cursor !== null ? Carbon::parse((string) $ctx->cursor) : $ctx->sinceOrDefault(3650);
        $fetched = 0;
        $upserted = 0;
        $latest = $since;

        foreach ($this->paginate('products.json', ['updated_at_min' => $since->toIso8601String(), 'limit' => $ctx->pageLimit], 'products') as $product) {
            $fetched++;
            $latest = max($latest, Carbon::parse($product['updated_at']));

            $productModel = Product::query()->updateOrCreate(
                ['tenant_id' => $ctx->tenant->id, 'source' => 'shopify', 'external_id' => (string) $product['id']],
                [
                    'title' => $product['title'] ?? 'Untitled',
                    'handle' => $product['handle'] ?? null,
                    'category' => $product['product_type'] ?: null,
                    'brand' => $product['vendor'] ?: null,
                    'product_type' => $product['product_type'] ?? null,
                    'status' => $product['status'] ?? 'active',
                    'image_url' => Arr::get($product, 'image.src'),
                    'tags' => $product['tags'] ? explode(', ', (string) $product['tags']) : [],
                    'published_at' => $this->utc($product['published_at'] ?? null),
                ],
            );

            foreach ($product['variants'] ?? [] as $variant) {
                Sku::query()->updateOrCreate(
                    ['tenant_id' => $ctx->tenant->id, 'source' => 'shopify', 'external_id' => (string) $variant['id']],
                    [
                        'product_id' => $productModel->id,
                        'sku_code' => $variant['sku'] ?: 'SHOPIFY-'.$variant['id'],
                        'name' => $product['title'].($variant['title'] !== 'Default Title' ? ' — '.$variant['title'] : ''),
                        'variant_title' => $variant['title'] ?? null,
                        'category' => $productModel->category,
                        'brand' => $productModel->brand,
                        'mrp' => $this->paise($variant['compare_at_price'] ?? $variant['price']),
                        'selling_price' => $this->paise($variant['price']),
                        'weight_grams' => (int) ($variant['grams'] ?? 0),
                        'barcode' => $variant['barcode'] ?? null,
                        'image_url' => $productModel->image_url,
                        'is_active' => ($product['status'] ?? 'active') === 'active',
                    ],
                );
                $upserted++;
            }
        }

        return SyncReport::of('products', $fetched, $upserted, $latest->toIso8601String());
    }

    protected function syncCustomers(SyncContext $ctx): SyncReport
    {
        $since = $ctx->cursor !== null ? Carbon::parse((string) $ctx->cursor) : $ctx->sinceOrDefault(3650);
        $fetched = 0;
        $latest = $since;

        foreach ($this->paginate('customers.json', ['updated_at_min' => $since->toIso8601String(), 'limit' => $ctx->pageLimit], 'customers') as $customer) {
            $fetched++;
            $latest = max($latest, Carbon::parse($customer['updated_at']));
            $address = $customer['default_address'] ?? [];

            Customer::query()->updateOrCreate(
                ['tenant_id' => $ctx->tenant->id, 'source' => 'shopify', 'external_id' => (string) $customer['id']],
                [
                    'email_hash' => Customer::hashIdentity($customer['email'] ?? null),
                    'masked_email' => Customer::maskEmail($customer['email'] ?? null),
                    'email_encrypted' => $customer['email'] ?? null,
                    'phone_hash' => Customer::hashIdentity($customer['phone'] ?? null),
                    'masked_phone' => Customer::maskPhone($customer['phone'] ?? null),
                    'phone_encrypted' => $customer['phone'] ?? null,
                    'name' => trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? '')) ?: null,
                    'city' => $address['city'] ?? null,
                    'state' => $address['province'] ?? null,
                    'pincode' => $address['zip'] ?? null,
                    'accepts_marketing' => (bool) ($customer['accepts_marketing'] ?? false),
                ],
            );
        }

        return SyncReport::of('customers', $fetched, $fetched, $latest->toIso8601String());
    }

    /**
     * When the shop opened — the natural start for a full order history backfill.
     */
    public function shopCreatedAt(): ?CarbonImmutable
    {
        $createdAt = $this->clientFromModel()->get('shop.json')->json('shop.created_at');

        return is_string($createdAt) ? CarbonImmutable::parse($createdAt) : null;
    }

    /**
     * Order ids with the gateways that paid for them, asked for with a trimmed
     * field list so a whole-store pass costs minutes rather than a full re-sync.
     *
     * @return iterable<int, array{id: string, gateways: list<string>}>
     */
    public function paymentGateways(?CarbonImmutable $since = null): iterable
    {
        $params = [
            'status' => 'any',
            'limit' => 250,
            'fields' => 'id,payment_gateway_names',
            ...($since !== null ? ['created_at_min' => $since->toIso8601String()] : []),
        ];

        foreach ($this->paginate('orders.json', $params, 'orders') as $order) {
            yield ['id' => (string) $order['id'], 'gateways' => $order['payment_gateway_names'] ?? []];
        }
    }

    protected function syncOrders(SyncContext $ctx): SyncReport
    {
        $since = $ctx->cursor !== null ? Carbon::parse((string) $ctx->cursor) : $ctx->sinceOrDefault(90);
        $upsert = app(UpsertShopifyOrder::class);
        $fetched = 0;
        $upserted = 0;
        $latest = $since;

        // A backfill window walks history by when orders were placed; the
        // incremental sync follows whatever changed since the cursor.
        $window = $ctx->until !== null
            ? ['created_at_min' => $since->toIso8601String(), 'created_at_max' => $ctx->until->toIso8601String()]
            : ['updated_at_min' => $since->toIso8601String()];

        $params = [...$window, 'status' => 'any', 'limit' => $ctx->pageLimit];

        foreach ($this->paginate('orders.json', $params, 'orders') as $order) {
            $fetched++;
            $latest = max($latest, Carbon::parse($order['updated_at']));
            $upsert->handle($ctx->tenant, $order);
            $upserted++;
        }

        return SyncReport::of('orders', $fetched, $upserted, $latest->toIso8601String());
    }

    protected function syncRefunds(SyncContext $ctx): SyncReport
    {
        // Refunds arrive nested on the order payload; syncing orders covers them.
        return SyncReport::empty('refunds', $ctx->cursor);
    }

    /**
     * Transactions are not embedded in the orders list, so they need one call
     * per order. Only orders touched since the last run are re-read, and the
     * gateway fee they carry is what makes payment-gateway cost real rather
     * than an assumed percentage.
     */
    protected function syncTransactions(SyncContext $ctx): SyncReport
    {
        $since = $ctx->cursor !== null ? Carbon::parse((string) $ctx->cursor) : $ctx->sinceOrDefault(14);

        $orders = Order::query()
            ->where('tenant_id', $ctx->tenant->id)
            ->where('source', 'shopify')
            ->where('payment_mode', PaymentMode::Prepaid)
            ->where('updated_at', '>=', $since)
            ->orderBy('placed_at')
            ->limit(500)
            ->get(['id', 'external_id']);

        $fetched = 0;
        $upserted = 0;

        foreach ($orders as $order) {
            $response = $this->clientFromModel()->get("orders/{$order->external_id}/transactions.json");

            if ($response->failed()) {
                continue;
            }

            foreach ($response->json('transactions', []) as $txn) {
                $fetched++;

                Transaction::query()->updateOrCreate(
                    ['tenant_id' => $ctx->tenant->id, 'source' => 'shopify', 'external_id' => (string) $txn['id']],
                    [
                        'order_id' => $order->id,
                        'gateway' => $txn['gateway'] ?? null,
                        'method' => Arr::get($txn, 'payment_details.credit_card_company') ?? ($txn['gateway'] ?? null),
                        'kind' => $txn['kind'] ?? 'sale',
                        'amount' => $this->paise($txn['amount'] ?? 0),
                        'fee' => $this->paise(Arr::get($txn, 'receipt.fee', 0)),
                        'status' => $txn['status'] ?? 'success',
                        'failure_reason' => $txn['error_code'] ?? null,
                        'processed_at' => $this->utc($txn['processed_at'] ?? $txn['created_at']),
                    ],
                );

                $upserted++;
            }
        }

        return SyncReport::of('transactions', $fetched, $upserted, now()->toIso8601String());
    }

    protected function syncInventory(SyncContext $ctx): SyncReport
    {
        $skus = Sku::query()
            ->where('tenant_id', $ctx->tenant->id)
            ->where('source', 'shopify')
            ->whereNotNull('external_id')
            ->pluck('id', 'external_id');

        $fetched = 0;
        $rows = [];

        // Variant stock rides along on products.json, so one paged pass over
        // products is both correct and cheaper than per-variant lookups.
        foreach ($this->paginate('products.json', ['limit' => $ctx->pageLimit], 'products') as $product) {
            foreach ($product['variants'] ?? [] as $variant) {
                $skuId = $skus[(string) $variant['id']] ?? null;

                if ($skuId === null) {
                    continue;
                }

                $available = (int) ($variant['inventory_quantity'] ?? 0);
                $fetched++;

                $rows[] = [
                    'tenant_id' => $ctx->tenant->id,
                    'sku_id' => $skuId,
                    'location_id' => null,
                    'source' => 'shopify',
                    'on_hand' => $available,
                    'reserved' => 0,
                    'available' => $available,
                    'synced_at' => now(),
                ];
            }
        }

        $upserted = $this->upsertChannelStock($ctx->tenant->id, 'shopify', $rows);

        return SyncReport::of('inventory', $fetched, $upserted, now()->toIso8601String());
    }

    protected function syncDiscountCodes(SyncContext $ctx): SyncReport
    {
        $fetched = 0;
        $rows = [];

        foreach ($this->paginate('price_rules.json', ['limit' => 250], 'price_rules') as $rule) {
            $codes = $this->clientFromModel()->get("price_rules/{$rule['id']}/discount_codes.json")->json('discount_codes', []);

            foreach ($codes as $code) {
                $fetched++;
                $rows[] = [
                    'tenant_id' => $ctx->tenant->id,
                    'source' => 'shopify',
                    'external_id' => (string) $code['id'],
                    'code' => $code['code'],
                    'type' => $rule['value_type'] === 'percentage' ? 'percentage' : 'fixed',
                    'value' => (int) round(abs((float) $rule['value']) * ($rule['value_type'] === 'percentage' ? 1 : 100)),
                    'starts_at' => $this->utc($rule['starts_at'] ?? null),
                    'ends_at' => $this->utc($rule['ends_at'] ?? null),
                ];
            }
        }

        $upserted = $this->upsert('discount_codes', $rows, ['tenant_id', 'code'], ['type', 'value', 'starts_at', 'ends_at', 'external_id', 'updated_at']);

        return SyncReport::of('discount_codes', $fetched, $upserted, now()->toIso8601String());
    }

    protected function syncAbandonedCheckouts(SyncContext $ctx): SyncReport
    {
        $since = $ctx->cursor !== null ? Carbon::parse((string) $ctx->cursor) : $ctx->sinceOrDefault(30);
        $fetched = 0;
        $rows = [];
        $latest = $since;

        foreach ($this->paginate('checkouts.json', ['updated_at_min' => $since->toIso8601String(), 'limit' => $ctx->pageLimit], 'checkouts') as $checkout) {
            $fetched++;
            $latest = max($latest, Carbon::parse($checkout['updated_at']));

            $rows[] = [
                'tenant_id' => $ctx->tenant->id,
                'source' => 'shopify',
                'external_id' => (string) $checkout['id'],
                'abandoned_at' => $this->utc($checkout['created_at']),
                'cart_value' => $this->paise($checkout['total_price'] ?? 0),
                'items_count' => count($checkout['line_items'] ?? []),
                'recovered' => ! empty($checkout['completed_at']),
                'recovered_at' => $this->utc($checkout['completed_at'] ?? null),
                'recovery_url' => $checkout['abandoned_checkout_url'] ?? null,
            ];
        }

        $upserted = $this->upsert('abandoned_checkouts', $rows,
            ['tenant_id', 'source', 'external_id'],
            ['abandoned_at', 'cart_value', 'items_count', 'recovered', 'recovered_at', 'recovery_url', 'updated_at'],
        );

        return SyncReport::of('abandoned_checkouts', $fetched, $upserted, $latest->toIso8601String());
    }

    /**
     * Cursor-based pagination following Shopify's Link header.
     *
     * @param  array<string, mixed>  $params
     * @return iterable<int, array<string, mixed>>
     */
    private function paginate(string $path, array $params, string $key): iterable
    {
        $client = $this->clientFromModel();
        $pageInfo = null;
        $guard = 0;

        do {
            $query = $pageInfo === null ? $params : ['limit' => $params['limit'] ?? 250, 'page_info' => $pageInfo];
            $response = $client->get($path, $query);

            if ($response->failed()) {
                return;
            }

            yield from $response->json($key, []);

            $pageInfo = $this->nextPageInfo($response->header('Link'));
            $guard++;
        } while ($pageInfo !== null && $guard < 500);
    }

    private function nextPageInfo(?string $linkHeader): ?string
    {
        if ($linkHeader === null || ! str_contains($linkHeader, 'rel="next"')) {
            return null;
        }

        foreach (explode(',', $linkHeader) as $link) {
            if (! str_contains($link, 'rel="next"')) {
                continue;
            }

            if (preg_match('/page_info=([^>&;"]+)/', $link, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function client(string $domain, string $token): PendingRequest
    {
        return $this->http("https://{$domain}/admin/api/".self::API_VERSION.'/', [
            'X-Shopify-Access-Token' => $token,
        ]);
    }

    private function clientFromModel(): PendingRequest
    {
        return $this->client(
            (string) $this->credential('shop_domain'),
            (string) $this->credential('access_token'),
        );
    }

    /** Shopify times carry the shop's offset; stored as they come they would read 5½ hours late. */
    private function utc(?string $timestamp): ?CarbonImmutable
    {
        return blank($timestamp) ? null : CarbonImmutable::parse($timestamp)->utc();
    }

    private function paise(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
