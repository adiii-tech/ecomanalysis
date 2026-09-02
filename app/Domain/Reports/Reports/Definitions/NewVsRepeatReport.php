<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Customers\Queries\CustomerQuery;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\DB;

/**
 * First-time buyers against returning ones: how many, what they spend, and what
 * each group is actually worth once costs come off.
 */
class NewVsRepeatReport extends Report
{
    public function __construct(
        private readonly RollupQuery $rollups,
        private readonly CustomerQuery $customers,
    ) {}

    public function key(): string
    {
        return 'new_vs_repeat';
    }

    public function label(): string
    {
        return 'New vs Repeat';
    }

    public function category(): string
    {
        return 'Marketing & Customers';
    }

    public function description(): string
    {
        return 'Conversion and ROAS split by customer type.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['customers'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $split = $this->split($filters);
        $previousSplit = $this->split($filters->previous());
        $totals = $this->rollups->totals($filters);
        $previous = $this->rollups->totals($filters->previous());
        $spend = $this->rollups->adSpend($filters)['total'];

        $new = $split['new'];
        $repeat = $split['repeat'];

        $daily = $this->rollups->daily($filters, ['new_customers', 'repeat_customers'])->all();

        return new ReportPayload(
            kpis: [
                $this->kpi('new_customers', 'New Customers', (float) $totals['new_customers'], (float) $previous['new_customers'], 'number'),
                $this->kpi('repeat_customers', 'Returning Customers', (float) $totals['repeat_customers'], (float) $previous['repeat_customers'], 'number'),
                $this->kpi('repeat_share', 'Repeat share of orders', Num::pct($repeat['orders'], $new['orders'] + $repeat['orders']),
                    Num::pct($previousSplit['repeat']['orders'], $previousSplit['new']['orders'] + $previousSplit['repeat']['orders']), 'percent'),
                $this->kpi('aov_gap', 'Repeat AOV premium',
                    (float) ($repeat['aov'] - $new['aov']),
                    (float) ($previousSplit['repeat']['aov'] - $previousSplit['new']['aov']),
                    tooltip: 'How much more a returning customer spends per order than a first-time buyer.'),
            ],
            sections: [
                Section::table('Side by side', [
                    ['metric' => 'Orders', 'new' => $new['orders'], 'repeat' => $repeat['orders'], 'format' => 'number'],
                    ['metric' => 'Customers', 'new' => $new['customers'], 'repeat' => $repeat['customers'], 'format' => 'number'],
                    ['metric' => 'Net sales', 'new' => $new['net_sales'], 'repeat' => $repeat['net_sales'], 'format' => 'currency'],
                    ['metric' => 'AOV', 'new' => $new['aov'], 'repeat' => $repeat['aov'], 'format' => 'currency'],
                    ['metric' => 'Contribution margin', 'new' => $new['margin'], 'repeat' => $repeat['margin'], 'format' => 'currency'],
                    ['metric' => 'Margin %', 'new' => $new['margin_pct'], 'repeat' => $repeat['margin_pct'], 'format' => 'percent'],
                    ['metric' => 'Return rate', 'new' => $new['return_rate'], 'repeat' => $repeat['return_rate'], 'format' => 'percent'],
                    ['metric' => 'COD share', 'new' => $new['cod_share'], 'repeat' => $repeat['cod_share'], 'format' => 'percent'],
                ], [
                    ['key' => 'metric', 'label' => '', 'format' => 'text'],
                    ['key' => 'new', 'label' => 'First-time buyers', 'format' => 'row_format', 'align' => 'right'],
                    ['key' => 'repeat', 'label' => 'Returning buyers', 'format' => 'row_format', 'align' => 'right'],
                ], 'Every row uses the format named on the row itself, so money and percentages sit in one table.'),
                Section::chart(Section::AREA, 'New and returning customers by day', $daily, 'date', [
                    ['key' => 'new_customers', 'label' => 'New', 'format' => 'number'],
                    ['key' => 'repeat_customers', 'label' => 'Returning', 'format' => 'number'],
                ]),
                Section::callouts('What each group is worth', [
                    ['label' => 'Margin per new customer', 'value' => (int) round(Num::safeDivide($new['margin'], $new['customers'])), 'format' => 'currency'],
                    ['label' => 'Margin per returning customer', 'value' => (int) round(Num::safeDivide($repeat['margin'], $repeat['customers'])), 'format' => 'currency',
                        'tone' => 'good', 'note' => 'No acquisition cost attached.'],
                    ['label' => 'Ad spend in window', 'value' => $spend, 'format' => 'currency'],
                    ['label' => 'Spend per new customer', 'value' => (int) round(Num::safeDivide($spend, $new['customers'])), 'format' => 'currency',
                        'note' => 'All spend charged to new customers — the conservative read.'],
                ]),
            ],
            verdict: $this->verdict($new, $repeat, (int) $spend),
            caveats: [$this->customers->caveat()],
        );
    }

