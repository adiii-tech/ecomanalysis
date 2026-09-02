<?php

declare(strict_types=1);

namespace App\Domain\Access;

/**
 * Built-in roles. Tenants can also build custom roles on top of these via the
 * admin console; per-user overrides then layer on top of whichever role applies.
 */
final class RoleRegistry
{
    public const OWNER = 'OWNER';

    public const ADMIN = 'ADMIN';

    public const FINANCE = 'FINANCE';

    public const MARKETING = 'MARKETING';

    public const OPERATIONS = 'OPERATIONS';

    public const ANALYST = 'ANALYST';

    public const DEMO = 'DEMO';

    /** @return list<string> */
    public static function names(): array
    {
        return [self::OWNER, self::ADMIN, self::FINANCE, self::MARKETING, self::OPERATIONS, self::ANALYST, self::DEMO];
    }

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return [
            self::OWNER => 'Full access including billing, users and connector credentials.',
            self::ADMIN => 'Everything except billing.',
            self::FINANCE => 'P&L, margin, fees, settlements, GST and the full report library.',
            self::MARKETING => 'Ads, social, GA4, customers and marketing reports.',
            self::OPERATIONS => 'Logistics, returns, RTO, inventory and catalog.',
            self::ANALYST => 'Read-only across every analytics module, no admin and no PII.',
            self::DEMO => 'Sample-data explorer with metered AI credits.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionsFor(string $role): array
    {
        return match ($role) {
            self::OWNER => PermissionRegistry::all(),

            self::ADMIN => array_values(array_filter(
                PermissionRegistry::all(),
                static fn (string $p): bool => $p !== 'admin.billing.manage',
            )),

            self::FINANCE => [
                ...PermissionRegistry::forModules(['dashboard', 'finance', 'reports', 'marketplace']),
                ...self::viewsFor(['operations', 'catalog', 'customer_intelligence']),
                'ai.chat.view', 'ai.chart_insight.view', 'ai.panel_insight.view', 'ai.insights_feed.view',
                'ai.opportunities.view', 'ai.morning_brief.view',
                'alerts.rules.view', 'alerts.events.view', 'alerts.notification_centre.view',
                'connectors.index.view', 'connectors.sync_health.view',
            ],

            self::MARKETING => [
                ...PermissionRegistry::forModules(['marketing', 'instagram', 'customer_intelligence', 'reviews']),
                ...self::viewsFor(['dashboard', 'marketplace', 'catalog']),
                'dashboard.kpi_strip.export', 'reports.library.view',
                'reports.channel_cac.view', 'reports.new_vs_repeat.view', 'reports.state_roi.view',
                'reports.cohort_retention.view', 'reports.geo_cities.view', 'reports.discount_impact.view',
                'reports.top_customers.view', 'reports.channel_cac.export', 'reports.cohort_retention.export',
                'ai.chat.view', 'ai.chart_insight.view', 'ai.panel_insight.view', 'ai.insights_feed.view',
                'ai.opportunities.view', 'ai.morning_brief.view',
                'alerts.rules.view', 'alerts.events.view', 'alerts.notification_centre.view',
                'connectors.index.view', 'connectors.sync_health.view',
            ],

            self::OPERATIONS => [
                ...PermissionRegistry::forModules(['operations', 'catalog']),
                ...self::viewsFor(['dashboard', 'marketplace', 'customer_intelligence', 'reviews']),
                'marketplace.zero_order_skus.export', 'marketplace.settlements.view',
                'reports.library.view', 'reports.inventory_health.view', 'reports.logistics_performance.view',
                'reports.order_aging.view', 'reports.reorder_replenishment.view', 'reports.stockout.view',
                'reports.returns_rto_register.view', 'reports.cod_cash_flow.view',
                'reports.inventory_health.export', 'reports.logistics_performance.export',
                'reports.order_aging.export', 'reports.returns_rto_register.export',
                'ai.chat.view', 'ai.chart_insight.view', 'ai.panel_insight.view', 'ai.insights_feed.view',
                'alerts.rules.view', 'alerts.rules.manage', 'alerts.events.view', 'alerts.notification_centre.view',
                'connectors.index.view', 'connectors.sync_health.view',
            ],

            self::ANALYST => [
                ...self::viewsFor([
                    'dashboard', 'finance', 'marketing', 'instagram', 'marketplace',
                    'operations', 'customer_intelligence', 'reviews', 'catalog', 'reports',
                ]),
                'ai.chat.view', 'ai.chart_insight.view', 'ai.panel_insight.view', 'ai.insights_feed.view',
                'ai.opportunities.view', 'ai.morning_brief.view',
                'alerts.events.view', 'alerts.notification_centre.view',
                'connectors.index.view', 'connectors.sync_health.view',
            ],

            self::DEMO => [
                ...self::viewsFor([
                    'dashboard', 'finance', 'marketing', 'instagram', 'marketplace',
                    'operations', 'customer_intelligence', 'reviews', 'catalog', 'reports',
                ]),
                'ai.chat.view', 'ai.insights_feed.view', 'ai.morning_brief.view',
                'connectors.index.view',
            ],

            default => [],
        };
    }

    /**
     * @param  list<string>  $modules
     * @return list<string>
     */
    private static function viewsFor(array $modules): array
    {
        return array_values(array_filter(
            PermissionRegistry::forModules($modules),
            static fn (string $p): bool => str_ends_with($p, '.view'),
        ));
    }
}
