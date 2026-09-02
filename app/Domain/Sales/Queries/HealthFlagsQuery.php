<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * Auto-generated red flags: the things an owner would want shouted at them
 * before they look at anything else. Every flag is derived from real rows —
 * nothing here is estimated.
 */
class HealthFlagsQuery
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /** @return list<array<string, mixed>> */
    public function handle(WidgetFilters $filters): array
    {
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);
        $now = $this->rollups->totals($filters);
        $prev = $this->rollups->totals($filters->previous());

        return collect([
            $this->marginDrop($now, $prev, $benchmark),
            $this->rtoSpike($filters, $benchmark),
            $this->stockouts($benchmark),
            $this->slaBreach($benchmark),
            $this->lossOrders($now),
            $this->adAnomaly($filters),
        ])->filter()->sortByDesc('severity_rank')->values()->all();
    }

    /**
     * @param  array<string, int>  $now
     * @param  array<string, int>  $prev
     * @return array<string, mixed>|null
     */
    private function marginDrop(array $now, array $prev, Benchmark $benchmark): ?array
    {
        $current = Num::pct($now['contribution_margin'], $now['net_sales']);
        $previous = Num::pct($prev['contribution_margin'], $prev['net_sales']);

        if ($now['net_sales'] === 0 || $prev['net_sales'] === 0) {
            return null;
        }

        $drop = $previous - $current;

        if ($drop < 3 && $current >= $benchmark->target_margin_pct) {
            return null;
        }

        return $this->flag(
            'margin_drop',
            $drop >= 5 ? 'critical' : 'warning',
            'Contribution margin fell '.round($drop, 1).' points',
            sprintf('Down from %.1f%% to %.1f%% versus the previous period.', $previous, $current),
            '/finance',
            (int) round($now['net_sales'] * $drop / 100),
        );
    }

    /** @return array<string, mixed>|null */
    private function rtoSpike(WidgetFilters $filters, Benchmark $benchmark): ?array
    {
        $row = DB::table('state_daily_rollup')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('state, SUM(orders_count) AS orders, SUM(rto_count) AS rto, SUM(net_sales) AS net_sales')
            ->groupBy('state')
            ->havingRaw('SUM(orders_count) >= 10')
            ->get()
            ->map(static fn (object $r): object => tap($r, static function (object $x): void {
                $x->rto_pct = Num::pct((int) $x->rto, (int) $x->orders);
            }))
            ->sortByDesc('rto_pct')
            ->first();

        if ($row === null || $row->rto_pct < $benchmark->rto_threshold_pct) {
            return null;
        }

        return $this->flag(
            'rto_spike',
            $row->rto_pct >= $benchmark->rto_threshold_pct * 1.5 ? 'critical' : 'warning',
            sprintf('%s is at %.1f%% RTO', $row->state, $row->rto_pct),
            sprintf('%d of %d orders came back without being delivered — above your %.0f%% threshold.', $row->rto, $row->orders, $benchmark->rto_threshold_pct),
            '/operations',
            (int) round(((int) $row->net_sales) * $row->rto_pct / 100),
        );
    }

    /** @return array<string, mixed>|null */
    private function stockouts(Benchmark $benchmark): ?array
    {
        $rows = DB::table('skus as s')
            ->join('inventory as i', 'i.sku_id', '=', 's.id')
            ->leftJoin('sku_daily_rollup as r', function ($join): void {
                $join->on('r.sku_id', '=', 's.id')->where('r.date', '>=', now()->subDays(30)->toDateString());
            })
            ->where('s.tenant_id', Tenant::id())
            ->where('s.is_active', true)
            ->selectRaw('s.id, s.sku_code, s.name, SUM(i.available) AS stock, COALESCE(SUM(r.units_sold), 0) AS units_30d')
            ->groupBy('s.id', 's.sku_code', 's.name')
            ->havingRaw('COALESCE(SUM(r.units_sold), 0) > 0')
            ->get()
            ->map(static function (object $r) use ($benchmark): object {
                $dailyRate = Num::safeDivide((int) $r->units_30d, 30);
                $r->days_of_cover = $dailyRate > 0 ? round((int) $r->stock / $dailyRate, 1) : 999;
                $r->is_critical = $r->days_of_cover < $benchmark->days_of_cover_threshold;

                return $r;
            })
            ->where('is_critical', true);

        if ($rows->isEmpty()) {
            return null;
        }

        $worst = $rows->sortBy('days_of_cover')->first();

        return $this->flag(
            'stockout_risk',
            $worst->days_of_cover < 3 ? 'critical' : 'warning',
            sprintf('%d bestseller%s about to stock out', $rows->count(), $rows->count() === 1 ? '' : 's'),
            sprintf('%s has %.1f days of cover left at its current sell-through.', $worst->sku_code, $worst->days_of_cover),
            '/catalog',
            null,
        );
    }

    /** @return array<string, mixed>|null */
    private function slaBreach(Benchmark $benchmark): ?array
    {
        $unshipped = DB::table('orders')
            ->where('tenant_id', Tenant::id())
            ->whereIn('status', ['placed', 'confirmed'])
            ->where('placed_at', '<', now()->subDays($benchmark->dispatch_sla_days))
            ->count();

        if ($unshipped === 0) {
            return null;
        }

        return $this->flag(
            'sla_breach',
            $unshipped > 20 ? 'critical' : 'warning',
            sprintf('%d orders unshipped past your %d-day SLA', $unshipped, $benchmark->dispatch_sla_days),
            'Late dispatch is the single biggest driver of cancellations and bad reviews.',
            '/operations',
            null,
        );
    }

    /** @param array<string, int> $now */
    private function lossOrders(array $now): ?array
    {
        if (($now['loss_orders'] ?? 0) === 0) {
            return null;
        }

        return $this->flag(
            'loss_orders',
            'warning',
            sprintf('%d orders lost money', $now['loss_orders']),
            sprintf('They cost you %s in total contribution.', Money::compact(abs($now['loss_amount']))),
            '/reports/order-profitability',
            abs($now['loss_amount']),
        );
    }

    /** @return array<string, mixed>|null */
    private function adAnomaly(WidgetFilters $filters): ?array
    {
        $anomaly = DB::table('metric_anomalies')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->whereIn('metric', ['ad_spend', 'roas'])
            ->where('severity', '!=', 'info')
            ->orderByDesc(DB::raw('ABS(z_score)'))
            ->first();

        if ($anomaly === null) {
            return null;
        }

        return $this->flag(
            'ad_anomaly',
            $anomaly->severity,
            sprintf('%s moved %s sharply on %s', str($anomaly->metric)->headline()->toString(), $anomaly->direction, $anomaly->date),
            sprintf('%.1f standard deviations from its own 28-day norm.', abs((float) $anomaly->z_score)),
            '/marketing',
            null,
        );
    }

    /** @return array<string, mixed> */
    private function flag(string $key, string $severity, string $title, string $body, string $link, ?int $impact): array
    {
        return [
            'key' => $key,
            'severity' => $severity,
            'severity_rank' => match ($severity) {
                'critical' => 3, 'warning' => 2, default => 1
            },
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'impact_amount' => $impact,
        ];
    }
}
