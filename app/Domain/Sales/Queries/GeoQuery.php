<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Support\Facades\Tenant;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeoQuery
{
    /** @return Collection<int, \stdClass> */
    public function states(WidgetFilters $filters, int $limit = 10): Collection
    {
        return $this->base($filters)
            ->selectRaw('state, SUM(orders_count) AS orders, SUM(net_sales) AS net_sales, SUM(gross_sales) AS gross_sales, SUM(margin) AS margin, SUM(rto_count) AS rto_count, SUM(returned_count) AS returned_count, SUM(cod_orders) AS cod_orders')
            ->groupBy('state')
            ->orderByDesc('net_sales')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, mixed> */
    public function topStates(WidgetFilters $filters, int $limit = 10): array
    {
        $rows = $this->states($filters, $limit);
        $total = (int) $this->base($filters)->sum('net_sales');

        return [
            'rows' => $rows->map(static fn (object $r): array => [
                'state' => $r->state,
                'orders' => (int) $r->orders,
                'net_sales' => (int) $r->net_sales,
                'share_pct' => Num::pct((int) $r->net_sales, $total),
                'margin_pct' => Num::pct((int) $r->margin, (int) $r->net_sales),
            ])->all(),
            'total' => $total,
        ];
    }

    /** @return array<string, mixed> */
    public function rtoByState(WidgetFilters $filters, float $threshold = 15.0, int $limit = 15): array
    {
        $rows = $this->base($filters)
            ->selectRaw('state, SUM(orders_count) AS orders, SUM(rto_count) AS rto_count, SUM(net_sales) AS net_sales, SUM(cod_orders) AS cod_orders')
            ->groupBy('state')
            ->havingRaw('SUM(orders_count) >= 5')
            ->orderByDesc('rto_count')
            ->limit($limit)
            ->get()
            ->map(static fn (object $r): array => [
                'state' => $r->state,
                'orders' => (int) $r->orders,
                'rto_count' => (int) $r->rto_count,
                'rto_pct' => Num::pct((int) $r->rto_count, (int) $r->orders),
                'net_sales' => (int) $r->net_sales,
                'cod_share_pct' => Num::pct((int) $r->cod_orders, (int) $r->orders),
            ])
            ->sortByDesc('rto_pct')
            ->values();

        $flagged = $rows->where('rto_pct', '>=', $threshold)->values();

        return [
            'rows' => $rows->all(),
            'flagged' => $flagged->all(),
            'threshold' => $threshold,
            'caveat' => 'States with fewer than 5 orders are excluded — the rate would be noise.',
            'verdict' => ($flagged->isEmpty()
                ? Verdict::good('No state is above your '.$threshold.'% RTO threshold.')
                : Verdict::bad(
                    sprintf('%d state%s above your %.0f%% RTO threshold.', $flagged->count(), $flagged->count() === 1 ? '' : 's', $threshold),
                    sprintf('%s is worst at %.1f%% RTO on %d orders.', $flagged->first()['state'], $flagged->first()['rto_pct'], $flagged->first()['orders']),
                    'Switch these states to prepaid-only or add address verification at checkout.',
                ))->toArray(),
        ];
    }

    /**
     * States plotted sales × RTO%, each with a recommended action.
     *
     * @return array<string, mixed>
     */
    public function actionMatrix(WidgetFilters $filters, float $rtoThreshold = 15.0): array
    {
        $rows = $this->base($filters)
            ->selectRaw('state, SUM(orders_count) AS orders, SUM(net_sales) AS net_sales, SUM(margin) AS margin, SUM(rto_count) AS rto_count, SUM(cod_orders) AS cod_orders')
            ->groupBy('state')
            ->havingRaw('SUM(orders_count) >= 5')
            ->get();

        $medianSales = Num::median($rows->pluck('net_sales')->map(static fn ($v): float => (float) $v)->all());

        return [
            'rows' => $rows->map(static function (object $r) use ($medianSales, $rtoThreshold): array {
                $rtoPct = Num::pct((int) $r->rto_count, (int) $r->orders);
                $highVolume = (int) $r->net_sales >= $medianSales;
                $highRto = $rtoPct >= $rtoThreshold;

                return [
                    'state' => $r->state,
                    'orders' => (int) $r->orders,
                    'net_sales' => (int) $r->net_sales,
                    'margin_pct' => Num::pct((int) $r->margin, (int) $r->net_sales),
                    'rto_pct' => $rtoPct,
                    'cod_share_pct' => Num::pct((int) $r->cod_orders, (int) $r->orders),
                    'quadrant' => match (true) {
                        $highVolume && ! $highRto => 'scale',
                        $highVolume && $highRto => 'fix',
                        ! $highVolume && ! $highRto => 'grow',
                        default => 'restrict',
                    },
                    'action' => match (true) {
                        $highVolume && ! $highRto => 'Scale — high volume, RTO under control. Push more spend here.',
                        $highVolume && $highRto => 'Fix — this state is big enough that its RTO is costing real money. Prepaid-only or address verification.',
                        ! $highVolume && ! $highRto => 'Grow — clean delivery, low volume. Worth a geo-targeted test.',
                        default => 'Restrict — low volume and high RTO. Switch to prepaid-only; do not spend here.',
                    },
                ];
            })->sortByDesc('net_sales')->values()->all(),
            'median_net_sales' => (int) $medianSales,
            'rto_threshold' => $rtoThreshold,
        ];
    }

    private function base(WidgetFilters $filters): Builder
    {
        $query = DB::table('state_daily_rollup')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()]);

        if ($filters->channelIds !== []) {
            $query->whereIn('channel_id', $filters->channelIds);
        }

        return $query;
    }
}
