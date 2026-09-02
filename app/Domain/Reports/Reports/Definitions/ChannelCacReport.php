<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Marketing\Queries\CampaignQuery;
use App\Domain\Marketing\Queries\FunnelQuery;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Domain\Rollups\Queries\RollupQuery;
use App\Support\Caveat;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * What a customer costs by source, and whether the margin they bring back
 * covers it. CAC on its own is a vanity number — here it is always next to
 * contribution.
 */
class ChannelCacReport extends Report
{
    public function __construct(
        private readonly CampaignQuery $campaigns,
        private readonly FunnelQuery $funnel,
        private readonly RollupQuery $rollups,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'channel_cac';
    }

    public function label(): string
    {
        return 'Channel CAC';
    }

    public function category(): string
    {
        return 'Marketing & Customers';
    }

    public function description(): string
    {
        return 'CAC and ROAS by acquisition source.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['campaigns'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $table = $this->campaigns->table($filters);
        $spendByPlatform = $this->rollups->adSpend($filters);
        $totals = $this->rollups->totals($filters);
        $previousTotals = $this->rollups->totals($filters->previous());
        $previousSpend = $this->rollups->adSpend($filters->previous())['total'];

        $spend = (int) $spendByPlatform['total'];
        $newCustomers = (int) $totals['new_customers'];
        $previousNew = (int) $previousTotals['new_customers'];

        $cac = (int) round(Num::safeDivide($spend, $newCustomers));
        $previousCac = (int) round(Num::safeDivide($previousSpend, $previousNew));
        $marginPerCustomer = (int) round(Num::safeDivide($totals['contribution_margin'], max(1, $totals['customers_count'])));

        $platforms = $spendByPlatform['by_platform']->map(static function (object $row) use ($newCustomers, $spend): array {
            $platformSpend = (int) $row->spend;

            return [
                'platform' => $row->platform,
                'spend' => $platformSpend,
                'clicks' => (int) $row->clicks,
                'conversions' => (int) $row->conversions,
                'attributed_sales' => (int) $row->conversion_value,
                'roas' => Num::ratio((int) $row->conversion_value, $platformSpend),
                'cpa' => (int) round(Num::safeDivide($platformSpend, (int) $row->conversions)),
                'spend_share_pct' => Num::pct($platformSpend, $spend),
                'implied_customers' => (int) round($newCustomers * Num::safeDivide($platformSpend, $spend)),
            ];
        })->sortByDesc('spend')->values()->all();

        $channels = $this->funnel->channelPerformance($filters);

        return new ReportPayload(
            kpis: [
                $this->kpi('cac', 'Blended CAC', (float) $cac, (float) $previousCac, higherIsBetter: false,
                    tooltip: 'Total ad spend divided by new customers acquired in the window.'),
                $this->kpi('margin_per_customer', 'Margin per Customer', (float) $marginPerCustomer,
                    tooltip: 'Contribution margin divided by customers who ordered in the window.'),
                $this->kpi('mer', 'MER', (float) Num::ratio($totals['net_sales'], $spend), (float) Num::ratio($previousTotals['net_sales'], $previousSpend), 'ratio',
                    tooltip: 'Marketing efficiency ratio: net sales over total ad spend, across every channel.'),
                $this->kpi('new_customers', 'New Customers', (float) $newCustomers, (float) $previousNew, 'number'),
            ],
            sections: [
                Section::table('By ad platform', $platforms, [
                    ['key' => 'platform', 'label' => 'Platform', 'format' => 'text'],
                    ['key' => 'spend', 'label' => 'Spend', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'spend_share_pct', 'label' => 'Share of spend', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'clicks', 'label' => 'Clicks', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'conversions', 'label' => 'Conversions', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'cpa', 'label' => 'Cost per conversion', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'attributed_sales', 'label' => 'Attributed sales', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'roas', 'label' => 'ROAS', 'format' => 'ratio', 'align' => 'right'],
                ], 'Conversions and attributed sales are the platform\'s own numbers.'),
                Section::table('By traffic source', $channels['rows'] ?? [], [
                    ['key' => 'channel_group', 'label' => 'Source', 'format' => 'text'],
                    ['key' => 'sessions', 'label' => 'Sessions', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'orders', 'label' => 'Orders', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'conversion_pct', 'label' => 'Conversion', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'ad_spend', 'label' => 'Ad spend', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'cac', 'label' => 'Cost per order', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'sales', 'label' => 'GA4 sales', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'site_roas', 'label' => 'Site ROAS', 'format' => 'ratio', 'align' => 'right'],
                ], 'From analytics sessions, so this covers your own store only.',
                    caveat: $this->caveatFrom($channels['caveat'] ?? null)),
                $this->tableFromDataset($this->datasets->build('campaigns', $filters), 'Campaign detail', exportKey: 'campaigns'),
            ],
            verdict: $this->verdict($cac, $marginPerCustomer, (float) $table['account_roas'], (float) $table['target_roas'], $spend),
            caveats: array_filter([
                Caveat::partial('CAC is blended: ad platforms report spend by campaign, not by which customers were new, so spend is not split between new and repeat buyers.'),
                $spend === 0 ? Caveat::partial('No ad spend recorded in this window — connect Meta or Google Ads for CAC to mean anything.') : null,
            ]),
        );
    }

    private function verdict(int $cac, int $marginPerCustomer, float $roas, float $targetRoas, int $spend): Verdict
    {
        if ($spend === 0) {
            return Verdict::neutral('No ad spend', 'Every order in this window came in without paid acquisition.');
        }

        if ($cac > $marginPerCustomer && $marginPerCustomer > 0) {
            return Verdict::bad(
                sprintf('You pay %s to acquire a customer who returns %s', Money::format($cac), Money::format($marginPerCustomer)),
                'Each new customer costs more than the margin they bring in this window. It only works if they come back.',
                'Check the cohort retention report — if month-1 repeat is weak, cut spend now.',
                ($cac - $marginPerCustomer),
            );
        }

        if ($roas < $targetRoas) {
            return Verdict::watch(
                sprintf('ROAS %.2f is below your %.2f target', $roas, $targetRoas),
                sprintf('CAC is %s against %s of margin per customer.', Money::format($cac), Money::format($marginPerCustomer)),
                'Move budget to the platform with the best cost per conversion above.',
            );
        }

        return Verdict::good(
            sprintf('Acquisition is paying for itself at %s CAC', Money::format($cac)),
            sprintf('ROAS %.2f against a %.2f target, %s of margin per customer.', $roas, $targetRoas, Money::format($marginPerCustomer)),
            'Increase budget on the highest-ROAS platform while the gap holds.',
        );
    }
}
