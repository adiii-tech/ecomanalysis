<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Sales\Queries\GeoQuery;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * Where India is buying from you — and where the parcels come back. State and
 * city, with RTO next to revenue so the two are never read apart.
 */
class GeoCitiesReport extends Report
{
    public function __construct(private readonly GeoQuery $geo) {}

    public function key(): string
    {
        return 'geo_cities';
    }

    public function label(): string
    {
        return 'Geography';
    }

    public function category(): string
    {
        return 'Marketing & Customers';
    }

    public function description(): string
    {
        return 'Revenue and orders by state and city.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['geo_states'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $states = $this->geo->states($filters, 40);
        $cities = $this->cities($filters);

        $totalNet = (int) $states->sum('net_sales');
        $totalOrders = (int) $states->sum('orders');
        $topState = $states->first();
        $concentration = $topState !== null ? Num::pct((int) $topState->net_sales, $totalNet) : 0.0;

        $stateRows = $states->map(static fn (object $row): array => [
            'state' => $row->state,
            'orders' => (int) $row->orders,
            'net_sales' => (int) $row->net_sales,
            'share_pct' => Num::pct((int) $row->net_sales, $totalNet),
            'aov' => (int) round(Num::safeDivide((int) $row->net_sales, (int) $row->orders)),
            'margin_pct' => Num::pct((int) $row->margin, (int) $row->net_sales),
            'rto_pct' => Num::pct((int) $row->rto_count, (int) $row->orders),
            'cod_share_pct' => Num::pct((int) $row->cod_orders, (int) $row->orders),
        ])->all();

        return new ReportPayload(
            kpis: [
                $this->kpi('states', 'States selling', (float) $states->count(), null, 'number'),
                $this->kpi('cities', 'Cities selling', (float) count($cities), null, 'number'),
                $this->kpi('top_state_share', 'Top state share', $concentration, null, 'percent',
                    tooltip: $topState !== null ? $topState->state : null),
                $this->kpi('aov', 'Average order value', (float) Num::safeDivide($totalNet, $totalOrders)),
            ],
            sections: [
                Section::table('By state', $stateRows, [
                    ['key' => 'state', 'label' => 'State', 'format' => 'text'],
                    ['key' => 'orders', 'label' => 'Orders', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'share_pct', 'label' => 'Share', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'aov', 'label' => 'AOV', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'margin_pct', 'label' => 'Margin %', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'rto_pct', 'label' => 'RTO %', 'format' => 'percent', 'align' => 'right', 'tone' => 'inverse'],
                    ['key' => 'cod_share_pct', 'label' => 'COD share', 'format' => 'percent', 'align' => 'right'],
                ], exportKey: 'geo_states', config: ['drilldown' => ['dimension' => 'state', 'value_key' => 'state']]),
                Section::chart(Section::BAR, 'Net sales by state', array_slice($stateRows, 0, 15), 'state', [
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency'],
                ]),
                Section::table('By city', $cities, [
                    ['key' => 'city', 'label' => 'City', 'format' => 'text'],
                    ['key' => 'state', 'label' => 'State', 'format' => 'text'],
                    ['key' => 'orders', 'label' => 'Orders', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'customers', 'label' => 'Customers', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'aov', 'label' => 'AOV', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'cod_share_pct', 'label' => 'COD share', 'format' => 'percent', 'align' => 'right'],
                ], 'Top 200 cities by net sales.', config: ['drilldown' => ['dimension' => 'city', 'value_key' => 'city']]),
            ],
            verdict: $this->verdict($stateRows, $concentration, $topState),
            caveats: [
                Caveat::note('Geography is taken from the shipping address on the order. Marketplace orders are included wherever the channel shares the delivery state.'),
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    private function cities(WidgetFilters $filters): array
    {
        $query = DB::table('orders')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('shipping_city')
            ->where('shipping_city', '!=', '')
            ->selectRaw('shipping_city AS city, shipping_state AS state')
            ->selectRaw('COUNT(*) AS orders, COUNT(DISTINCT customer_id) AS customers')
            ->selectRaw('COALESCE(SUM(net_amount),0) AS net_sales')
            ->selectRaw("SUM(CASE WHEN payment_mode = 'cod' THEN 1 ELSE 0 END) AS cod_orders")
            ->groupBy('shipping_city', 'shipping_state')
            ->orderByDesc('net_sales')
            ->limit(200);

        if ($filters->channelIds !== []) {
            $query->whereIn('channel_id', $filters->channelIds);
        }

        return $query->get()->map(static fn (object $row): array => [
            'city' => $row->city,
            'state' => $row->state,
            'orders' => (int) $row->orders,
            'customers' => (int) $row->customers,
            'net_sales' => (int) $row->net_sales,
            'aov' => (int) round(Num::safeDivide((int) $row->net_sales, (int) $row->orders)),
            'cod_share_pct' => Num::pct((int) $row->cod_orders, (int) $row->orders),
        ])->all();
    }

    /** @param list<array<string, mixed>> $stateRows */
    private function verdict(array $stateRows, float $concentration, ?object $topState): Verdict
    {
        if ($topState === null) {
            return Verdict::neutral('No geography data', 'No order in this window carried a shipping state.');
        }

        $risky = array_values(array_filter($stateRows, static fn (array $row): bool => (float) $row['rto_pct'] > 20 && (int) $row['orders'] >= 20));

        if ($risky !== []) {
            $worst = $risky[0];

            return Verdict::bad(
                sprintf('%s returns %.1f%% of its orders to origin', $worst['state'], (float) $worst['rto_pct']),
                sprintf('That is %d orders worth %s of net sales at risk.', (int) $worst['orders'], Money::format((int) $worst['net_sales'])),
                'Switch that state to prepaid-only or add address verification before dispatch.',
            );
        }

        if ($concentration > 40) {
            return Verdict::watch(
                sprintf('%.1f%% of your revenue comes from %s alone', $concentration, $topState->state),
                'One state failing — a courier issue, a festival, a lockdown — would take most of your revenue with it.',
                'Test spend in the next two states down this list.',
            );
        }

        return Verdict::good(
            sprintf('Demand is spread across %d states', count($stateRows)),
            sprintf('%s leads with %.1f%% of net sales.', $topState->state, $concentration),
        );
    }
}
