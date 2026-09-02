<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Domain\Operations\Queries\InventoryQuery;
use App\Domain\Sales\Queries\ProductQuery;
use App\Support\WidgetFilters;

class GetSkuPerformanceTool extends BaseMetricTool
{
    public function __construct(
        private readonly ProductQuery $products,
        private readonly InventoryQuery $inventory,
    ) {}

    public function name(): string
    {
        return 'get_sku_performance';
    }

    public function description(): string
    {
        return 'Per-SKU units, net sales, COGS, contribution margin and return rate, ranked. Can also return the Pareto view (which SKUs make 80% of profit), dead stock, and stock cover in days. Use for product, inventory or reorder questions.';
    }

    public function permission(): string
    {
        return 'catalog.products.view';
    }

    /** @return array<string, mixed> */
    protected function properties(): array
    {
        return [
            ...$this->periodProperties(),
            'view' => [
                'type' => 'string',
                'enum' => ['top_sellers', 'top_margin', 'pareto', 'dead_stock', 'low_cover'],
                'description' => 'Which slice to return. Defaults to top_sellers.',
            ],
            'limit' => ['type' => 'integer', 'description' => 'How many SKUs to return, 1-50. Defaults to 10.'],
        ];
    }

    /** @param array<string, mixed> $input */
    public function run(array $input, WidgetFilters $filters): array
    {
        $resolved = $this->resolve($input, $filters);
        $view = $input['view'] ?? 'top_sellers';
        $limit = min(max((int) ($input['limit'] ?? 10), 1), 50);

        return match ($view) {
            'pareto' => $this->pareto($resolved, $limit),
            'dead_stock' => $this->deadStock($resolved, $limit),
            'low_cover' => $this->lowCover($resolved, $limit),
            'top_margin' => $this->ranked($resolved, $limit, 'margin'),
            default => $this->ranked($resolved, $limit, 'units'),
        };
    }

    /** @return array<string, mixed> */
    private function ranked(WidgetFilters $filters, int $limit, string $orderBy): array
    {
        return [
            'period' => $this->describePeriod($filters),
            'ranked_by' => $orderBy,
            'skus' => $this->products->topSkus($filters, $limit, $orderBy)->map(fn (object $r): array => [
                'sku' => $r->sku_code,
                'name' => $r->name,
                'units' => (int) $r->units,
                'net_sales' => $this->money((int) $r->net_sales),
                'contribution_margin' => $this->money((int) $r->margin),
                'margin_pct' => $r->margin_pct,
                'return_pct' => $r->return_rate,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function pareto(WidgetFilters $filters, int $limit): array
    {
        $result = $this->products->pareto($filters);

        return [
            'period' => $this->describePeriod($filters),
            'sku_count' => $result['sku_count'],
            'skus_making_80pct_of_profit' => $result['pareto_sku_count'],
            'that_is_pct_of_catalog' => $result['pareto_share_pct'],
            'total_margin' => $this->money($result['total_margin']),
            'top_skus' => array_map(fn (array $row): array => [
                'rank' => $row['rank'],
                'sku' => $row['sku_code'],
                'margin' => $this->money($row['margin']),
                'cumulative_profit_pct' => $row['cumulative_pct'],
            ], array_slice($result['rows'], 0, $limit)),
        ];
    }

    /** @return array<string, mixed> */
    private function deadStock(WidgetFilters $filters, int $limit): array
    {
        $result = $this->products->zeroOrderSkus($filters, $limit);

        return [
            'period' => $this->describePeriod($filters),
            'sku_count' => $result['sku_count'],
            'capital_held' => $this->money($result['capital_held']),
            'skus' => array_map(fn (object|array $row): array => [
                'sku' => data_get($row, 'sku_code'),
                'name' => data_get($row, 'name'),
                'stock' => (int) data_get($row, 'stock', 0),
                'capital_held' => $this->money((int) data_get($row, 'capital_held', 0)),
            ], array_slice($result['rows'], 0, $limit)),
        ];
    }

    /** @return array<string, mixed> */
    private function lowCover(WidgetFilters $filters, int $limit): array
    {
        $result = $this->inventory->critical($filters);

        return [
            'threshold_days' => $result['threshold_days'],
            'sku_count_below_threshold' => $result['count'],
            'monthly_revenue_at_risk' => $this->money($result['revenue_at_risk']),
            'skus' => array_map(fn (array $row): array => [
                'sku' => $row['sku_code'],
                'name' => $row['name'],
                'stock' => $row['stock'],
                'days_of_cover' => $row['days_of_cover'],
                'suggested_reorder_qty' => $row['suggested_reorder_qty'],
            ], array_slice($result['rows'], 0, $limit)),
        ];
    }
}
