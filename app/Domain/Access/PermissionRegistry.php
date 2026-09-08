<?php

declare(strict_types=1);

namespace App\Domain\Access;

/**
 * The single definition of every permission in the product.
 *
 * Naming is always `module.widget.action`. Every widget on every page is gated
 * by its own permission from day one — the frontend hides the widget entirely
 * rather than rendering an empty shell.
 */
final class PermissionRegistry
{
    public const ACTION_VIEW = 'view';

    public const ACTION_EXPORT = 'export';

    public const ACTION_MANAGE = 'manage';

    /**
     * module => [label, widgets => [widget => label]]
     *
     * @return array<string, array{label: string, widgets: array<string, string>, exportable?: 'all'|list<string>, manageable?: list<string>}>
     */
    public static function modules(): array
    {
        return [
            'dashboard' => [
                'label' => 'Command Centre',
                'widgets' => [
                    'kpi_strip' => 'KPI strip',
                    'marquee' => 'Headline marquee',
                    'morning_brief' => 'Morning brief',
                    'sales_by_channel' => 'Sales by channel',
                    'sales_journey' => 'Sales journey walkthrough',
                    'net_sales_vs_margin' => 'Net sales vs net margin',
                    'sales_summary' => 'Sales summary waterfall',
                    'top_states' => 'Top performing states',
                    'top_categories' => 'Top performing categories',
                    'payment_mode_economics' => 'COD vs prepaid economics',
                    'channel_mix' => 'Channel mix',
                    'returns_by_channel' => 'Returns by channel',
                    'top_return_reasons' => 'Top return reasons',
                    'top_return_skus' => 'Top return SKUs',
                    'loss_orders' => 'Biggest loss orders',
                    'top_rto_states' => 'Highest RTO states',
                    'recent_orders' => 'Recent orders',
                    'health_flags' => 'Health flags',
                    'revenue_pacing' => 'Revenue pacing',
                    'sku_pareto' => 'SKU pareto',
                    'state_action_matrix' => 'State action matrix',
                    'ltv_cohort' => 'LTV cohort mini',
                    'critical_inventory' => 'Critical inventory',
                    'conversion_funnel' => 'Conversion funnel',
                    'campaigns' => 'Campaign snapshot',
                ],
                'exportable' => 'all',
            ],
            'finance' => [
                'label' => 'Finance',
                'widgets' => [
                    'kpi_strip' => 'KPI strip',
                    'sales_over_time' => 'Net sales over time',
                    'geographic_sales' => 'Geographic sales',
                    'aov_trend' => 'AOV over time',
                    'top_skus' => 'Top SKUs by net sales',
                    'revenue_breakdown' => 'Revenue breakdown waterfall',
                    'payment_method_split' => 'Payment method split',
                    'refunds' => 'Refunds',
                    'transaction_ledger' => 'Transaction ledger',
                    'pnl' => 'True P&L statement',
                    'cash_flow' => 'Cash flow view',
                    'gst' => 'GST summary',
                ],
                'exportable' => 'all',
            ],
            'marketing' => [
                'label' => 'Marketing',
                'widgets' => [
                    'kpi_strip' => 'KPI strip',
                    'campaign_table' => 'Campaign performance table',
                    'insights_cards' => 'Marketing insight cards',
                    'conversion_funnel' => 'Conversion funnel',
                    'abandoned_carts' => 'Abandoned carts',
                    'realtime_users' => 'Live active users',
                    'trend' => 'Marketing trend',
                    'active_users_by_country' => 'Active users by country',
                    'channel_performance' => 'Marketing channel performance',
                    'product_performance' => 'Product marketing performance',
                    'top_cities' => 'Top cities',
                    'top_pages' => 'Top pages & screens',
                    'buyer_persona' => 'Buyer persona age x gender',
                    'creative_fatigue' => 'Creative fatigue',
                    'spend_by_objective' => 'Spend by objective',
                    'placements' => 'Placement performance',
                    'utm_analysis' => 'UTM analysis',
                    'attribution_gap' => 'Attribution gap',
                    'creative_library' => 'Ad creative library',
                    'budget_pacing' => 'Budget pacing',
                    'mer' => 'Marketing efficiency ratio',
                ],
                'exportable' => 'all',
            ],
            'instagram' => [
                'label' => 'Instagram & Facebook',
                'widgets' => [
                    'kpi_strip' => 'KPI strip',
                    'growth_reach' => 'Growth & reach',
                    'engagement' => 'Engagement chart',
                    'content_performance' => 'Content performance grid',
                    'reels_vs_feed' => 'Reels vs feed',
                    'stories' => 'Stories table',
                    'audience' => 'Audience breakdown',
                    'fb_page' => 'Facebook page insights',
                    'best_time' => 'Best time to post',
                    'hashtags' => 'Hashtag performance',
                    'sales_correlation' => 'Organic to sales correlation',
                ],
                'exportable' => 'all',
            ],
            'marketplace' => [
                'label' => 'Marketplace',
                'widgets' => [
                    'today_snapshot' => 'Today snapshot strip',
                    'kpi_strip' => 'KPI strip',
                    'sales_trend' => 'Sales trend',
                    'channel_comparison' => 'Channel comparison',
                    'sales_summary' => 'Marketplace sales summary',
                    'top_categories' => 'Top categories',
                    'order_status' => 'Order status distribution',
                    'payment_split' => 'COD vs prepaid',
                    'top_states' => 'Top states',
                    'top_products' => 'Top performing products',
                    'products_by_channel' => 'Channel-wise top products',
                    'channel_returns' => 'Channel-wise return %',
                    'top_return_reasons' => 'Top return reasons',
                    'zero_order_skus' => 'Products with zero orders',
                    'fast_moving' => 'Fast moving SKUs',
                    'inventory_valuation' => 'Inventory valuation',
                    'recent_orders' => 'Recent orders',
                    'buybox' => 'Buy box & listing health',
                    'price_competitiveness' => 'Price competitiveness',
                    'settlements' => 'Settlement reconciliation',
                ],
                'exportable' => 'all',
            ],
            'operations' => [
                'label' => 'Operations',
                'widgets' => [
                    'kpi_strip' => 'Logistics KPI strip',
                    'returns_kpis' => 'Returns & RTO KPIs',
                    'returns_by_reason' => 'Returns by reason',
                    'returns_by_channel' => 'Returns by channel',
                    'returns_trend' => 'Returns trend',
                    'shipment_status' => 'Shipment status by courier',
                    'rto_by_state' => 'RTO by state',
                    'delivery_funnel' => 'Marketplace delivery funnel',
                    'delivery_performance' => 'On-time delivery performance',
                    'courier_scorecard' => 'Courier scorecard',
                    'ndr_queue' => 'NDR management queue',
                    'pincode_risk' => 'Pincode serviceability & RTO risk',
                    'order_aging' => 'Order aging live board',
                ],
                'exportable' => 'all',
            ],
            'customer_intelligence' => [
                'label' => 'Customer intelligence',
                'widgets' => [
                    'kpi_strip' => 'KPI strip',
                    'top_customers' => 'Top customers',
                    'customer_360' => 'Customer 360',
                    'cohorts' => 'Cohort retention heatmap',
                    'repeat_metrics' => 'Repeat metrics',
                    'rfm' => 'RFM segmentation',
                    'ltv_distribution' => 'LTV distribution',
                    'vip' => 'VIP list',
                    'churn' => 'Churn risk scoring',
                    'purchase_interval' => 'Purchase interval distribution',
                    'serial_returners' => 'Serial returners',
                    'geo' => 'Geographic distribution',
                    'explorer' => 'Customer explorer',
                    'segments' => 'Segment builder',
                ],
                'exportable' => 'all',
                'manageable' => ['segments'],
            ],
            'reviews' => [
                'label' => 'Reviews',
                'widgets' => [
                    'summary' => 'Rating summary',
                    'trend' => 'Rating trend',
                    'recent' => 'Recent reviews feed',
                    'top_rated' => 'Top rated products',
                    'worst_rated' => 'Worst rated products',
                    'sentiment' => 'Sentiment analysis',
                    'themes' => 'Theme extraction',
                    'return_correlation' => 'Review to return correlation',
                ],
                'exportable' => 'all',
            ],
            'catalog' => [
                'label' => 'Catalog & inventory',
                'widgets' => [
                    'kpi_strip' => 'KPI strip',
                    'products' => 'Product list',
                    'best_sellers' => 'Best sellers',
                    'slow_movers' => 'Slow movers',
                    'inventory' => 'Inventory levels',
                    'stockouts' => 'Stockouts',
                    'reorder' => 'Reorder suggestions',
                    'margin' => 'SKU margin',
                    'cost_editor' => 'COGS editor',
                    'stock' => 'Stock levels & adjustments',
                    'locations' => 'Warehouses & locations',
                    'sku_editor' => 'Create & edit SKUs',
                    'suppliers' => 'Suppliers',
                    'purchase_orders' => 'Purchase orders',
                    'stock_counts' => 'Stock counts',
                    'transfers' => 'Stock transfers',
                    'movements' => 'Stock ledger',
                    'reconciliation' => 'Channel stock reconciliation',
                    'batches' => 'Batch & expiry tracking',
                    'bundles' => 'Bundles & kits',
                ],
                'exportable' => 'all',
                'manageable' => [
                    'cost_editor', 'stock', 'locations', 'sku_editor',
                    'suppliers', 'purchase_orders', 'stock_counts', 'transfers',
                    'batches', 'bundles',
                ],
            ],
            'reports' => [
                'label' => 'Report library',
                'widgets' => [
                    'library' => 'Report library',
                    'owner_business_review' => 'Owner business review',
                    'channel_scorecard' => 'Channel scorecard',
                    'discount_impact' => 'Discount impact',
                    'fee_leakage' => 'Fee leakage',
                    'net_realisation' => 'Net realisation',
                    'order_profitability' => 'Order profitability',
                    'cohort_retention' => 'Cohort retention',
                    'geo_cities' => 'Geo cities',
                    'channel_cac' => 'Channel CAC',
                    'new_vs_repeat' => 'New vs repeat',
                    'state_roi' => 'State ROI',
                    'top_customers' => 'Top customers by LTV',
                    'inventory_health' => 'Inventory health',
                    'logistics_performance' => 'Logistics performance',
                    'order_aging' => 'Order aging',
                    'reorder_replenishment' => 'Reorder & replenishment',
                    'stockout' => 'Stockout impact',
                    'cod_cash_flow' => 'COD cash flow',
                    'returns_rto_register' => 'Returns & RTO register',
                    'transaction_ledger' => 'Transaction ledger',
                    'pnl_statement' => 'P&L statement',
                    'gst_summary' => 'GST summary',
                    'sku_margin_waterfall' => 'SKU margin waterfall',
                    'contribution_by_cohort' => 'Contribution by cohort',
                    'forecast' => 'Forecast',
                    'stock_ledger' => 'Stock ledger',
                    'inventory_valuation' => 'Inventory valuation',
                ],
                'exportable' => 'all',
                'manageable' => ['library'],
            ],
            'ai' => [
                'label' => 'AI layer',
                'widgets' => [
                    'chat' => 'Ask AI chat',
                    'chart_insight' => 'Per-chart insight',
                    'panel_insight' => 'Panel insight',
                    'insights_feed' => 'AI insights feed',
                    'opportunities' => 'AI opportunities feed',
                    'morning_brief' => 'Morning brief generation',
                    'share' => 'Shareable answer links',
                ],
                'manageable' => ['chat', 'share'],
            ],
            'alerts' => [
                'label' => 'Alerts & notifications',
                'widgets' => [
                    'rules' => 'Alert rules',
                    'events' => 'Alert events',
                    'notification_centre' => 'Notification centre',
                    'digests' => 'Digest scheduler',
                ],
                'manageable' => ['rules', 'digests'],
            ],
            'connectors' => [
                'label' => 'Connectors',
                'widgets' => [
                    'index' => 'Connector list',
                    'sync_health' => 'Sync health',
                    'credentials' => 'Connector credentials',
                ],
                'manageable' => ['credentials', 'sync_health'],
            ],
            'admin' => [
                'label' => 'Admin console',
                'widgets' => [
                    'users' => 'Users',
                    'permissions' => 'User permissions',
                    'roles' => 'Roles',
                    'audit' => 'Audit log',
                    'settings' => 'Tenant settings',
                    'cost_settings' => 'Cost settings',
                    'benchmarks' => 'Benchmarks',
                    'demo_users' => 'Demo users',
                    'billing' => 'Plan & billing',
                ],
                'exportable' => 'all',
                'manageable' => ['users', 'permissions', 'roles', 'settings', 'cost_settings', 'benchmarks', 'demo_users', 'billing'],
            ],
            'pii' => [
                'label' => 'Personal data',
                'widgets' => [
                    'unmask' => 'Unmask customer emails & phones',
                ],
            ],
        ];
    }

