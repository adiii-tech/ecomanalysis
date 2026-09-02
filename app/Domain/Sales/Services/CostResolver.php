<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Models\CostSetting;
use App\Models\Sku;
use App\Models\SkuCostHistory;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Resolves per-SKU COGS as at a given date plus the tenant's fixed cost
 * settings. Costs are cached per tenant for the life of a job/request so a
 * rollup rebuild does not re-query the cost history thousands of times.
 */
class CostResolver
{
    /** @var array<int, CostSetting> */
    private array $settings = [];

    /** @var array<string, int> keyed by "{skuId}:{date}" */
    private array $costCache = [];

    /** @var array<int, Collection<int, SkuCostHistory>> keyed by sku id */
    private array $history = [];

    public function settingsFor(Tenant $tenant): CostSetting
    {
        return $this->settings[$tenant->id] ??= CostSetting::query()->firstOrCreate(['tenant_id' => $tenant->id]);
    }

    /**
     * Cost price in paise that applied on `$date`.
     */
    public function costFor(Sku $sku, string $date): int
    {
        $key = $sku->id.':'.$date;

        if (isset($this->costCache[$key])) {
            return $this->costCache[$key];
        }

        $this->history[$sku->id] ??= $sku->costHistory()->orderByDesc('effective_from')->get();

        $applicable = $this->history[$sku->id]->first(
            static fn ($row): bool => $row->effective_from->toDateString() <= $date,
        );

        return $this->costCache[$key] = (int) ($applicable?->cost_price ?? $sku->cost_price);
    }

    /**
     * Per-order fixed costs: packaging + fixed handling + COD charge when COD.
     */
    public function fixedOrderCost(Tenant $tenant, bool $isCod): int
    {
        $settings = $this->settingsFor($tenant);

        return $settings->packaging_cost
            + $settings->per_order_fixed_cost
            + ($isCod ? $settings->cod_charge : 0);
    }

    public function gatewayFee(Tenant $tenant, int $amountPaise, bool $isCod): int
    {
        if ($isCod) {
            return 0;
        }

        return (int) round($amountPaise * $this->settingsFor($tenant)->gateway_fee_pct / 100);
    }

    public function forget(): void
    {
        $this->settings = [];
        $this->costCache = [];
        $this->history = [];
    }
}
