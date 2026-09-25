<?php

declare(strict_types=1);

namespace App\Domain\Sales\Queries;

use App\Domain\Rollups\Queries\RollupQuery;
use App\Domain\Sales\Support\PaymentInstrumentResolver;
use App\Enums\PaymentInstrument;
use App\Enums\PaymentMode;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * COD vs prepaid economics — the single most India-specific number in the
 * product. COD looks like revenue until you subtract RTO, and this widget is
 * where that becomes obvious.
 */
class PaymentModeQuery
{
    public function __construct(private readonly RollupQuery $rollups) {}

    /** @return array<string, mixed> */
    public function handle(WidgetFilters $filters): array
    {
        $rows = $this->rollups->byPaymentMode($filters);
        $totalNet = (int) $rows->sum('net_sales');
        $totalOrders = (int) $rows->sum('orders_count');

        $modes = collect(PaymentMode::cases())->map(function (PaymentMode $mode) use ($rows, $totalNet, $totalOrders): array {
            $row = $rows->firstWhere('dimension', $mode->value);
            $netSales = (int) ($row->net_sales ?? 0);
            $margin = (int) ($row->contribution_margin ?? 0);
            $orders = (int) ($row->orders_count ?? 0);
            $invoiced = (int) ($row->invoiced_orders ?? 0);

            return [
                'mode' => $mode->value,
                'label' => $mode->label(),
                'orders' => $orders,
                'net_sales' => $netSales,
                'cogs' => (int) ($row->cogs ?? 0),
                'fees' => (int) ($row->marketplace_fees ?? 0) + (int) ($row->gateway_fees ?? 0),
                'logistics' => (int) ($row->logistics_cost ?? 0),
                'net_margin' => $margin,
                'net_margin_pct' => Num::pct($margin, $netSales),
                'share_of_sales' => Num::pct($netSales, $totalNet),
                'share_of_orders' => Num::pct($orders, $totalOrders),
                'rto_orders' => (int) ($row->rto_orders ?? 0),
                'rto_pct' => Num::pct((int) ($row->rto_orders ?? 0), $invoiced),
                'return_pct' => Num::pct((int) ($row->returned_orders ?? 0), $invoiced),
                'aov' => (int) round(Num::safeDivide($netSales, $orders)),
            ];
        })->all();

        return [
            'rows' => $modes,
            'prepaid_breakdown' => $this->prepaidBreakdown($filters),
            'verdict' => $this->verdict($modes)->toArray(),
        ];
    }

    /** @param list<array<string, mixed>> $modes */
    private function verdict(array $modes): Verdict
    {
        $cod = collect($modes)->firstWhere('mode', 'cod');
        $prepaid = collect($modes)->firstWhere('mode', 'prepaid');

        if ($cod === null || $prepaid === null || ($cod['orders'] === 0 && $prepaid['orders'] === 0)) {
            return Verdict::neutral('No orders in this window.');
        }

        if ($cod['orders'] === 0) {
            return Verdict::good('Every order in this window was prepaid.', 'No COD exposure and no RTO risk from cash orders.');
        }

        if ($prepaid['orders'] === 0) {
            return Verdict::watch('Every order was COD.', sprintf('RTO on COD is running at %.1f%%.', $cod['rto_pct']),
                'Offer a small prepaid discount — it usually pays for itself in avoided RTO.');
        }

        $gap = round($prepaid['net_margin_pct'] - $cod['net_margin_pct'], 1);

        if ($gap > 0.5) {
            $shiftableOrders = (int) round($cod['orders'] * 0.2);
            $impact = (int) round($shiftableOrders * Num::safeDivide($cod['net_sales'], max($cod['orders'], 1)) * $gap / 100);

            return new Verdict(
                Verdict::WATCH,
                sprintf('Prepaid is %.1f percentage points more profitable than COD.', $gap),
                sprintf('COD margin %.1f%% vs prepaid %.1f%%, driven mainly by %.1f%% RTO on COD.', $cod['net_margin_pct'], $prepaid['net_margin_pct'], $cod['rto_pct']),
                sprintf('Shifting just 20%% of COD orders to prepaid is worth about %s in this window.', Money::compact($impact)),
                $impact,
            );
        }

        if ($gap < -0.5) {
            return Verdict::good(
                sprintf('COD is currently %.1f points more profitable than prepaid.', abs($gap)),
                'Unusual — check that gateway fees on prepaid are configured correctly in cost settings.',
            );
        }

        return Verdict::neutral('COD and prepaid are earning roughly the same margin.',
            sprintf('Within half a point of each other (%.1f%% vs %.1f%%).', $cod['net_margin_pct'], $prepaid['net_margin_pct']));
    }

