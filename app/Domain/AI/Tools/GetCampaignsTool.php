<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Domain\Marketing\Queries\CampaignQuery;
use App\Support\WidgetFilters;

class GetCampaignsTool extends BaseMetricTool
{
    public function __construct(private readonly CampaignQuery $campaigns) {}

    public function name(): string
    {
        return 'get_campaigns';
    }

    public function description(): string
    {
        return 'Ad campaign performance: spend, attributed sales, ROAS, CAC, CTR and a Scale/Hold/Cut verdict per campaign, plus blended ROAS and the gap between what platforms claim and what the store actually recorded. Use for any advertising, spend or ROAS question.';
    }

    public function permission(): string
    {
        return 'marketing.campaign_table.view';
    }

    /** @param array<string, mixed> $input */
    public function run(array $input, WidgetFilters $filters): array
    {
        $resolved = $this->resolve($input, $filters);
        $table = $this->campaigns->table($resolved);
        $gap = $this->campaigns->attributionGap($resolved);

        return [
            'period' => $this->describePeriod($resolved),
            'total_spend' => $this->money($table['total_spend']),
            'account_roas' => $table['account_roas'],
            'target_roas' => $table['target_roas'],
            'blended_roas' => $gap['blended_roas'],
            'attribution_caveat' => $gap['caveat'],
            'platforms_claim_sales' => $this->money($gap['platform_reported_sales']),
            'store_recorded_net_sales' => $this->money($gap['store_net_sales']),
            'verdict' => $table['verdict']['headline'] ?? null,
            'campaigns' => array_map(fn (array $row): array => [
                'name' => $row['name'],
                'platform' => $row['platform'],
                'spend' => $this->money($row['spend']),
                'attributed_sales' => $this->money($row['attributed_sales']),
                'orders' => $row['conversions'],
                'roas' => $row['roas'],
                'cac' => $this->money($row['cac']),
                'verdict' => $row['verdict']['status'],
                'recommended_action' => $row['verdict']['action'],
            ], array_slice($table['rows'], 0, 25)),
        ];
    }
}