    /** @return array{new: array<string, mixed>, repeat: array<string, mixed>} */
    private function split(WidgetFilters $filters): array
    {
        $query = DB::table('orders')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('customer_id')
            ->selectRaw('is_first_order')
            ->selectRaw('COUNT(*) AS orders, COUNT(DISTINCT customer_id) AS customers')
            ->selectRaw('COALESCE(SUM(net_amount),0) AS net_sales, COALESCE(SUM(contribution_margin),0) AS margin')
            ->selectRaw("SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS returned_orders")
            ->selectRaw("SUM(CASE WHEN payment_mode = 'cod' THEN 1 ELSE 0 END) AS cod_orders")
            ->groupBy('is_first_order');

        if ($filters->channelIds !== []) {
            $query->whereIn('channel_id', $filters->channelIds);
        }

        $rows = $query->get()->keyBy(static fn (object $row): int => (int) $row->is_first_order);

        return [
            'new' => $this->shape($rows->get(1)),
            'repeat' => $this->shape($rows->get(0)),
        ];
    }

    /** @return array<string, mixed> */
    private function shape(?object $row): array
    {
        $orders = (int) ($row->orders ?? 0);
        $net = (int) ($row->net_sales ?? 0);

        return [
            'orders' => $orders,
            'customers' => (int) ($row->customers ?? 0),
            'net_sales' => $net,
            'aov' => (int) round(Num::safeDivide($net, $orders)),
            'margin' => (int) ($row->margin ?? 0),
            'margin_pct' => Num::pct((int) ($row->margin ?? 0), $net),
            'return_rate' => Num::pct((int) ($row->returned_orders ?? 0), $orders),
            'cod_share' => Num::pct((int) ($row->cod_orders ?? 0), $orders),
        ];
    }

    /**
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $repeat
     */
    private function verdict(array $new, array $repeat, int $spend): Verdict
    {
        $totalOrders = (int) $new['orders'] + (int) $repeat['orders'];

        if ($totalOrders === 0) {
            return Verdict::neutral('No attributable orders', 'No order in this window carried a customer record.');
        }

        $repeatShare = Num::pct((int) $repeat['orders'], $totalOrders);
        $newMarginPerCustomer = Num::safeDivide((int) $new['margin'], max(1, (int) $new['customers']));
        $spendPerNew = Num::safeDivide($spend, max(1, (int) $new['customers']));

        if ($spend > 0 && $spendPerNew > $newMarginPerCustomer) {
            return Verdict::bad(
                'First orders are bought at a loss',
                sprintf('You spend %s to win a customer whose first order returns %s of margin.',
                    Money::format((int) round($spendPerNew)), Money::format((int) round($newMarginPerCustomer))),
                sprintf('It only works if they come back — repeat share is %.1f%% today.', $repeatShare),
                (int) round(($spendPerNew - $newMarginPerCustomer) * (int) $new['customers']),
            );
        }

        if ($repeatShare < 20) {
            return Verdict::watch(
                sprintf('Only %.1f%% of orders come from returning customers', $repeatShare),
                'The business is running on newly bought customers, which is the expensive way to grow.',
                'Put a post-purchase flow at day 21-30 in front of the last 90 days of buyers.',
            );
        }

        return Verdict::good(
            sprintf('Returning customers drive %.1f%% of orders', $repeatShare),
            sprintf('They spend %s per order against %s for first-timers.',
                Money::format((int) $repeat['aov']), Money::format((int) $new['aov'])),
            'Keep the retention flows funded — they are cheaper than the ad account.',
        );
    }
}