    /**
     * How the prepaid half actually paid — UPI, cards, net banking, wallets.
     *
     * The instrument lives on the transaction, not the order, so this is the
     * one part of the widget that reads orders directly: the rollup is only
     * built down to the COD/prepaid grain. Each order is attributed to its
     * largest successful sale transaction, so a split payment or a retried
     * capture cannot count the same order in two buckets.
     *
     * @return array<string, mixed>
     */
    private function prepaidBreakdown(WidgetFilters $filters): array
    {
        if ($filters->paymentMode === PaymentMode::Cod->value) {
            return ['rows' => [], 'orders' => 0, 'net_sales' => 0, 'identified_pct' => 0.0, 'caveat' => null];
        }

        $rows = DB::table('orders as o')
            ->leftJoin('transactions as t', function (JoinClause $join): void {
                $join->on('t.id', '=', DB::raw(<<<'SQL'
                    (SELECT t2.id FROM transactions t2
                      WHERE t2.order_id = o.id AND t2.kind = 'sale' AND t2.status = 'success'
                      ORDER BY t2.amount DESC, t2.id ASC LIMIT 1)
                    SQL));
            })
            ->where('o.tenant_id', Tenant::id())
            ->where('o.payment_mode', PaymentMode::Prepaid->value)
            ->whereBetween('o.placed_at', [
                $filters->period->from->setTimezone('UTC'),
                $filters->period->to->setTimezone('UTC'),
            ])
            ->when($filters->channelIds !== [], fn ($query) => $query->whereIn('o.channel_id', $filters->channelIds))
            ->selectRaw('t.method AS method, t.gateway AS gateway, COUNT(*) AS orders')
            ->selectRaw('COALESCE(SUM(o.net_amount), 0) AS net_sales, COALESCE(SUM(o.gateway_fee_amount), 0) AS fees, COALESCE(SUM(o.contribution_margin), 0) AS net_margin')
            ->groupBy('t.method', 't.gateway')
            ->get();

        /** @var array<string, array{instrument: PaymentInstrument, orders: int, net_sales: int, fees: int, net_margin: int}> $buckets */
        $buckets = [];

        foreach ($rows as $row) {
            $instrument = $row->method === null && $row->gateway === null
                ? PaymentInstrument::Unattributed
                : PaymentInstrumentResolver::resolve($row->method, $row->gateway);

            $bucket = $buckets[$instrument->value] ?? ['instrument' => $instrument, 'orders' => 0, 'net_sales' => 0, 'fees' => 0, 'net_margin' => 0];

            $buckets[$instrument->value] = [
                'instrument' => $instrument,
                'orders' => $bucket['orders'] + (int) $row->orders,
                'net_sales' => $bucket['net_sales'] + (int) $row->net_sales,
                'fees' => $bucket['fees'] + (int) $row->fees,
                'net_margin' => $bucket['net_margin'] + (int) $row->net_margin,
            ];
        }

        $totalOrders = (int) array_sum(array_column($buckets, 'orders'));
        $totalNet = (int) array_sum(array_column($buckets, 'net_sales'));
        $identified = (int) collect($buckets)->filter(static fn (array $b): bool => $b['instrument']->isIdentified())->sum('orders');

        $breakdown = collect($buckets)
            ->map(static fn (array $bucket): array => [
                'instrument' => $bucket['instrument']->value,
                'label' => $bucket['instrument']->label(),
                'identified' => $bucket['instrument']->isIdentified(),
                'orders' => $bucket['orders'],
                'net_sales' => $bucket['net_sales'],
                'fees' => $bucket['fees'],
                'net_margin' => $bucket['net_margin'],
                'net_margin_pct' => Num::pct($bucket['net_margin'], $bucket['net_sales']),
                'fee_pct' => Num::pct($bucket['fees'], $bucket['net_sales']),
                'share_of_orders' => Num::pct($bucket['orders'], $totalOrders),
                'share_of_sales' => Num::pct($bucket['net_sales'], $totalNet),
                'aov' => (int) round(Num::safeDivide($bucket['net_sales'], $bucket['orders'])),
            ])
            // Identified instruments first, biggest first — ranked on net sales
            // so the bars match the figure beside them. The two placeholder
            // buckets sort to the bottom, where they read as a gap in the data
            // rather than as a method someone chose.
            ->sortBy(static fn (array $row): array => [$row['identified'] ? 0 : 1, -$row['net_sales']])
            ->values()
            ->all();

        return [
            'rows' => $breakdown,
            'orders' => $totalOrders,
            'net_sales' => $totalNet,
            'identified_pct' => Num::pct($identified, $totalOrders),
            'caveat' => $this->breakdownCaveat($totalOrders, $identified),
        ];
    }

    /**
     * Says out loud how much of the prepaid split is a real answer. Payment
     * instrument only arrives with transactions, and a store that has not
     * synced them — or a gateway that reports nothing but its own name — would
     * otherwise read as though every order were "other".
     */
    private function breakdownCaveat(int $totalOrders, int $identified): ?string
    {
        if ($totalOrders === 0) {
            return null;
        }

        if ($identified === 0) {
            return 'No prepaid order in this window carries a payment method. Your gateway reports only its own name, or transactions have not synced yet — connect the gateway to split UPI from cards.';
        }

        $coverage = Num::pct($identified, $totalOrders);

        return $coverage < 90.0
            ? sprintf('Method is known for %.0f%% of prepaid orders; the rest came through without one and sit in the last rows.', $coverage)
            : null;
    }
}
