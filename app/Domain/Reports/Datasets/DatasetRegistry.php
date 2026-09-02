<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Customers\Queries\CohortQuery;
use App\Domain\Customers\Queries\CustomerQuery;
use App\Domain\Marketing\Queries\CampaignQuery;
use App\Domain\Operations\Queries\InventoryQuery;
use App\Domain\Operations\Queries\LogisticsQuery;
use App\Domain\Reports\Exports\Column;
use App\Domain\Reports\Exports\Dataset;
use App\Domain\Reports\Queries\PnlQuery;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Domain\Sales\Queries\ChannelQuery;
use App\Domain\Sales\Queries\GeoQuery;
use App\Domain\Sales\Queries\OrderQuery;
use App\Domain\Sales\Queries\ProductQuery;
use App\Domain\Sales\Queries\SalesSummaryQuery;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Everything that can be exported or shown as a report, in one place.
 *
 * Each entry reuses the same query object the on-screen widget uses, so an
 * export can never drift from what the user was looking at.
 */
class DatasetRegistry
{
    /**
     * key => [label, permission, builder]
     *
     * @var array<string, array{label: string, permission: string}>
     */
    private const CATALOGUE = [
        'orders' => ['label' => 'Orders', 'permission' => 'dashboard.recent_orders.export'],
        'order_profitability' => ['label' => 'Order Profitability', 'permission' => 'reports.order_profitability.export'],
        'loss_orders' => ['label' => 'Loss-making Orders', 'permission' => 'dashboard.loss_orders.export'],
        'sales_summary' => ['label' => 'Sales Summary', 'permission' => 'dashboard.sales_summary.export'],
        'channel_scorecard' => ['label' => 'Channel Scorecard', 'permission' => 'reports.channel_scorecard.export'],
        'top_skus' => ['label' => 'Top SKUs', 'permission' => 'finance.top_skus.export'],
        'sku_margin_waterfall' => ['label' => 'SKU Margin Waterfall', 'permission' => 'reports.sku_margin_waterfall.export'],
        'geo_states' => ['label' => 'Sales by State', 'permission' => 'finance.geographic_sales.export'],
        'state_roi' => ['label' => 'State ROI', 'permission' => 'reports.state_roi.export'],
        'transactions' => ['label' => 'Transaction Ledger', 'permission' => 'finance.transaction_ledger.export'],
        'pnl' => ['label' => 'P&L Statement', 'permission' => 'finance.pnl.export'],
        'gst_summary' => ['label' => 'GST Summary', 'permission' => 'finance.gst.export'],
        'fee_leakage' => ['label' => 'Fee Leakage', 'permission' => 'reports.fee_leakage.export'],
        'discount_impact' => ['label' => 'Discount Impact', 'permission' => 'reports.discount_impact.export'],
        'campaigns' => ['label' => 'Campaign Performance', 'permission' => 'marketing.campaign_table.export'],
        'customers' => ['label' => 'Customers', 'permission' => 'customer_intelligence.top_customers.export'],
        'cohort_retention' => ['label' => 'Cohort Retention', 'permission' => 'customer_intelligence.cohorts.export'],
        'returns_register' => ['label' => 'Returns & RTO Register', 'permission' => 'reports.returns_rto_register.export'],
        'inventory_health' => ['label' => 'Inventory Health', 'permission' => 'reports.inventory_health.export'],
        'reorder' => ['label' => 'Reorder & Replenishment', 'permission' => 'catalog.reorder.export'],
        'stockout' => ['label' => 'Stockout Impact', 'permission' => 'catalog.stockouts.export'],
        'order_aging' => ['label' => 'Order Aging', 'permission' => 'operations.order_aging.export'],
        'courier_scorecard' => ['label' => 'Courier Scorecard', 'permission' => 'operations.courier_scorecard.export'],
        'rto_by_state' => ['label' => 'RTO by State', 'permission' => 'operations.rto_by_state.export'],
        'ndr_queue' => ['label' => 'NDR Queue', 'permission' => 'operations.ndr_queue.export'],
        'cod_cash_flow' => ['label' => 'COD Cash Flow', 'permission' => 'reports.cod_cash_flow.export'],
        'zero_order_skus' => ['label' => 'Products with Zero Orders', 'permission' => 'marketplace.zero_order_skus.export'],
    ];

    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly OrderQuery $orders,
        private readonly ProductQuery $products,
        private readonly GeoQuery $geo,
        private readonly ChannelQuery $channels,
        private readonly SalesSummaryQuery $summary,
        private readonly InventoryQuery $inventory,
        private readonly LogisticsQuery $logistics,
        private readonly CustomerQuery $customers,
        private readonly CohortQuery $cohorts,
        private readonly CampaignQuery $campaigns,
        private readonly PnlQuery $pnl,
    ) {}

    /** @return array<string, array{label: string, permission: string}> */
    public static function catalogue(): array
    {
        return self::CATALOGUE;
    }

    public static function has(string $key): bool
    {
        return isset(self::CATALOGUE[$key]);
    }

    public static function permissionFor(string $key): string
    {
        return self::CATALOGUE[$key]['permission'] ?? 'reports.library.export';
    }

    public function build(string $key, WidgetFilters $filters): Dataset
    {
        if (! self::has($key)) {
            throw new InvalidArgumentException("Unknown dataset [{$key}].");
        }

        $context = [
            'Period' => $filters->period->fromDate().' to '.$filters->period->toDate(),
            'Channel' => $filters->channelScope === 'all' ? 'All channels' : $filters->channelScope,
            'Returns basis' => $filters->usesReturnDateBasis() ? 'Return date' : 'Order date (cohort)',
            'Generated' => now(Tenant::timezone())->format('d M Y, H:i'),
        ];

        $method = 'build'.str($key)->studly()->toString();

        return $this->{$method}($filters, $context);
    }

    /** @param array<string, string> $context */
    private function buildOrders(WidgetFilters $filters, array $context): Dataset
    {
        return new Dataset(
            'Orders',
            $this->orders->base($filters)->orderByDesc('placed_at')->limit(20000)->get()->map(fn ($o): array => [
                'order_number' => $o->order_number,
                'placed_at' => $o->placed_at,
                'channel' => $o->channel?->name,
                'status' => $o->status->label(),
                'payment_mode' => $o->payment_mode->label(),
                'state' => $o->shipping_state,
                'city' => $o->shipping_city,
                'units' => $o->units_count,
                'gross_amount' => $o->gross_amount,
                'discount_amount' => $o->discount_amount,
                'net_amount' => $o->net_amount,
                'cogs_amount' => $o->cogs_amount,
                'fees_amount' => $o->fees_amount,
                'logistics_amount' => $o->logistics_amount,
                'contribution_margin' => $o->contribution_margin,
                'margin_pct' => $o->net_amount === 0 ? null : Num::pct($o->contribution_margin, $o->net_amount),
            ]),
            [
                Column::text('order_number', 'Order'),
                Column::datetime('placed_at', 'Placed at'),
                Column::text('channel', 'Channel'),
                Column::text('status', 'Status'),
                Column::text('payment_mode', 'Payment'),
                Column::text('state', 'State'),
                Column::text('city', 'City'),
                Column::number('units', 'Units'),
                Column::money('gross_amount', 'Gross'),
                Column::money('discount_amount', 'Discount'),
                Column::money('net_amount', 'Net sales'),
                Column::money('cogs_amount', 'COGS'),
                Column::money('fees_amount', 'Fees'),
                Column::money('logistics_amount', 'Logistics'),
                Column::money('contribution_margin', 'Contribution margin'),
                Column::percent('margin_pct', 'Margin %'),
            ],
            $context,
            'Amounts are in rupees. Margin % is blank where net sales are zero (an RTO books cost but no revenue).',
        );
    }

    /** @param array<string, string> $context */
    private function buildOrderProfitability(WidgetFilters $filters, array $context): Dataset
    {
        $dataset = $this->buildOrders($filters, $context);

        return new Dataset('Order Profitability', $dataset->rows, $dataset->columns, $context, $dataset->caveat);
    }

    /** @param array<string, string> $context */
    private function buildLossOrders(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->orders->lossMaking($filters, 5000);

        return new Dataset(
            'Loss-making Orders',
            collect($result['rows']),
            [
                Column::text('order_number', 'Order'),
                Column::datetime('placed_at', 'Placed at'),
                Column::text('channel.name', 'Channel'),
                Column::text('payment_mode', 'Payment'),
                Column::text('shipping_state', 'State'),
                Column::money('net_amount', 'Net sales'),
                Column::money('cogs_amount', 'COGS'),
                Column::money('fees_amount', 'Fees'),
                Column::money('logistics_amount', 'Logistics'),
                Column::money('contribution_margin', 'Loss'),
            ],
            $context,
            'Every row here cost more to fulfil than it earned.',
            Verdict::bad($result['verdict']['headline'], $result['verdict']['detail'], $result['verdict']['action']),
        );
    }

    /** @param array<string, string> $context */
    private function buildSalesSummary(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->summary->handle($filters);

        return new Dataset(
            'Sales Summary',
            collect($result['rows']),
            [Column::text('label', 'Line'), Column::number('orders', 'Orders'), Column::money('amount', 'Amount')],
            $context,
            'The gross to net chain. Negative lines are deductions.',
        );
    }

    /** @param array<string, string> $context */
    private function buildChannelScorecard(WidgetFilters $filters, array $context): Dataset
    {
        return new Dataset(
            'Channel Scorecard',
            collect($this->channels->mix($filters)['rows']),
            [
                Column::text('name', 'Channel'),
                Column::text('type', 'Type'),
                Column::number('orders', 'Orders'),
                Column::money('gross_sales', 'Gross'),
                Column::money('net_sales', 'Net sales'),
                Column::money('aov', 'AOV'),
                Column::money('margin', 'Contribution margin'),
                Column::percent('margin_pct', 'Margin %'),
                Column::percent('share_pct', 'Share of net sales'),
                Column::percent('return_pct', 'Return %'),
                Column::percent('rto_pct', 'RTO %'),
            ],
            $context,
        );
    }

    /** @param array<string, string> $context */
    private function buildTopSkus(WidgetFilters $filters, array $context): Dataset
    {
        return new Dataset(
            'Top SKUs',
            $this->products->topSkus($filters, 5000),
            [
                Column::text('sku_code', 'SKU'),
                Column::text('name', 'Product'),
                Column::text('category', 'Category'),
                Column::number('units', 'Units'),
                Column::number('orders', 'Orders'),
                Column::money('net_sales', 'Net sales'),
                Column::money('cogs', 'COGS'),
                Column::money('margin', 'Margin'),
                Column::percent('margin_pct', 'Margin %'),
                Column::percent('return_rate', 'Return %'),
            ],
            $context,
        );
    }

    /** @param array<string, string> $context */
    private function buildSkuMarginWaterfall(WidgetFilters $filters, array $context): Dataset
    {
        return new Dataset(
            'SKU Margin Waterfall',
            $this->products->marginChain($filters),
            [
                Column::text('sku_code', 'SKU'),
                Column::text('name', 'Product'),
                Column::money('mrp', 'Listed price'),
                Column::number('units', 'Units'),
                Column::money('gross_sales', 'Gross'),
                Column::money('discounts', 'less Discounts'),
                Column::money('returned_amount', 'less Returns'),
                Column::money('net_sales', '= Net sales'),
                Column::money('cogs', 'less COGS'),
                Column::money('gross_profit', '= Gross profit'),
                Column::money('fees', 'less Fees'),
                Column::money('other_costs', 'less Logistics & other'),
                Column::money('margin', '= Contribution margin'),
                Column::percent('margin_pct', 'Margin %'),
            ],
            $context,
            'Discounts and the logistics block are derived as residuals of the SKU rollup: marketplaces settle at order level, so per-line costs are allocated by share of net sales.',
        );
    }

    /** @param array<string, string> $context */
    private function buildGeoStates(WidgetFilters $filters, array $context): Dataset
    {
        return new Dataset(
            'Sales by State',
            collect($this->geo->topStates($filters, 100)['rows']),
            [
                Column::text('state', 'State'),
                Column::number('orders', 'Orders'),
                Column::money('net_sales', 'Net sales'),
                Column::percent('share_pct', 'Share %'),
                Column::percent('margin_pct', 'Margin %'),
            ],
            $context,
        );
    }

    /** @param array<string, string> $context */
    private function buildStateRoi(WidgetFilters $filters, array $context): Dataset
    {
        $states = collect($this->geo->actionMatrix($filters)['rows']);

        return new Dataset(
            'State ROI',
            $states,
            [
                Column::text('state', 'State'),
                Column::number('orders', 'Orders'),
                Column::money('net_sales', 'Net sales'),
                Column::percent('margin_pct', 'Margin %'),
                Column::percent('rto_pct', 'RTO %'),
                Column::percent('cod_share_pct', 'COD share %'),
                Column::text('quadrant', 'Quadrant'),
                Column::text('action', 'Recommended action'),
            ],
            $context,
            'Ad spend is not split by state — platforms do not report it at that grain — so this ranks by realised margin rather than ROAS.',
        );
    }

    /** @param array<string, string> $context */
    private function buildTransactions(WidgetFilters $filters, array $context): Dataset
    {
        $rows = DB::table('transactions as t')
            ->leftJoin('orders as o', 'o.id', '=', 't.order_id')
            ->where('t.tenant_id', Tenant::id())
            ->whereBetween('t.processed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->selectRaw('o.order_number, t.gateway, t.method, t.kind, t.status, t.failure_reason, t.amount, t.fee, t.processed_at, o.payment_mode, o.shipping_state')
            ->orderByDesc('t.processed_at')
            ->limit(20000)
            ->get();

        return new Dataset(
            'Transaction Ledger',
            $rows,
            [
                Column::text('order_number', 'Order'),
                Column::datetime('processed_at', 'Processed at'),
                Column::text('gateway', 'Gateway'),
                Column::text('method', 'Method'),
                Column::text('kind', 'Kind'),
                Column::text('status', 'Status'),
                Column::text('failure_reason', 'Failure reason'),
                Column::money('amount', 'Amount'),
                Column::money('fee', 'Fee'),
                Column::text('payment_mode', 'Payment mode'),
                Column::text('shipping_state', 'State'),
            ],
            $context,
            'COD orders have no gateway transaction — cash is collected by the courier and appears in the COD cash flow report instead.',
        );
    }

    /** @param array<string, string> $context */
    private function buildPnl(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->pnl->handle($filters);
        $columns = collect($result['columns']);

        $rows = collect($result['rows'])->map(static function (array $row) use ($columns, $result): array {
            $line = ['label' => $row['label']];

            foreach ($columns as $column) {
                $line[$column['key']] = $column[$row['key']] ?? 0;
            }

            $line['total'] = $result['totals'][$row['key']] ?? 0;

            return $line;
        });

        $definitions = [Column::text('label', 'Line')];
        foreach ($columns as $column) {
            $definitions[] = Column::money($column['key'], $column['month']);
        }
        $definitions[] = Column::money('total', 'Total');

        return new Dataset('P&L Statement', $rows, $definitions, $context, $result['caveat']);
    }

    /** @param array<string, string> $context */
    private function buildGstSummary(WidgetFilters $filters, array $context): Dataset
    {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('skus as s', 's.id', '=', 'oi.sku_id')
            ->where('oi.tenant_id', Tenant::id())
            ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->where('o.status', '!=', 'cancelled')
            ->selectRaw("COALESCE(NULLIF(s.hsn, ''), 'Unclassified') AS hsn, s.gst_rate")
            ->selectRaw('COUNT(DISTINCT o.id) AS orders, COALESCE(SUM(oi.qty),0) AS units')
            ->selectRaw('COALESCE(SUM(oi.line_net),0) - COALESCE(SUM(oi.tax),0) AS taxable_value')
            ->selectRaw('COALESCE(SUM(oi.tax),0) AS tax_amount')
            ->groupByRaw("COALESCE(NULLIF(s.hsn, ''), 'Unclassified'), s.gst_rate")
            ->orderByDesc('taxable_value')
            ->get()
            ->map(static fn (object $r): array => [
                'hsn' => $r->hsn,
                'gst_rate' => (float) $r->gst_rate,
                'orders' => (int) $r->orders,
                'units' => (int) $r->units,
                'taxable_value' => (int) $r->taxable_value,
                'cgst' => (int) round((int) $r->tax_amount / 2),
                'sgst' => (int) round((int) $r->tax_amount / 2),
                'tax_amount' => (int) $r->tax_amount,
            ]);

        return new Dataset(
            'GST Summary',
            $rows,
            [
                Column::text('hsn', 'HSN'),
                Column::percent('gst_rate', 'Rate %'),
                Column::number('orders', 'Orders'),
                Column::number('units', 'Units'),
                Column::money('taxable_value', 'Taxable value'),
                Column::money('cgst', 'CGST'),
                Column::money('sgst', 'SGST'),
                Column::money('tax_amount', 'Total tax'),
            ],
            $context,
            'Derived from the GST rate on each SKU under inclusive pricing, split CGST/SGST evenly. A working view for reconciliation, not a filed return.',
        );
    }

    /** @param array<string, string> $context */
    private function buildFeeLeakage(WidgetFilters $filters, array $context): Dataset
    {
        $rows = DB::table('marketplace_fees as f')
            ->leftJoin('channels as c', 'c.id', '=', 'f.channel_id')
            ->where('f.tenant_id', Tenant::id())
            ->whereBetween('f.fee_date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('c.name AS channel, f.fee_type, COUNT(*) AS occurrences, COALESCE(SUM(f.amount),0) AS amount')
            ->groupBy('c.name', 'f.fee_type')
            ->orderByDesc('amount')
            ->get();

        $gmvByChannel = $this->rollups->byChannel($filters)->pluck('invoiced_sales', 'channel_name');

        return new Dataset(
            'Fee Leakage',
            $rows->map(static fn (object $r) => (object) [
                'channel' => $r->channel,
                'fee_type' => str_replace('_', ' ', (string) $r->fee_type),
                'occurrences' => (int) $r->occurrences,
                'amount' => (int) $r->amount,
                'pct_of_gmv' => Num::pct((int) $r->amount, (int) ($gmvByChannel[$r->channel] ?? 0)),
            ]),
            [
                Column::text('channel', 'Channel'),
                Column::text('fee_type', 'Fee type'),
                Column::number('occurrences', 'Occurrences'),
                Column::money('amount', 'Amount'),
                Column::percent('pct_of_gmv', '% of invoiced sales'),
            ],
            $context,
            'Only fees the marketplace actually reported are included. A channel showing nothing here is not fee-free — its connector may simply not expose them.',
        );
    }

    /** @param array<string, string> $context */
    private function buildDiscountImpact(WidgetFilters $filters, array $context): Dataset
    {
        $rows = DB::table('orders as o')
            ->where('o.tenant_id', Tenant::id())
            ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->whereNotNull('o.discount_codes')
            ->where('o.discount_codes', '!=', '')
            ->selectRaw('o.discount_codes AS code, COUNT(*) AS orders')
            ->selectRaw('COALESCE(SUM(o.gross_amount),0) AS gross, COALESCE(SUM(o.discount_amount),0) AS discount')
            ->selectRaw('COALESCE(SUM(o.net_amount),0) AS net_sales, COALESCE(SUM(o.contribution_margin),0) AS margin')
            ->groupBy('o.discount_codes')
            ->orderByDesc('discount')
            ->get();

        // Undiscounted AOV is the honest baseline for judging whether a promo
        // actually lifted basket size or just gave away margin.
        // MySQL returns AVG() as a string, so cast before rounding.
        $baselineAov = (int) round((float) (DB::table('orders')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->where(fn ($q) => $q->whereNull('discount_codes')->orWhere('discount_codes', ''))
            ->avg('net_amount') ?? 0));

        return new Dataset(
            'Discount Impact',
            $rows->map(static function (object $r) use ($baselineAov) {
                $aov = (int) round(Num::safeDivide((int) $r->net_sales, (int) $r->orders));

                return (object) [
                    'code' => $r->code,
                    'orders' => (int) $r->orders,
                    'net_sales' => (int) $r->net_sales,
                    'discount' => (int) $r->discount,
                    'depth_pct' => Num::pct((int) $r->discount, (int) $r->gross),
                    'margin' => (int) $r->margin,
                    'margin_pct' => Num::pct((int) $r->margin, (int) $r->net_sales),
                    'aov' => $aov,
                    'aov_lift' => $aov - $baselineAov,
                    'verdict' => $aov > $baselineAov && (int) $r->margin > 0
                        ? 'Bought volume'
                        : ((int) $r->margin <= 0 ? 'Burned margin' : 'Neutral'),
                ];
            }),
            [
                Column::text('code', 'Code'),
                Column::number('orders', 'Orders'),
                Column::money('net_sales', 'Net sales'),
                Column::money('discount', 'Discount given'),
                Column::percent('depth_pct', 'Discount depth %'),
                Column::money('margin', 'Contribution margin'),
                Column::percent('margin_pct', 'Margin %'),
                Column::money('aov', 'AOV'),
                Column::money('aov_lift', 'AOV lift vs undiscounted'),
                Column::text('verdict', 'Did it work?'),
            ],
            $context,
            sprintf('Baseline AOV on undiscounted orders in this window is %s.', Money::format($baselineAov)),
        );
    }

    /** @param array<string, string> $context */
    private function buildCampaigns(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->campaigns->table($filters);

        return new Dataset(
            'Campaign Performance',
            collect($result['rows'])->map(static fn (array $row): array => [
                ...$row,
                'verdict_status' => $row['verdict']['status'],
                'verdict_action' => $row['verdict']['action'],
            ]),
            [
                Column::text('name', 'Campaign'),
                Column::text('platform', 'Platform'),
                Column::text('objective', 'Objective'),
                Column::money('spend', 'Spend'),
                Column::number('impressions', 'Impressions'),
                Column::number('clicks', 'Clicks'),
                Column::percent('ctr', 'CTR %'),
                Column::money('cpc', 'CPC'),
                Column::number('conversions', 'Orders'),
                Column::money('attributed_sales', 'Attributed sales'),
                Column::number('roas', 'ROAS'),
                Column::money('cac', 'CAC'),
                Column::text('verdict_status', 'Verdict'),
                Column::text('verdict_action', 'Recommended action'),
            ],
            $context,
            $result['caveat'],
        );
    }

    /** @param array<string, string> $context */
    private function buildCustomers(WidgetFilters $filters, array $context): Dataset
    {
        $unmask = auth()->user()?->can('pii.unmask.view') ?? false;

        return new Dataset(
            'Customers',
            collect($this->customers->list($filters, 20000, 'total_spent', 'desc', $unmask)->items()),
            [
                Column::text('name', 'Name'),
                Column::text('email', 'Email'),
                Column::text('city', 'City'),
                Column::text('state', 'State'),
                Column::text('rfm_label', 'Segment'),
                Column::number('orders_count', 'Orders'),
                Column::money('aov', 'AOV'),
                Column::money('total_spent', 'Total spent'),
                Column::money('total_margin', 'Margin earned'),
                Column::number('returns_count', 'Returns'),
                Column::date('first_order_at', 'First order'),
                Column::date('last_order_at', 'Last order'),
                Column::number('churn_risk_score', 'Churn risk'),
            ],
            $context,
            $unmask
                ? 'Contains unmasked personal data. Handle per your privacy policy.'
                : 'Emails and phone numbers are masked. The pii.unmask.view permission is required to export them in full.',
        );
    }

    /** @param array<string, string> $context */
    private function buildCohortRetention(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->cohorts->heatmap(12);

        $rows = collect($result['cohorts'])->map(static function (array $cohort): array {
            $row = ['cohort_month' => $cohort['cohort_month'], 'cohort_size' => $cohort['cohort_size']];

            foreach ($cohort['cells'] as $index => $cell) {
                $row['m'.$index] = $cell['retention_pct'] ?? null;
            }

            return $row;
        });

        $columns = [Column::text('cohort_month', 'Cohort'), Column::number('cohort_size', 'Size')];
        for ($month = 0; $month <= 12; $month++) {
            $columns[] = Column::percent('m'.$month, 'M'.$month);
        }

        return new Dataset('Cohort Retention', $rows, $columns, $context, $result['caveat']);
    }

    /** @param array<string, string> $context */
    private function buildReturnsRegister(WidgetFilters $filters, array $context): Dataset
    {
        $dateColumn = $filters->usesReturnDateBasis() ? 'r.initiated_at' : 'o.placed_at';

        $rows = DB::table('returns as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->leftJoin('channels as c', 'c.id', '=', 'o.channel_id')
            ->leftJoin('skus as s', 's.id', '=', 'r.sku_id')
            ->where('r.tenant_id', Tenant::id())
            ->whereBetween($dateColumn, [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->selectRaw('o.order_number, o.placed_at, c.name AS channel, o.payment_mode, r.type, r.reason_code, r.reason_text')
            ->selectRaw('s.sku_code, s.name AS sku_name, s.hsn, s.gst_rate, r.qty, r.refund_amount, r.loss_amount')
            ->selectRaw('r.initiated_at, r.received_at, r.restock, o.shipping_state')
            ->orderByDesc('r.initiated_at')
            ->limit(20000)
            ->get();

        return new Dataset(
            'Returns & RTO Register',
            $rows,
            [
                // Column order matches the Tally returns register import layout.
                Column::text('order_number', 'Voucher Ref'),
                Column::date('placed_at', 'Order Date'),
                Column::date('initiated_at', 'Return Date'),
                Column::text('channel', 'Channel'),
                Column::text('type', 'Type'),
                Column::text('sku_code', 'Item Code'),
                Column::text('sku_name', 'Item Name'),
                Column::text('hsn', 'HSN'),
                Column::percent('gst_rate', 'GST Rate'),
                Column::number('qty', 'Quantity'),
                Column::money('refund_amount', 'Refund Amount'),
                Column::money('loss_amount', 'Handling Loss'),
                Column::text('reason_text', 'Reason'),
                Column::text('shipping_state', 'Place of Supply'),
                Column::text('payment_mode', 'Payment Mode'),
                Column::date('received_at', 'Received Date'),
            ],
            $context,
            'Column order matches the Tally returns register import format. RTO rows carry no refund because the customer never paid.',
        );
    }

    /** @param array<string, string> $context */
    private function buildInventoryHealth(WidgetFilters $filters, array $context): Dataset
    {
        return new Dataset(
            'Inventory Health',
            $this->inventory->coverage($filters),
            [
                Column::text('sku_code', 'SKU'),
                Column::text('name', 'Product'),
                Column::text('category', 'Category'),
                Column::number('stock', 'Stock on hand'),
                Column::money('cost_price', 'Unit cost'),
                Column::money('stock_value', 'Stock value at cost'),
                Column::number('units_30d', 'Units sold (30d)'),
                Column::number('daily_rate', 'Units per day'),
                Column::number('days_of_cover', 'Days of cover'),
                Column::money('monthly_revenue', 'Revenue (30d)'),
                Column::number('suggested_reorder_qty', 'Suggested reorder'),
            ],
            $context,
        );
    }

    /** @param array<string, string> $context */
    private function buildReorder(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->inventory->reorder($filters);

        return new Dataset(
            'Reorder & Replenishment',
            collect($result['rows']),
            [
                Column::text('sku_code', 'SKU'),
                Column::text('name', 'Product'),
                Column::text('abc_class', 'ABC class'),
                Column::number('stock', 'Stock'),
                Column::number('daily_rate', 'Units per day'),
                Column::number('days_of_cover', 'Days of cover'),
                Column::number('suggested_reorder_qty', 'Suggested reorder'),
                Column::money('monthly_revenue', 'Revenue (30d)'),
                Column::percent('cumulative_revenue_pct', 'Cumulative revenue %'),
            ],
            $context,
            $result['caveat'],
        );
    }

    /** @param array<string, string> $context */
    private function buildStockout(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->inventory->stockouts($filters);

        return new Dataset(
            'Stockout Impact',
            collect($result['rows']),
            [
                Column::text('sku_code', 'SKU'),
                Column::text('name', 'Product'),
                Column::number('units_30d', 'Units sold (30d)'),
                Column::number('daily_rate', 'Units per day'),
                Column::number('days_out_of_stock', 'Days out of stock'),
                Column::money('selling_price', 'Selling price'),
                Column::money('estimated_lost_revenue', 'Estimated lost revenue'),
            ],
            $context,
            $result['caveat'],
        );
    }

    /** @param array<string, string> $context */
    private function buildOrderAging(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->logistics->orderAging();

        return new Dataset(
            'Order Aging',
            collect($result['rows']),
            [
                Column::text('order_number', 'Order'),
                Column::datetime('placed_at', 'Placed at'),
                Column::text('channel_name', 'Channel'),
                Column::text('shipping_state', 'State'),
                Column::text('payment_mode', 'Payment'),
                Column::number('age_days', 'Age (days)'),
                Column::text('bucket', 'Bucket'),
                Column::text('sla_breached', 'SLA breached'),
                Column::money('net_amount', 'Value'),
            ],
            $context,
            $result['caveat'],
        );
    }

    /** @param array<string, string> $context */
    private function buildCourierScorecard(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->logistics->courierScorecard($filters);

        return new Dataset(
            'Courier Scorecard',
            collect($result['rows']),
            [
                Column::text('courier', 'Courier'),
                Column::number('shipments', 'Shipments'),
                Column::percent('delivered_pct', 'Delivered %'),
                Column::percent('on_time_pct', 'On time %'),
                Column::percent('rto_pct', 'RTO %'),
                Column::number('avg_days', 'Avg days'),
                Column::money('cost_per_shipment', 'Cost per shipment'),
                Column::percent('ndr_resolution_pct', 'NDR resolved %'),
                Column::number('score', 'Score'),
            ],
            $context,
            $result['caveat'],
        );
    }

    /** @param array<string, string> $context */
    private function buildRtoByState(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->geo->rtoByState($filters, 15.0, 100);

        return new Dataset(
            'RTO by State',
            collect($result['rows']),
            [
                Column::text('state', 'State'),
                Column::number('orders', 'Orders'),
                Column::number('rto_count', 'RTO events'),
                Column::percent('rto_pct', 'RTO %'),
                Column::percent('cod_share_pct', 'COD share %'),
                Column::money('net_sales', 'Net sales'),
            ],
            $context,
            $result['caveat'],
        );
    }

    /** @param array<string, string> $context */
    private function buildNdrQueue(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->logistics->ndrQueue($filters, 5000);

        return new Dataset(
            'NDR Queue',
            collect($result['rows']),
            [
                Column::text('awb', 'AWB'),
                Column::text('order_number', 'Order'),
                Column::text('courier', 'Courier'),
                Column::number('attempts', 'Attempts'),
                Column::text('ndr_reason', 'Reason'),
                Column::text('destination_city', 'City'),
                Column::text('destination_state', 'State'),
                Column::text('destination_pincode', 'Pincode'),
                Column::number('days_since_dispatch', 'Days since dispatch'),
                Column::money('net_amount', 'Value at risk'),
            ],
            $context,
            'Act before the third attempt — most couriers auto-return after that.',
        );
    }

    /** @param array<string, string> $context */
    private function buildCodCashFlow(WidgetFilters $filters, array $context): Dataset
    {
        $rows = DB::table('shipments as s')
            ->join('orders as o', 'o.id', '=', 's.order_id')
            ->where('s.tenant_id', Tenant::id())
            ->where('s.payment_mode', 'cod')
            ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->selectRaw('o.order_number, o.placed_at, s.awb, s.courier, s.status, o.net_amount')
            ->selectRaw('s.cod_collected_at, s.cod_remitted_at, s.destination_state')
            ->selectRaw('DATEDIFF(s.cod_remitted_at, s.cod_collected_at) AS remittance_days')
            ->orderByDesc('o.placed_at')
            ->limit(20000)
            ->get()
            ->map(static fn (object $r) => (object) [
                ...(array) $r,
                'settlement_state' => match (true) {
                    $r->cod_remitted_at !== null => 'Remitted',
                    $r->cod_collected_at !== null => 'Collected, awaiting remittance',
                    default => 'In transit',
                },
            ]);

        return new Dataset(
            'COD Cash Flow',
            $rows,
            [
                Column::text('order_number', 'Order'),
                Column::date('placed_at', 'Order date'),
                Column::text('awb', 'AWB'),
                Column::text('courier', 'Courier'),
                Column::text('destination_state', 'State'),
                Column::money('net_amount', 'COD amount'),
                Column::text('settlement_state', 'Settlement state'),
                Column::date('cod_collected_at', 'Collected'),
                Column::date('cod_remitted_at', 'Remitted'),
                Column::number('remittance_days', 'Days to remit'),
            ],
            $context,
            'Remittance timing comes from the courier feed. Anything not yet scanned as collected shows as in transit.',
        );
    }

    /** @param array<string, string> $context */
    private function buildZeroOrderSkus(WidgetFilters $filters, array $context): Dataset
    {
        $result = $this->products->zeroOrderSkus($filters, 5000);

        return new Dataset(
            'Products with Zero Orders',
            collect($result['rows']),
            [
                Column::text('sku_code', 'SKU'),
                Column::text('name', 'Product'),
                Column::text('category', 'Category'),
                Column::number('stock', 'Stock'),
                Column::money('cost_price', 'Unit cost'),
                Column::money('capital_held', 'Capital held'),
            ],
            $context,
            'Active SKUs that sold nothing in this window. The capital column is stock at cost.',
        );
    }
}
