<?php

declare(strict_types=1);

namespace App\Domain\Operations\Queries;

use App\Enums\ReturnType;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Returns and RTO, honouring the return-date vs order-date basis toggle. The
 * basis changes which date column the window is applied to, which is what makes
 * cohort accounting possible.
 */
class ReturnsQuery
{
    /** @return Collection<int, array{reason_code: mixed, label: string, count: int, units: int, refund_amount: int}> */
    public function byReason(WidgetFilters $filters, int $limit = 10): Collection
    {
        return $this->base($filters)
            ->selectRaw("COALESCE(NULLIF(r.reason_code, ''), 'unspecified') AS reason_code")
            ->selectRaw('COUNT(*) AS count, SUM(r.qty) AS units, SUM(r.refund_amount) AS refund_amount')
            ->groupBy('reason_code')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(static fn (object $r): array => [
                'reason_code' => $r->reason_code,
                'label' => str(str_replace('_', ' ', (string) $r->reason_code))->title()->toString(),
                'count' => (int) $r->count,
                'units' => (int) $r->units,
                'refund_amount' => (int) $r->refund_amount,
            ]);
    }

    /** @return array<string, mixed> */
    public function byChannel(WidgetFilters $filters): array
    {
        $returns = $this->base($filters)
            ->join('channels as c', 'c.id', '=', 'o.channel_id')
            ->selectRaw('c.id, c.name, c.code, c.color')
            ->selectRaw("SUM(CASE WHEN r.type = 'customer_return' THEN 1 ELSE 0 END) AS customer_returns")
            ->selectRaw("SUM(CASE WHEN r.type = 'rto' THEN 1 ELSE 0 END) AS rto_events")
            ->selectRaw('SUM(r.refund_amount) AS refund_amount')
            ->groupBy('c.id', 'c.name', 'c.code', 'c.color')
            ->get();

        $orders = DB::table('daily_metrics_rollup as m')
            ->where('m.tenant_id', Tenant::id())
            ->whereBetween('m.date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('m.channel_id, SUM(m.invoiced_orders) AS invoiced_orders')
            ->groupBy('m.channel_id')
            ->pluck('invoiced_orders', 'channel_id');

        return [
            'rows' => $returns->map(static fn (object $r): array => [
                'channel_id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'color' => $r->color,
                'orders' => (int) ($orders[$r->id] ?? 0),
                'customer_returns' => (int) $r->customer_returns,
                'rto_events' => (int) $r->rto_events,
                'returns_total' => (int) $r->customer_returns + (int) $r->rto_events,
                'refund_amount' => (int) $r->refund_amount,
                'return_pct' => Num::pct((int) $r->customer_returns + (int) $r->rto_events, (int) ($orders[$r->id] ?? 0)),
            ])->sortByDesc('return_pct')->values()->all(),
        ];
    }

    /** @return Collection<int, array{sku_id: mixed, sku_code: mixed, name: mixed, image_url: mixed, returns_count: int, units: int, refund_amount: int}> */
    public function topReturnSkus(WidgetFilters $filters, int $limit = 10): Collection
    {
        return $this->base($filters)
            ->join('skus as s', 's.id', '=', 'r.sku_id')
            ->selectRaw('s.id, s.sku_code, s.name, s.image_url')
            ->selectRaw('COUNT(*) AS returns_count, SUM(r.qty) AS units, SUM(r.refund_amount) AS refund_amount')
            ->groupBy('s.id', 's.sku_code', 's.name', 's.image_url')
            ->orderByDesc('returns_count')
            ->limit($limit)
            ->get()
            ->map(static fn (object $r): array => [
                'sku_id' => $r->id,
                'sku_code' => $r->sku_code,
                'name' => $r->name,
                'image_url' => $r->image_url,
                'returns_count' => (int) $r->returns_count,
                'units' => (int) $r->units,
                'refund_amount' => (int) $r->refund_amount,
            ]);
    }

    /** @return array<string, mixed> */
    public function trend(WidgetFilters $filters): array
    {
        $dateColumn = $filters->usesReturnDateBasis() ? 'r.initiated_at' : 'o.placed_at';
        $offset = now(Tenant::timezone())->format('P');

        $rows = $this->base($filters)
            ->selectRaw("DATE(CONVERT_TZ({$dateColumn}, '+00:00', '{$offset}')) AS d")
            ->selectRaw("SUM(CASE WHEN r.type = 'customer_return' THEN 1 ELSE 0 END) AS customer_returns")
            ->selectRaw("SUM(CASE WHEN r.type = 'rto' THEN 1 ELSE 0 END) AS rto_events")
            ->selectRaw('SUM(r.refund_amount) AS refund_amount')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        return [
            'series' => $filters->period->dateKeys()->map(static fn (string $date): array => [
                'date' => $date,
                'customer_returns' => (int) ($rows[$date]->customer_returns ?? 0),
                'rto_events' => (int) ($rows[$date]->rto_events ?? 0),
                'refund_amount' => (int) ($rows[$date]->refund_amount ?? 0),
            ])->all(),
            'basis' => $filters->returnsBasis,
        ];
    }

    /** @return array<string, mixed> */
    public function kpis(WidgetFilters $filters, float $threshold = 10.0): array
    {
        $current = $this->summarise($filters);
        $previous = $this->summarise($filters->previous());

        return [
            'returns' => ['value' => $current['customer_returns'], 'prev_value' => $previous['customer_returns'], 'format' => 'number', 'higher_is_better' => false, 'label' => 'Returns'],
            'rto_events' => ['value' => $current['rto_events'], 'prev_value' => $previous['rto_events'], 'format' => 'number', 'higher_is_better' => false, 'label' => 'RTO Events'],
            'return_loss' => ['value' => $current['loss'], 'prev_value' => $previous['loss'], 'format' => 'currency', 'higher_is_better' => false, 'label' => 'Return Loss'],
            'return_rate' => ['value' => $current['return_rate'], 'prev_value' => $previous['return_rate'], 'format' => 'percent', 'higher_is_better' => false, 'label' => 'Return Rate'],
            'basis' => $filters->returnsBasis,
            'verdict' => ($current['return_rate'] > $threshold
                ? Verdict::bad(
                    sprintf('Return rate is %.1f%%, above your %.0f%% threshold.', $current['return_rate'], $threshold),
                    sprintf('That is %s of refunds and reverse-logistics cost in this window.', Money::compact($current['loss'])),
                    'Check the top return reasons — sizing and expectation gaps are usually the fix.',
                    $current['loss'],
                )
                : Verdict::good(sprintf('Return rate is %.1f%%, inside your %.0f%% threshold.', $current['return_rate'], $threshold)))->toArray(),
        ];
    }

    /** @return array<string, int|float> */
    private function summarise(WidgetFilters $filters): array
    {
        $row = $this->base($filters)
            ->selectRaw("SUM(CASE WHEN r.type = 'customer_return' THEN 1 ELSE 0 END) AS customer_returns")
            ->selectRaw("SUM(CASE WHEN r.type = 'rto' THEN 1 ELSE 0 END) AS rto_events")
            ->selectRaw('COALESCE(SUM(r.refund_amount), 0) AS refund_amount')
            ->selectRaw('COALESCE(SUM(r.loss_amount), 0) AS loss_amount')
            ->first();

        $invoicedOrders = (int) DB::table('daily_metrics_rollup')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->when($filters->channelIds !== [], fn ($q) => $q->whereIn('channel_id', $filters->channelIds))
            ->sum('invoiced_orders');

        $returns = (int) ($row->customer_returns ?? 0);
        $rto = (int) ($row->rto_events ?? 0);

        return [
            'customer_returns' => $returns,
            'rto_events' => $rto,
            'loss' => (int) ($row->refund_amount ?? 0) + (int) ($row->loss_amount ?? 0),
            'return_rate' => Num::pct($returns + $rto, $invoicedOrders),
        ];
    }

    private function base(WidgetFilters $filters): Builder
    {
        $query = DB::table('returns as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->where('r.tenant_id', Tenant::id());

        $from = $filters->period->from->setTimezone('UTC');
        $to = $filters->period->to->setTimezone('UTC');

        if ($filters->usesReturnDateBasis()) {
            $query->whereBetween('r.initiated_at', [$from, $to]);
        } else {
            $query->whereBetween('o.placed_at', [$from, $to]);
        }

        if ($filters->channelIds !== []) {
            $query->whereIn('o.channel_id', $filters->channelIds);
        }

        if ($filters->paymentMode !== null) {
            $query->where('o.payment_mode', $filters->paymentMode);
        }

        return $query;
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_column(ReturnType::cases(), 'value');
    }
}
