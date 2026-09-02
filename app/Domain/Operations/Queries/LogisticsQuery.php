<?php

declare(strict_types=1);

namespace App\Domain\Operations\Queries;

use App\Enums\ShipmentStatus;
use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shipment performance, courier scorecards, NDR queue and the live order-aging
 * board. All of it reads shipments joined to orders, never the rollup, because
 * these are operational queues rather than period aggregates.
 */
class LogisticsQuery
{
    /** @return array<string, mixed> */
    public function kpis(WidgetFilters $filters): array
    {
        $current = $this->summarise($filters);
        $previous = $this->summarise($filters->previous());

        return [
            'total_shipments' => ['label' => 'Total Shipments', 'value' => $current['total'], 'prev_value' => $previous['total'], 'format' => 'number', 'higher_is_better' => true, 'badge' => $current['d2c'].' D2C · '.$current['marketplace'].' MP'],
            'delivered_pct' => ['label' => 'Delivered %', 'value' => $current['delivered_pct'], 'prev_value' => $previous['delivered_pct'], 'format' => 'percent', 'higher_is_better' => true],
            'in_transit' => ['label' => 'In Transit', 'value' => $current['in_transit'], 'prev_value' => $previous['in_transit'], 'format' => 'number', 'higher_is_better' => true],
            'returned_pct' => ['label' => 'Returned %', 'value' => $current['returned_pct'], 'prev_value' => $previous['returned_pct'], 'format' => 'percent', 'higher_is_better' => false],
        ];
    }

    /** @return array<string, int|float> */
    private function summarise(WidgetFilters $filters): array
    {
        $row = $this->base($filters)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END) AS delivered")
            ->selectRaw("SUM(CASE WHEN s.status IN ('manifested','in_transit','out_for_delivery','ndr') THEN 1 ELSE 0 END) AS in_transit")
            ->selectRaw('SUM(CASE WHEN s.is_rto = 1 THEN 1 ELSE 0 END) AS rto')
            ->selectRaw("SUM(CASE WHEN c.type = 'd2c' THEN 1 ELSE 0 END) AS d2c")
            ->selectRaw("SUM(CASE WHEN c.type = 'marketplace' THEN 1 ELSE 0 END) AS marketplace")
            ->first();

        $total = (int) ($row->total ?? 0);

