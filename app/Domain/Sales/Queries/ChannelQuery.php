<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

class ChannelQuery
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /** @return array<string, mixed> */
    public function mix(WidgetFilters $filters): array
    {
        $rows = $this->rollups->byChannel($filters);
        $totalNet = (int) $rows->sum('net_sales');

        $channels = $rows->map(static fn (object $row): array => [
            'channel_id' => $row->channel_id,
            'name' => $row->channel_name,
            'code' => $row->channel_code,
            'type' => $row->channel_type,
            'color' => $row->color,
            'orders' => (int) $row->orders_count,
            'gross_sales' => (int) $row->gross_sales,
            'invoiced_sales' => (int) $row->invoiced_sales,
            'net_sales' => (int) $row->net_sales,
            'margin' => (int) $row->contribution_margin,
            'margin_pct' => Num::pct((int) $row->contribution_margin, (int) $row->net_sales),
            'share_pct' => Num::pct((int) $row->net_sales, $totalNet),
            'aov' => (int) round(Num::safeDivide((int) $row->net_sales, (int) $row->orders_count)),
            'return_pct' => Num::pct((int) $row->returned_orders, (int) $row->invoiced_orders),
            'rto_pct' => Num::pct((int) $row->rto_orders, (int) $row->invoiced_orders),
        ])->all();

        return ['rows' => $channels, 'verdict' => $this->verdict($channels)->toArray()];
    }

    /**
     * Daily stacked sales per channel, zero-filled.
     *
     * @return array<string, mixed>
     */
    public function daily(WidgetFilters $filters, string $column = 'invoiced_sales'): array
    {
        $query = DB::table('daily_metrics_rollup as r')
            ->join('channels as c', 'c.id', '=', 'r.channel_id')
            ->where('r.tenant_id', Tenant::id())
            ->whereBetween('r.date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw("r.date, c.code, c.name, c.color, COALESCE(SUM(r.{$column}), 0) AS value")
            ->groupBy('r.date', 'c.code', 'c.name', 'c.color');

        if ($filters->channelIds !== []) {
            $query->whereIn('r.channel_id', $filters->channelIds);
        }

        $rows = $query->get();
        $channels = $rows->unique('code')->map(static fn (object $r): array => [
            'code' => $r->code, 'name' => $r->name, 'color' => $r->color,
        ])->values();

        $byDate = $rows->groupBy('date');

        $series = $filters->period->dateKeys()->map(static function (string $date) use ($byDate, $channels): array {
            $dayRows = $byDate->get($date, collect());

            return [
                'date' => $date,
                ...$channels->mapWithKeys(static fn (array $c): array => [
                    $c['code'] => (int) ($dayRows->firstWhere('code', $c['code'])->value ?? 0),
                ])->all(),
            ];
        })->values();

        // Plain arrays only: cached payloads must survive serialize/unserialize,
        // and a Collection does not round-trip through every cache store.
        return ['series' => $series->all(), 'channels' => $channels->all()];
    }

    /** @param list<array<string, mixed>> $channels */
    private function verdict(array $channels): Verdict
    {
        $ranked = collect($channels)->where('net_sales', '>', 0)->sortByDesc('margin_pct')->values();

        if ($ranked->isEmpty()) {
            return Verdict::neutral('No channel produced net sales in this window.');
        }

        $best = $ranked->first();
        $loss = collect($channels)->where('margin', '<', 0)->sortBy('margin')->first();

        if ($loss !== null) {
            return Verdict::bad(
                sprintf('%s is losing money.', $loss['name']),
                sprintf('%s ran at %.1f%% margin on %s of net sales.', $loss['name'], $loss['margin_pct'], Money::compact($loss['net_sales'])),
                'Re-check its fee configuration and COGS before scaling it further.',
                abs($loss['margin']),
            );
        }

        return Verdict::good(
            sprintf('%s is your most profitable channel at %.1f%% margin.', $best['name'], $best['margin_pct']),
            sprintf('It carries %.1f%% of net sales.', $best['share_pct']),
            $best['share_pct'] < 40 ? 'It is under-weighted relative to its margin — worth pushing more volume through it.' : null,
        );
    }
}
