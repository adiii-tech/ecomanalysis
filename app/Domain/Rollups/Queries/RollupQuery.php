<?php

declare(strict_types=1);

namespace App\Domain\Rollups\Queries;

use App\Models\DailyMetricsRollup;
use App\Support\TenantContext;
use App\Support\WidgetFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every dashboard number comes through here. Reads `daily_metrics_rollup`,
 * never raw orders.
 *
 * Returns/RTO are stored on the order-date (cohort) basis. When the caller asks
 * for the return-date basis, the returns columns are overlaid from the `returns`
 * table — small enough to aggregate live — and net sales is re-derived so the
 * whole chain stays internally consistent.
 */
class RollupQuery
{
    public function __construct(private readonly TenantContext $context) {}

    public function base(WidgetFilters $filters): Builder
    {
        $query = DB::table('daily_metrics_rollup')
            ->where('tenant_id', $this->context->requireId())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()]);

        if ($filters->channelIds !== []) {
            $query->whereIn('channel_id', $filters->channelIds);
        }

        if ($filters->paymentMode !== null) {
            $query->where('payment_mode', $filters->paymentMode);
        }

        return $query;
    }

    /**
     * Totals for the window, with the returns basis applied.
     *
     * @return array<string, int>
     */
    public function totals(WidgetFilters $filters): array
    {
        $row = (array) $this->base($filters)->selectRaw($this->sumSelect())->first();
        $totals = array_map(static fn (mixed $v): int => (int) $v, $row);

        return $filters->usesReturnDateBasis()
            ? $this->applyReturnDateBasis($totals, $filters)
            : $totals;
    }

    /**
     * One row per day in the window, zero-filled so charts never have gaps.
     *
     * @param  list<string>  $columns
     * @return Collection<int, non-empty-array<string, int|string>>
     */
    public function daily(WidgetFilters $filters, array $columns = []): Collection
    {
        $columns = $columns ?: DailyMetricsRollup::sumColumns();
        $select = collect($columns)->map(static fn (string $c): string => "COALESCE(SUM({$c}), 0) AS {$c}")->implode(', ');

        $rows = $this->base($filters)
            ->selectRaw("date, {$select}")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(static fn (object $row): string => (string) $row->date);

        return $filters->period->dateKeys()->map(static function (string $date) use ($rows, $columns): array {
            $row = $rows->get($date);

            return [
                'date' => $date,
                ...collect($columns)->mapWithKeys(static fn (string $c): array => [$c => (int) ($row->{$c} ?? 0)])->all(),
            ];
        })->values();
    }

    /**
     * Aggregate grouped by a dimension column on the rollup.
     *
     * @param  list<string>  $columns
     * @return Collection<int, \stdClass>
     */
    public function groupedBy(WidgetFilters $filters, string $dimension, array $columns = []): Collection
    {
        $columns = $columns ?: DailyMetricsRollup::sumColumns();
        $select = collect($columns)->map(static fn (string $c): string => "COALESCE(SUM({$c}), 0) AS {$c}")->implode(', ');

        return $this->base($filters)
            ->selectRaw("{$dimension} AS dimension, {$select}")
            ->groupBy($dimension)
            ->get();
    }

    /**
     * Totals per channel joined to channel metadata, for channel mix / scorecards.
     *
     * @return Collection<int, \stdClass>
     */
    public function byChannel(WidgetFilters $filters): Collection
    {
        $select = collect(DailyMetricsRollup::sumColumns())
            ->map(static fn (string $c): string => "COALESCE(SUM(r.{$c}), 0) AS {$c}")
            ->implode(', ');

        $query = DB::table('daily_metrics_rollup as r')
            ->join('channels as c', 'c.id', '=', 'r.channel_id')
            ->where('r.tenant_id', $this->context->requireId())
            ->whereBetween('r.date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw("c.id AS channel_id, c.name AS channel_name, c.code AS channel_code, c.type AS channel_type, c.color, {$select}")
            ->groupBy('c.id', 'c.name', 'c.code', 'c.type', 'c.color')
            ->orderByDesc('net_sales');

        if ($filters->channelIds !== []) {
            $query->whereIn('r.channel_id', $filters->channelIds);
        }

        if ($filters->paymentMode !== null) {
            $query->where('r.payment_mode', $filters->paymentMode);
        }

        return $query->get();
    }

    /**
     * Totals split by payment mode, for the COD vs prepaid economics widget.
     *
     * @return Collection<int, \stdClass>
     */
    public function byPaymentMode(WidgetFilters $filters): Collection
    {
        return $this->groupedBy($filters, 'payment_mode');
    }

    /**
     * Ad spend for the window, by platform and in total.
     *
     * @return array{total: int, by_platform: Collection<int, \stdClass>, impressions: int, clicks: int, conversions: int, conversion_value: int}
     */
    public function adSpend(WidgetFilters $filters): array
    {
        $rows = DB::table('ad_spend_rollup')
            ->where('tenant_id', $this->context->requireId())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('platform, COALESCE(SUM(spend),0) AS spend, COALESCE(SUM(impressions),0) AS impressions, COALESCE(SUM(clicks),0) AS clicks, COALESCE(SUM(conversions),0) AS conversions, COALESCE(SUM(conversion_value),0) AS conversion_value')
            ->groupBy('platform')
            ->get();

        return [
            'total' => (int) $rows->sum('spend'),
            'by_platform' => $rows,
            'impressions' => (int) $rows->sum('impressions'),
            'clicks' => (int) $rows->sum('clicks'),
            'conversions' => (int) $rows->sum('conversions'),
            'conversion_value' => (int) $rows->sum('conversion_value'),
        ];
    }

    /** @return Collection<int, \stdClass> */
    public function dailyAdSpend(WidgetFilters $filters): Collection
    {
        $rows = DB::table('ad_spend_rollup')
            ->where('tenant_id', $this->context->requireId())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('date, platform, COALESCE(SUM(spend),0) AS spend, COALESCE(SUM(conversions),0) AS conversions, COALESCE(SUM(conversion_value),0) AS conversion_value')
            ->groupBy('date', 'platform')
            ->orderBy('date')
            ->get();

        return $rows;
    }

    /**
     * Replaces the cohort-basis returns columns with return-date-basis figures
     * and re-derives net sales, so switching the toggle changes every returns
     * number consistently instead of only the headline.
     *
     * @param  array<string, int>  $totals
     * @return array<string, int>
     */
    private function applyReturnDateBasis(array $totals, WidgetFilters $filters): array
    {
        $query = DB::table('returns as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->where('r.tenant_id', $this->context->requireId())
            ->whereBetween('r.initiated_at', [
                $filters->period->from->setTimezone('UTC'),
                $filters->period->to->setTimezone('UTC'),
            ]);

        if ($filters->channelIds !== []) {
            $query->whereIn('o.channel_id', $filters->channelIds);
        }

        if ($filters->paymentMode !== null) {
            $query->where('o.payment_mode', $filters->paymentMode);
        }

        $row = $query->selectRaw(<<<'SQL'
            COALESCE(SUM(CASE WHEN r.type = 'customer_return' THEN r.refund_amount ELSE 0 END), 0) AS returned_amount,
            COUNT(DISTINCT CASE WHEN r.type = 'customer_return' THEN r.order_id END) AS returned_orders,
            COALESCE(SUM(CASE WHEN r.type = 'rto' THEN r.refund_amount ELSE 0 END), 0) AS rto_amount,
            COUNT(DISTINCT CASE WHEN r.type = 'rto' THEN r.order_id END) AS rto_orders
        SQL)->first();

        $totals['returned_amount'] = (int) ($row->returned_amount ?? 0);
        $totals['returned_orders'] = (int) ($row->returned_orders ?? 0);
        $totals['rto_amount'] = (int) ($row->rto_amount ?? 0);
        $totals['rto_orders'] = (int) ($row->rto_orders ?? 0);
        $totals['net_sales'] = max(0, $totals['invoiced_sales'] - $totals['returned_amount'] - $totals['rto_amount']);

        return $totals;
    }

    private function sumSelect(): string
    {
        return collect(DailyMetricsRollup::sumColumns())
            ->map(static fn (string $c): string => "COALESCE(SUM({$c}), 0) AS {$c}")
            ->implode(', ');
    }
}