    /**
     * Every permission name in the product.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::modules() as $module => $config) {
            $exportable = $config['exportable'] ?? [];
            $manageable = $config['manageable'] ?? [];

            foreach (array_keys($config['widgets']) as $widget) {
                $permissions[] = "{$module}.{$widget}.".self::ACTION_VIEW;

                if ($exportable === 'all' || (is_array($exportable) && in_array($widget, $exportable, true))) {
                    $permissions[] = "{$module}.{$widget}.".self::ACTION_EXPORT;
                }

                if (in_array($widget, $manageable, true)) {
                    $permissions[] = "{$module}.{$widget}.".self::ACTION_MANAGE;
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    /** @return list<string> */
    public static function forModule(string $module): array
    {
        return array_values(array_filter(self::all(), static fn (string $p): bool => str_starts_with($p, $module.'.')));
    }

    /**
     * @param  list<string>  $modules
     * @return list<string>
     */
    public static function forModules(array $modules): array
    {
        $result = [];
        foreach ($modules as $module) {
            $result = [...$result, ...self::forModule($module)];
        }

        return array_values(array_unique($result));
    }

    /** @return list<string> */
    public static function viewOnly(): array
    {
        return array_values(array_filter(self::all(), static fn (string $p): bool => str_ends_with($p, '.'.self::ACTION_VIEW)));
    }

    public static function label(string $permission): string
    {
        [$module, $widget, $action] = array_pad(explode('.', $permission), 3, '');
        $modules = self::modules();

        $moduleLabel = $modules[$module]['label'] ?? str($module)->headline()->toString();
        $widgetLabel = $modules[$module]['widgets'][$widget] ?? str($widget)->headline()->toString();

        return sprintf('%s · %s · %s', $moduleLabel, $widgetLabel, ucfirst($action));
    }
}
