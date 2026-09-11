<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers;

use App\Domain\Connectors\DTOs\ConnectionResult;
use App\Domain\Connectors\DTOs\HealthResult;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Domain\Connectors\Support\AbstractConnector;
use App\Domain\Marketplace\Actions\UpsertMarketplaceOrder;
use App\Enums\AuthType;
use App\Models\Channel;
use App\Models\Sku;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Unicommerce (Uniware) — the marketplace side of the business: sale orders
 * across Amazon/Flipkart/Myntra/etc, shipments, returns and channel inventory.
 */
class UnicommerceConnector extends AbstractConnector
{
    public function id(): string
    {
        return 'unicommerce';
    }

    public function label(): string
    {
        return 'Unicommerce';
    }

    public function summary(): string
    {
        return 'Marketplace sale orders, shipments, returns and Uniware inventory with channel mapping across Amazon, Flipkart, Myntra, Meesho and more.';
    }

    public function authType(): AuthType
    {
        return AuthType::Token;
    }

    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function credentialFields(): array
    {
        return [
            'base_url' => ['label' => 'Uniware base URL', 'type' => 'text', 'required' => true, 'help' => 'https://yourbrand.unicommerce.com'],
            'username' => ['label' => 'Username', 'type' => 'text', 'required' => true],
            'password' => ['label' => 'Password', 'type' => 'password', 'required' => true],
            'facility_code' => ['label' => 'Facility code', 'type' => 'text', 'required' => false],
        ];
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['sale_orders', 'shipments', 'returns', 'inventory', 'channels'];
    }

    /** @return array<string, int> */
    public function syncCadence(): array
    {
        return ['sale_orders' => 15, 'shipments' => 30, 'returns' => 60, 'inventory' => 30, 'channels' => 720];
    }

    /** @param array<string, mixed> $credentials */
    public function connect(array $credentials): ConnectionResult
    {
        try {
            $token = $this->fetchToken(
                (string) $credentials['base_url'],
                (string) $credentials['username'],
                (string) $credentials['password'],
            );
        } catch (Throwable $e) {
            return ConnectionResult::failure('Unicommerce authentication failed: '.$e->getMessage());
        }

        if ($token === null) {
            return ConnectionResult::failure('Unicommerce rejected those credentials.');
        }

        return ConnectionResult::success(
            (string) ($credentials['facility_code'] ?? 'Uniware'),
            [...$credentials, 'access_token' => $token, 'token_fetched_at' => now()->toIso8601String()],
        );
    }

    public function testConnection(): HealthResult
    {
        try {
            $response = $this->client()->post('services/rest/v1/oms/saleorder/search', ['pageNumber' => 1, 'pageSize' => 1]);
        } catch (Throwable $e) {
            return HealthResult::fail('Uniware unreachable: '.$e->getMessage());
        }

        return $response->successful()
            ? HealthResult::ok('Uniware responding.')
            : HealthResult::fail('Uniware returned HTTP '.$response->status().'.');
    }

    protected function syncSaleOrders(SyncContext $ctx): SyncReport
    {
        $since = $ctx->cursor !== null ? Carbon::parse((string) $ctx->cursor) : $ctx->sinceOrDefault(60);
        $upsert = app(UpsertMarketplaceOrder::class);
        $page = 1;
        $fetched = 0;
        $upserted = 0;
        $latest = $since;

        do {
            $response = $this->client()->post('services/rest/v1/oms/saleorder/search', [
                'updatedSinceInMinutes' => max(15, (int) $since->diffInMinutes(now())),
                'pageNumber' => $page,
                'pageSize' => 100,
            ]);

            if ($response->failed()) {
                return SyncReport::failed('sale_orders', 'Uniware search failed with HTTP '.$response->status().'.');
            }

            $elements = $response->json('elements', []);

            foreach ($elements as $summary) {
                $detail = $this->client()->post('services/rest/v1/oms/saleorder/get', [
                    'code' => $summary['code'],
                ])->json('saleOrderDTO');

                if ($detail === null) {
                    continue;
                }

                $fetched++;
                $upsert->handle($ctx->tenant, 'unicommerce', $this->normalise($detail));
                $upserted++;
                $latest = max($latest, Carbon::parse($detail['updated'] ?? $detail['displayOrderDateTime']));
            }

            $page++;
        } while (count($elements) === 100 && $page < 200);

        return SyncReport::of('sale_orders', $fetched, $upserted, $latest->toIso8601String());
    }