        return [
            'total' => $total,
            'delivered' => (int) ($row->delivered ?? 0),
            'delivered_pct' => Num::pct((int) ($row->delivered ?? 0), $total),
            'in_transit' => (int) ($row->in_transit ?? 0),
            'returned_pct' => Num::pct((int) ($row->rto ?? 0), $total),
            'd2c' => (int) ($row->d2c ?? 0),
            'marketplace' => (int) ($row->marketplace ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public function shipmentStatus(WidgetFilters $filters): array
    {
        $rows = $this->base($filters)
            ->selectRaw('s.courier, s.status, COUNT(*) AS count')
            ->groupBy('s.courier', 's.status')
            ->get();

        $couriers = $rows->pluck('courier')->unique()->filter()->values();
        $statuses = collect(ShipmentStatus::cases());

        return [
            'rows' => $couriers->map(static fn (string $courier): array => [
                'courier' => $courier,
                ...$statuses->mapWithKeys(static fn (ShipmentStatus $status): array => [
                    $status->value => (int) ($rows->firstWhere(fn (object $r): bool => $r->courier === $courier && $r->status === $status->value)->count ?? 0),
                ])->all(),
                'total' => (int) $rows->where('courier', $courier)->sum('count'),
            ])->sortByDesc('total')->values()->all(),
            'statuses' => $statuses->map(static fn (ShipmentStatus $s): array => ['key' => $s->value, 'label' => $s->label()])->all(),
            'caveat' => 'Status reflects the last successful courier sync. Anything shipped since then still shows its previous state.',
        ];
    }

    /**
     * Per-courier scorecard: delivery rate, RTO rate, speed, cost and NDR
     * resolution — the four numbers that decide who gets the next shipment.
     *
     * @return array<string, mixed>
     */
    public function courierScorecard(WidgetFilters $filters): array
    {
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);

        $rows = $this->base($filters)
            ->whereNotNull('s.courier')
            ->selectRaw('s.courier, COUNT(*) AS shipments')
            ->selectRaw("SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END) AS delivered")
            ->selectRaw('SUM(CASE WHEN s.is_rto = 1 THEN 1 ELSE 0 END) AS rto')
            ->selectRaw('SUM(CASE WHEN s.attempts > 1 THEN 1 ELSE 0 END) AS ndr_raised')
            ->selectRaw("SUM(CASE WHEN s.attempts > 1 AND s.status = 'delivered' THEN 1 ELSE 0 END) AS ndr_resolved")
            ->selectRaw('AVG(s.transit_days) AS avg_days')
            ->selectRaw('COALESCE(SUM(s.shipping_cost + s.rto_cost),0) AS cost')
            ->selectRaw("SUM(CASE WHEN s.status = 'delivered' AND s.transit_days <= ? THEN 1 ELSE 0 END) AS on_time", [$benchmark->delivery_sla_days])
            ->groupBy('s.courier')
            ->havingRaw('COUNT(*) >= 5')
            ->get();

        $scored = $rows->map(static function (object $row): array {
            $shipments = (int) $row->shipments;
            $deliveryPct = Num::pct((int) $row->delivered, $shipments);
            $rtoPct = Num::pct((int) $row->rto, $shipments);
            $onTimePct = Num::pct((int) $row->on_time, max((int) $row->delivered, 1));

            // A single comparable number: reward delivery and speed, punish RTO.
            $score = round(($deliveryPct * 0.4) + ($onTimePct * 0.35) + ((100 - $rtoPct) * 0.25), 1);

            return [
                'courier' => $row->courier,
                'shipments' => $shipments,
                'delivered_pct' => $deliveryPct,
                'rto_pct' => $rtoPct,
                'on_time_pct' => $onTimePct,
                'avg_days' => round((float) ($row->avg_days ?? 0), 1),
                'cost' => (int) $row->cost,
                'cost_per_shipment' => (int) round(Num::safeDivide((int) $row->cost, $shipments)),
                'ndr_raised' => (int) $row->ndr_raised,
                'ndr_resolution_pct' => Num::pct((int) $row->ndr_resolved, (int) $row->ndr_raised),
                'score' => $score,
                'meets_sla' => $onTimePct >= 85.0,
            ];
        })->sortByDesc('score')->values();

        $best = $scored->first();
        $worst = $scored->last();

        return [
            'rows' => $scored->all(),
            'sla_days' => $benchmark->delivery_sla_days,
            'caveat' => 'Couriers with fewer than 5 shipments in the window are excluded — the rates would be noise.',
            'verdict' => ($scored->count() < 2
                ? Verdict::neutral('Not enough courier volume to compare yet.')
                : Verdict::neutral(
                    sprintf('%s is your strongest courier at a %.1f score; %s is weakest at %.1f.', $best['courier'], $best['score'], $worst['courier'], $worst['score']),
                    sprintf('%s delivers %.1f%% on time with %.1f%% RTO, against %s at %.1f%% and %.1f%%.',
                        $best['courier'], $best['on_time_pct'], $best['rto_pct'], $worst['courier'], $worst['on_time_pct'], $worst['rto_pct']),
                ))->toArray(),
        ];
    }

    /**
     * Undelivered attempts that still need a human decision.
     *
     * @return array<string, mixed>
     */
    public function ndrQueue(WidgetFilters $filters, int $limit = 100): array
    {
        $rows = DB::table('shipments as s')
            ->join('orders as o', 'o.id', '=', 's.order_id')
            ->leftJoin('channels as c', 'c.id', '=', 'o.channel_id')
            ->where('s.tenant_id', Tenant::id())
            ->where(function ($query): void {
                $query->where('s.status', ShipmentStatus::Ndr->value)->orWhere('s.attempts', '>=', 2);
            })
            ->whereNotIn('s.status', [ShipmentStatus::Delivered->value, ShipmentStatus::RtoDelivered->value, ShipmentStatus::Cancelled->value])
            ->selectRaw('s.id, s.awb, s.courier, s.attempts, s.ndr_reason, s.status, s.payment_mode')
            ->selectRaw('s.destination_city, s.destination_state, s.destination_pincode, s.dispatched_at')
            ->selectRaw('o.id AS order_id, o.order_number, o.net_amount, o.placed_at, c.name AS channel_name')
            ->orderByDesc('s.attempts')
            ->orderBy('s.dispatched_at')
            ->limit($limit)
            ->get();

        $valueAtRisk = (int) $rows->sum('net_amount');

        return [
            'rows' => $rows->map(static fn (object $row): array => [
                ...(array) $row,
                'attempts' => (int) $row->attempts,
                'net_amount' => (int) $row->net_amount,
                'days_since_dispatch' => $row->dispatched_at !== null
                    ? (int) now()->diffInDays($row->dispatched_at, absolute: true)
                    : null,
                'urgency' => (int) $row->attempts >= 3 ? 'critical' : 'warning',
            ])->all(),
            'count' => $rows->count(),
            'value_at_risk' => $valueAtRisk,
            'verdict' => ($rows->isEmpty()
                ? Verdict::good('Nothing is sitting in NDR.')
                : Verdict::bad(
                    sprintf('%d shipments need an NDR decision.', $rows->count()),
                    sprintf('%s of order value turns into RTO if nobody acts.', Money::compact($valueAtRisk)),
                    'Call the customer before the third attempt — after that the courier returns it automatically.',
                    $valueAtRisk,
                ))->toArray(),
        ];
    }

    /**
     * Unshipped orders bucketed by age, with SLA breaches flagged.
     *
     * @return array<string, mixed>
     */
    public function orderAging(): array
    {
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);

        $orders = DB::table('orders as o')
            ->leftJoin('channels as c', 'c.id', '=', 'o.channel_id')
            ->where('o.tenant_id', Tenant::id())
            ->whereIn('o.status', ['placed', 'confirmed'])
            ->selectRaw('o.id, o.order_number, o.placed_at, o.net_amount, o.payment_mode, o.shipping_state, c.name AS channel_name')
            ->selectRaw('TIMESTAMPDIFF(HOUR, o.placed_at, NOW()) AS age_hours')
            ->orderBy('o.placed_at')
            ->get()
            ->map(static function (object $row) use ($benchmark): array {
                $days = ((int) $row->age_hours) / 24;

                return [
                    ...(array) $row,
                    'net_amount' => (int) $row->net_amount,
                    'age_days' => round($days, 1),
                    'bucket' => match (true) {
                        $days < 1 => '0-1d',
                        $days < 2 => '1-2d',
                        $days < 3 => '2-3d',
                        default => '>3d',
                    },
                    'sla_breached' => $days > $benchmark->dispatch_sla_days,
                ];
            });

        $buckets = collect(['0-1d', '1-2d', '2-3d', '>3d'])->map(static fn (string $bucket): array => [
            'bucket' => $bucket,
            'count' => $orders->where('bucket', $bucket)->count(),
            'value' => (int) $orders->where('bucket', $bucket)->sum('net_amount'),
        ]);

        $breached = $orders->where('sla_breached', true);

        return [
            'buckets' => $buckets->all(),
            'rows' => $orders->take(200)->values()->all(),
            'total' => $orders->count(),
            'breached_count' => $breached->count(),
            'breached_value' => (int) $breached->sum('net_amount'),
            'sla_days' => $benchmark->dispatch_sla_days,
            'caveat' => 'This board ignores the global date filter — it is a live queue of everything still unshipped.',
            'verdict' => ($breached->isEmpty()
                ? Verdict::good(sprintf('Every unshipped order is inside your %d-day dispatch SLA.', $benchmark->dispatch_sla_days))
                : Verdict::bad(
                    sprintf('%d orders have breached your %d-day dispatch SLA.', $breached->count(), $benchmark->dispatch_sla_days),
                    sprintf('%s of value is sitting unshipped past SLA.', Money::compact((int) $breached->sum('net_amount'))),
                    'Late dispatch is the biggest single driver of cancellations and one-star reviews.',
                    (int) $breached->sum('net_amount'),
                ))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function deliveryPerformance(WidgetFilters $filters): array
    {
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);

        $delivered = $this->base($filters)
            ->where('s.status', ShipmentStatus::Delivered->value)
            ->whereNotNull('s.transit_days')
            ->pluck('s.transit_days')
            ->map(static fn ($v): float => (float) $v)
            ->all();

        $onTime = count(array_filter($delivered, static fn (float $d): bool => $d <= $benchmark->delivery_sla_days));

        return [
            'delivered_count' => count($delivered),
            'on_time_count' => $onTime,
            'on_time_pct' => Num::pct($onTime, count($delivered)),
            'avg_days' => round(Num::mean($delivered), 1),
            'median_days' => round(Num::median($delivered), 1),
            'late_count' => count($delivered) - $onTime,
            'sla_days' => $benchmark->delivery_sla_days,
            'distribution' => collect(range(1, 14))->map(static fn (int $day): array => [
                'day' => $day,
                'count' => count(array_filter($delivered, static fn (float $d): bool => (int) round($d) === $day)),
            ])->all(),
            'verdict' => (count($delivered) === 0
                ? Verdict::neutral('No deliveries completed in this window.')
                : (Num::pct($onTime, count($delivered)) >= 85
                    ? Verdict::good(sprintf('%.1f%% of deliveries met your %d-day SLA.', Num::pct($onTime, count($delivered)), $benchmark->delivery_sla_days))
                    : Verdict::watch(
                        sprintf('Only %.1f%% of deliveries met your %d-day SLA.', Num::pct($onTime, count($delivered)), $benchmark->delivery_sla_days),
                        sprintf('Median transit is %.1f days against a %d-day promise.', Num::median($delivered), $benchmark->delivery_sla_days),
                        'Check the courier scorecard — the gap is usually concentrated in one carrier.',
                    )))->toArray(),
        ];
    }

    /**
     * MySQL returns DECIMAL columns as strings, so cast on the way out — the
     * frontend sorts and formats these numerically.
     *
     * @return Collection<int, array{pincode: mixed, city: mixed, state: mixed, shipments_count: int, rto_count: int, rto_rate: float, risk_score: int, risk_band: mixed, cod_serviceable: bool}>
     */
    public function pincodeRisk(int $limit = 100): Collection
    {
        return DB::table('pincode_risk')
            ->where('tenant_id', Tenant::id())
            ->whereIn('risk_band', ['high', 'critical'])
            ->orderByDesc('risk_score')
            ->orderByDesc('shipments_count')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): array => [
                'pincode' => $row->pincode,
                'city' => $row->city,
                'state' => $row->state,
                'shipments_count' => (int) $row->shipments_count,
                'rto_count' => (int) $row->rto_count,
                'rto_rate' => (float) $row->rto_rate,
                'risk_score' => (int) $row->risk_score,
                'risk_band' => $row->risk_band,
                'cod_serviceable' => (bool) $row->cod_serviceable,
            ]);
    }

    private function base(WidgetFilters $filters): Builder
    {
        $query = DB::table('shipments as s')
            ->join('orders as o', 'o.id', '=', 's.order_id')
            ->leftJoin('channels as c', 'c.id', '=', 'o.channel_id')
            ->where('s.tenant_id', Tenant::id())
            ->whereBetween('o.placed_at', [
                $filters->period->from->setTimezone('UTC'),
                $filters->period->to->setTimezone('UTC'),
            ]);

        if ($filters->channelIds !== []) {
            $query->whereIn('o.channel_id', $filters->channelIds);
        }

        if ($filters->paymentMode !== null) {
            $query->where('o.payment_mode', $filters->paymentMode);
        }

        return $query;
    }
}
