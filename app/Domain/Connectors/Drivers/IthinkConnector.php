<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers;

use App\Domain\Connectors\DTOs\HealthResult;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Domain\Connectors\Support\AbstractConnector;
use App\Enums\AuthType;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * iThink Logistics — AWB tracking, NDR, RTO and COD remittance.
 */
class IthinkConnector extends AbstractConnector
{
    public function id(): string
    {
        return 'ithink';
    }

    public function label(): string
    {
        return 'iThink Logistics';
    }

    public function summary(): string
    {
        return 'AWB tracking, shipment status, NDR reasons, RTO events and COD remittance reconciliation.';
    }

    public function authType(): AuthType
    {
        return AuthType::KeySecret;
    }

    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function credentialFields(): array
    {
        return [
            'access_token' => ['label' => 'Access token', 'type' => 'password', 'required' => true],
            'secret_key' => ['label' => 'Secret key', 'type' => 'password', 'required' => true],
            'base_url' => ['label' => 'API base URL', 'type' => 'text', 'required' => false, 'help' => 'Defaults to https://api.ithinklogistics.com/api_v3/'],
        ];
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['shipments', 'ndr', 'cod_remittance'];
    }

    /** @return array<string, int> */
    public function syncCadence(): array
    {
        return ['shipments' => 60, 'ndr' => 120, 'cod_remittance' => 720];
    }

    public function testConnection(): HealthResult
    {
        try {
            $response = $this->client()->post('order/track.json', [
                'data' => ['awb_number_list' => '', ...$this->auth()],
            ]);
        } catch (Throwable $e) {
            return HealthResult::fail('iThink unreachable: '.$e->getMessage());
        }

        return $response->successful()
            ? HealthResult::ok('iThink API responding.')
            : HealthResult::fail('iThink returned HTTP '.$response->status().'.');
    }

    protected function syncShipments(SyncContext $ctx): SyncReport
    {
        $shipments = Shipment::query()
            ->where('tenant_id', $ctx->tenant->id)
            ->whereNotNull('awb')
            ->whereNotIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::RtoDelivered->value, ShipmentStatus::Cancelled->value])
            ->get(['id', 'awb', 'order_id']);

        if ($shipments->isEmpty()) {
            return SyncReport::empty('shipments', now()->toIso8601String());
        }

        $fetched = 0;
        $upserted = 0;

        foreach ($shipments->chunk(25) as $chunk) {
            $response = $this->client()->post('order/track.json', [
                'data' => ['awb_number_list' => $chunk->pluck('awb')->implode(','), ...$this->auth()],
            ]);

            if ($response->failed()) {
                continue;
            }

            foreach ($response->json('data', []) as $awb => $tracking) {
                $shipment = $chunk->firstWhere('awb', (string) $awb);
                if ($shipment === null) {
                    continue;
                }

                $fetched++;
                $status = $this->mapStatus((string) ($tracking['current_status'] ?? ''));
                $scans = $tracking['scan_detail'] ?? [];
                $lastScan = end($scans) ?: [];

                $shipment->forceFill([
                    'status' => $status,
                    'courier' => $tracking['courier_name'] ?? $shipment->courier,
                    'delivered_at' => $status === ShipmentStatus::Delivered ? Carbon::parse($lastScan['scan_date_time'] ?? now()) : $shipment->delivered_at,
                    'is_rto' => in_array($status, [ShipmentStatus::Rto, ShipmentStatus::RtoDelivered], true),
                    'rto_at' => in_array($status, [ShipmentStatus::Rto, ShipmentStatus::RtoDelivered], true)
                        ? Carbon::parse($lastScan['scan_date_time'] ?? now())
                        : $shipment->rto_at,
                    'attempts' => count(array_filter($scans, static fn (array $s): bool => str_contains(strtolower((string) ($s['status'] ?? '')), 'undelivered'))),
                    'ndr_reason' => $status === ShipmentStatus::Ndr ? ($lastScan['remark'] ?? null) : $shipment->ndr_reason,
                ])->save();

                if ($shipment->is_rto) {
                    Order::query()->whereKey($shipment->order_id)->update(['is_rto' => true, 'status' => 'rto']);
                }

                $upserted++;
            }
        }

        return SyncReport::of('shipments', $fetched, $upserted, now()->toIso8601String());
    }

    protected function syncNdr(SyncContext $ctx): SyncReport
    {
        // NDR state arrives on the same tracking payload as shipments.
        return SyncReport::empty('ndr', $ctx->cursor);
    }

    protected function syncCodRemittance(SyncContext $ctx): SyncReport
    {
        $response = $this->client()->post('cod/remittance.json', [
            'data' => [
                'from_date' => $ctx->sinceOrDefault(60)->toDateString(),
                'to_date' => $ctx->untilOrNow()->toDateString(),
                ...$this->auth(),
            ],
        ]);

        if ($response->failed()) {
            return SyncReport::failed('cod_remittance', 'iThink COD remittance returned HTTP '.$response->status().'.');
        }

        $updated = 0;
        foreach ($response->json('data', []) as $remittance) {
            $affected = Shipment::query()
                ->where('tenant_id', $ctx->tenant->id)
                ->where('awb', (string) ($remittance['awb_number'] ?? ''))
                ->update([
                    'cod_amount' => (int) round(((float) ($remittance['cod_amount'] ?? 0)) * 100),
                    'cod_collected_at' => $remittance['collected_date'] ?? null,
                    'cod_remitted_at' => $remittance['remitted_date'] ?? null,
                ]);

            $updated += $affected;
        }

        return SyncReport::of('cod_remittance', $updated, $updated, $ctx->untilOrNow()->toIso8601String());
    }

    private function mapStatus(string $status): ShipmentStatus
    {
        $normalised = strtolower($status);

        return match (true) {
            str_contains($normalised, 'rto') && str_contains($normalised, 'deliver') => ShipmentStatus::RtoDelivered,
            str_contains($normalised, 'rto') => ShipmentStatus::Rto,
            str_contains($normalised, 'deliver') => ShipmentStatus::Delivered,
            str_contains($normalised, 'out for delivery') => ShipmentStatus::OutForDelivery,
            str_contains($normalised, 'undelivered') || str_contains($normalised, 'ndr') => ShipmentStatus::Ndr,
            str_contains($normalised, 'transit') || str_contains($normalised, 'picked') => ShipmentStatus::InTransit,
            str_contains($normalised, 'manifest') => ShipmentStatus::Manifested,
            str_contains($normalised, 'cancel') => ShipmentStatus::Cancelled,
            str_contains($normalised, 'lost') => ShipmentStatus::Lost,
            default => ShipmentStatus::Pending,
        };
    }

    /** @return array{access_token: string, secret_key: string} */
    private function auth(): array
    {
        return [
            'access_token' => (string) $this->credential('access_token'),
            'secret_key' => (string) $this->credential('secret_key'),
        ];
    }

    private function client(): PendingRequest
    {
        $base = (string) ($this->credential('base_url') ?: 'https://api.ithinklogistics.com/api_v3/');

        return $this->http(rtrim($base, '/').'/', ['Content-Type' => 'application/json']);
    }
}