    protected function syncInventory(SyncContext $ctx): SyncReport
    {
        $rows = [];
        $fetched = 0;

        $skus = Sku::query()->where('tenant_id', $ctx->tenant->id)->pluck('id', 'sku_code');

        $response = $this->client()->post('services/rest/v1/inventory/inventorySnapshot/get', [
            'itemTypeSKUs' => $skus->keys()->take(500)->values()->all(),
            'facilityCode' => $this->credential('facility_code'),
        ]);

        foreach ($response->json('inventorySnapshots', []) as $snapshot) {
            $skuId = $skus[$snapshot['itemTypeSKU']] ?? null;
            if ($skuId === null) {
                continue;
            }

            $fetched++;
            $rows[] = [
                'tenant_id' => $ctx->tenant->id,
                'sku_id' => $skuId,
                'location_id' => null,
                'source' => 'unicommerce',
                'on_hand' => (int) ($snapshot['inventory'] ?? 0),
                'reserved' => (int) ($snapshot['blocked'] ?? 0),
                'available' => max(0, (int) ($snapshot['inventory'] ?? 0) - (int) ($snapshot['blocked'] ?? 0)),
                'synced_at' => now(),
            ];
        }

        $upserted = $this->upsertChannelStock($ctx->tenant->id, 'unicommerce', $rows);

        return SyncReport::of('inventory', $fetched, $upserted, now()->toIso8601String());
    }

    protected function syncShipments(SyncContext $ctx): SyncReport
    {
        // Shipment rows arrive nested on the sale order payload.
        return SyncReport::empty('shipments', $ctx->cursor);
    }

    protected function syncReturns(SyncContext $ctx): SyncReport
    {
        return SyncReport::empty('returns', $ctx->cursor);
    }

    protected function syncChannels(SyncContext $ctx): SyncReport
    {
        $response = $this->client()->get('services/rest/v1/channel/list');
        $fetched = 0;

        foreach ($response->json('elements', []) as $channel) {
            $fetched++;
            Channel::query()->updateOrCreate(
                ['tenant_id' => $ctx->tenant->id, 'code' => str($channel['code'])->slug('_')->toString()],
                [
                    'name' => $channel['name'] ?? $channel['code'],
                    'type' => 'marketplace',
                    'source_connector' => 'unicommerce',
                    'is_active' => (bool) ($channel['enabled'] ?? true),
                ],
            );
        }

        return SyncReport::of('channels', $fetched, $fetched, now()->toIso8601String());
    }

    /**
     * Map a Uniware sale order onto the shape UpsertMarketplaceOrder expects.
     *
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function normalise(array $order): array
    {
        $address = $order['addresses'][0] ?? [];

        return [
            'external_id' => $order['code'],
            'order_number' => $order['displayOrderCode'] ?? $order['code'],
            'channel_code' => str((string) ($order['channel'] ?? 'marketplace'))->slug('_')->toString(),
            'channel_name' => $order['channel'] ?? 'Marketplace',
            'placed_at' => $order['displayOrderDateTime'] ?? $order['created'],
            'status' => $this->mapStatus((string) ($order['status'] ?? '')),
            'payment_mode' => ($order['cashOnDelivery'] ?? false) ? 'cod' : 'prepaid',
            'state' => $address['stateCode'] ?? $address['state'] ?? null,
            'city' => $address['city'] ?? null,
            'pincode' => $address['pincode'] ?? null,
            'currency' => $order['currencyCode'] ?? 'INR',
            'shipping_amount' => (float) ($order['totalShippingCharges'] ?? 0),
            'tax_amount' => 0.0,
            'items' => collect($order['saleOrderItems'] ?? [])->map(fn (array $item): array => [
                'external_id' => (string) $item['code'],
                'sku_code' => $item['itemSku'] ?? null,
                'title' => $item['itemName'] ?? null,
                'qty' => 1,
                'unit_price' => (float) ($item['sellingPrice'] ?? 0),
                'discount' => (float) ($item['discount'] ?? 0),
                'tax' => (float) ($item['totalTax'] ?? 0),
                'status' => $this->mapStatus((string) ($item['statusCode'] ?? '')),
            ])->all(),
            'fees' => collect($order['saleOrderItems'] ?? [])
                ->filter(fn (array $i): bool => (float) ($i['channelProductId'] ?? 0) > 0 || isset($i['marketplaceFee']))
                ->map(fn (array $i): array => ['type' => 'commission', 'amount' => (float) ($i['marketplaceFee'] ?? 0)])
                ->values()->all(),
        ];
    }

    private function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'CANCELLED' => 'cancelled',
            'DELIVERED', 'COMPLETE' => 'delivered',
            'DISPATCHED', 'SHIPPED' => 'shipped',
            'RETURNED', 'RETURN_EXPECTED' => 'returned',
            'RTO', 'RTO_EXPECTED' => 'rto',
            default => 'confirmed',
        };
    }

    private function client(): PendingRequest
    {
        return $this->http(rtrim((string) $this->credential('base_url'), '/').'/', [
            'Authorization' => 'Bearer '.$this->credential('access_token'),
            'Content-Type' => 'application/json',
            'Facility' => (string) $this->credential('facility_code', ''),
        ]);
    }

    private function fetchToken(string $baseUrl, string $username, string $password): ?string
    {
        $response = $this->http(rtrim($baseUrl, '/').'/')
            ->get('oauth/token', [
                'grant_type' => 'password',
                'client_id' => 'my-trusted-client',
                'username' => $username,
                'password' => $password,
            ]);

        return $response->successful() ? $response->json('access_token') : null;
    }
}
