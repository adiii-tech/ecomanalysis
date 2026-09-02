<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ConnectorController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DrilldownController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InstagramController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OperationsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SavedViewController;
use App\Http\Controllers\Api\SegmentController;
use Illuminate\Support\Facades\Route;

/*
| Every analytics endpoint is Sanctum-guarded, tenant-scoped and gated on its
| own `module.widget.action` permission, and accepts from / to / channel /
| returns_basis.
*/

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::prefix('dashboard')->name('api.dashboard.')->group(function (): void {
        Route::get('kpis', [DashboardController::class, 'kpis'])
            ->middleware('permission.widget:dashboard.kpi_strip.view')->name('kpis');

        Route::get('sales-by-channel-daily', [DashboardController::class, 'salesByChannelDaily'])
            ->middleware('permission.widget:dashboard.sales_by_channel.view')->name('sales-by-channel');

        Route::get('sales-summary', [DashboardController::class, 'salesSummary'])
            ->middleware('permission.widget:dashboard.sales_summary.view')->name('sales-summary');

        Route::get('sales-journey', [DashboardController::class, 'salesJourney'])
            ->middleware('permission.widget:dashboard.sales_journey.view')->name('sales-journey');

        Route::get('revenue-margin-trend', [DashboardController::class, 'revenueMarginTrend'])
            ->middleware('permission.widget:dashboard.net_sales_vs_margin.view')->name('revenue-margin-trend');

        Route::get('payment-mode-economics', [DashboardController::class, 'paymentModeEconomics'])
            ->middleware('permission.widget:dashboard.payment_mode_economics.view')->name('payment-mode');

        Route::get('channel-mix', [DashboardController::class, 'channelMix'])
            ->middleware('permission.widget:dashboard.channel_mix.view')->name('channel-mix');

        Route::get('top-states', [DashboardController::class, 'topStates'])
            ->middleware('permission.widget:dashboard.top_states.view')->name('top-states');

        Route::get('top-rto-states', [DashboardController::class, 'topRtoStates'])
            ->middleware('permission.widget:dashboard.top_rto_states.view')->name('top-rto-states');

        Route::get('state-action-matrix', [DashboardController::class, 'stateActionMatrix'])
            ->middleware('permission.widget:dashboard.state_action_matrix.view')->name('state-action-matrix');

        Route::get('top-categories', [DashboardController::class, 'topCategories'])
            ->middleware('permission.widget:dashboard.top_categories.view')->name('top-categories');

        Route::get('sku-pareto', [DashboardController::class, 'skuPareto'])
            ->middleware('permission.widget:dashboard.sku_pareto.view')->name('sku-pareto');

        Route::get('returns-by-channel', [DashboardController::class, 'returnsByChannel'])
            ->middleware('permission.widget:dashboard.returns_by_channel.view')->name('returns-by-channel');

        Route::get('top-return-reasons', [DashboardController::class, 'topReturnReasons'])
            ->middleware('permission.widget:dashboard.top_return_reasons.view')->name('top-return-reasons');

        Route::get('high-return-products', [DashboardController::class, 'highReturnProducts'])
            ->middleware('permission.widget:dashboard.top_return_skus.view')->name('high-return-products');

        Route::get('top-loss-orders', [DashboardController::class, 'topLossOrders'])
            ->middleware('permission.widget:dashboard.loss_orders.view')->name('top-loss-orders');

        Route::get('recent-orders', [DashboardController::class, 'recentOrders'])
            ->middleware('permission.widget:dashboard.recent_orders.view')->name('recent-orders');

        Route::get('health-flags', [DashboardController::class, 'healthFlags'])
            ->middleware('permission.widget:dashboard.health_flags.view')->name('health-flags');

        Route::get('revenue-pacing', [DashboardController::class, 'revenuePacing'])
            ->middleware('permission.widget:dashboard.revenue_pacing.view')->name('revenue-pacing');

        Route::get('critical-inventory', [DashboardController::class, 'criticalInventory'])
            ->middleware('permission.widget:dashboard.critical_inventory.view')->name('critical-inventory');

        Route::get('ltv-cohort', [DashboardController::class, 'ltvCohort'])
            ->middleware('permission.widget:dashboard.ltv_cohort.view')->name('ltv-cohort');
    });

    Route::prefix('finance')->name('api.finance.')->group(function (): void {
        Route::get('kpis', [FinanceController::class, 'kpis'])->middleware('permission.widget:finance.kpi_strip.view')->name('kpis');
        Route::get('sales-over-time', [FinanceController::class, 'salesOverTime'])->middleware('permission.widget:finance.sales_over_time.view')->name('sales-over-time');
        Route::get('aov-trend', [FinanceController::class, 'aovTrend'])->middleware('permission.widget:finance.aov_trend.view')->name('aov-trend');
        Route::get('revenue-breakdown', [FinanceController::class, 'revenueBreakdown'])->middleware('permission.widget:finance.revenue_breakdown.view')->name('revenue-breakdown');
        Route::get('geographic-sales', [FinanceController::class, 'geographicSales'])->middleware('permission.widget:finance.geographic_sales.view')->name('geographic-sales');
        Route::get('top-skus', [FinanceController::class, 'topSkus'])->middleware('permission.widget:finance.top_skus.view')->name('top-skus');
        Route::get('payment-method-split', [FinanceController::class, 'paymentMethodSplit'])->middleware('permission.widget:finance.payment_method_split.view')->name('payment-method-split');
        Route::get('refunds', [FinanceController::class, 'refunds'])->middleware('permission.widget:finance.refunds.view')->name('refunds');
        Route::get('transactions', [FinanceController::class, 'transactions'])->middleware('permission.widget:finance.transaction_ledger.view')->name('transactions');
        Route::get('pnl', [FinanceController::class, 'pnl'])->middleware('permission.widget:finance.pnl.view')->name('pnl');
        Route::get('cash-flow', [FinanceController::class, 'cashFlow'])->middleware('permission.widget:finance.cash_flow.view')->name('cash-flow');
        Route::get('gst', [FinanceController::class, 'gst'])->middleware('permission.widget:finance.gst.view')->name('gst');
    });

    Route::prefix('marketing')->name('api.marketing.')->group(function (): void {
        Route::get('kpis', [MarketingController::class, 'kpis'])->middleware('permission.widget:marketing.kpi_strip.view')->name('kpis');
        Route::get('campaigns', [MarketingController::class, 'campaigns'])->middleware('permission.widget:marketing.campaign_table.view')->name('campaigns');
        Route::get('insights', [MarketingController::class, 'insights'])->middleware('permission.widget:marketing.insights_cards.view')->name('insights');
        Route::get('trend', [MarketingController::class, 'trend'])->middleware('permission.widget:marketing.trend.view')->name('trend');
        Route::get('attribution-gap', [MarketingController::class, 'attributionGap'])->middleware('permission.widget:marketing.attribution_gap.view')->name('attribution-gap');
        Route::get('conversion-funnel', [MarketingController::class, 'conversionFunnel'])->middleware('permission.widget:marketing.conversion_funnel.view')->name('conversion-funnel');
        Route::get('channel-performance', [MarketingController::class, 'channelPerformance'])->middleware('permission.widget:marketing.channel_performance.view')->name('channel-performance');
        Route::get('abandoned-carts', [MarketingController::class, 'abandonedCarts'])->middleware('permission.widget:marketing.abandoned_carts.view')->name('abandoned-carts');
        Route::get('buyer-persona', [MarketingController::class, 'buyerPersona'])->middleware('permission.widget:marketing.buyer_persona.view')->name('buyer-persona');
        Route::get('realtime-active-users', [MarketingController::class, 'realtimeActiveUsers'])->middleware('permission.widget:marketing.realtime_users.view')->name('realtime');
        Route::get('top-pages', [MarketingController::class, 'topPages'])->middleware('permission.widget:marketing.top_pages.view')->name('top-pages');
        Route::get('top-cities', [MarketingController::class, 'topCities'])->middleware('permission.widget:marketing.top_cities.view')->name('top-cities');
        Route::get('active-users-by-country', [MarketingController::class, 'activeUsersByCountry'])->middleware('permission.widget:marketing.active_users_by_country.view')->name('users-by-country');
        Route::get('product-performance', [MarketingController::class, 'productPerformance'])->middleware('permission.widget:marketing.product_performance.view')->name('product-performance');
        Route::get('placements', [MarketingController::class, 'placements'])->middleware('permission.widget:marketing.placements.view')->name('placements');
        Route::get('spend-by-objective', [MarketingController::class, 'spendByObjective'])->middleware('permission.widget:marketing.spend_by_objective.view')->name('spend-by-objective');
        Route::get('creative-fatigue', [MarketingController::class, 'creativeFatigue'])->middleware('permission.widget:marketing.creative_fatigue.view')->name('creative-fatigue');
        Route::get('creative-library', [MarketingController::class, 'creativeLibrary'])->middleware('permission.widget:marketing.creative_library.view')->name('creative-library');
        Route::get('budget-pacing', [MarketingController::class, 'budgetPacing'])->middleware('permission.widget:marketing.budget_pacing.view')->name('budget-pacing');
        Route::get('mer', [MarketingController::class, 'mer'])->middleware('permission.widget:marketing.mer.view')->name('mer');
        Route::get('utm-analysis', [MarketingController::class, 'utmAnalysis'])->middleware('permission.widget:marketing.utm_analysis.view')->name('utm-analysis');
    });

    Route::prefix('marketplace')->name('api.marketplace.')->group(function (): void {
        Route::get('kpis', [MarketplaceController::class, 'kpis'])->middleware('permission.widget:marketplace.kpi_strip.view')->name('kpis');
        Route::get('today-snapshot', [MarketplaceController::class, 'todaySnapshot'])->middleware('permission.widget:marketplace.today_snapshot.view')->name('today-snapshot');
        Route::get('sales-summary', [MarketplaceController::class, 'salesSummary'])->middleware('permission.widget:marketplace.sales_summary.view')->name('sales-summary');
        Route::get('revenue-trend', [MarketplaceController::class, 'revenueTrend'])->middleware('permission.widget:marketplace.sales_trend.view')->name('revenue-trend');
        Route::get('channel-comparison', [MarketplaceController::class, 'channelComparison'])->middleware('permission.widget:marketplace.channel_comparison.view')->name('channel-comparison');
        Route::get('top-categories', [MarketplaceController::class, 'topCategories'])->middleware('permission.widget:marketplace.top_categories.view')->name('top-categories');
        Route::get('top-products', [MarketplaceController::class, 'topProducts'])->middleware('permission.widget:marketplace.top_products.view')->name('top-products');
        Route::get('top-products-by-channel', [MarketplaceController::class, 'topProductsByChannel'])->middleware('permission.widget:marketplace.products_by_channel.view')->name('products-by-channel');
        Route::get('order-status', [MarketplaceController::class, 'orderStatus'])->middleware('permission.widget:marketplace.order_status.view')->name('order-status');
        Route::get('payment-split', [MarketplaceController::class, 'paymentSplit'])->middleware('permission.widget:marketplace.payment_split.view')->name('payment-split');
        Route::get('top-states', [MarketplaceController::class, 'topStates'])->middleware('permission.widget:marketplace.top_states.view')->name('top-states');
        Route::get('channel-returns', [MarketplaceController::class, 'channelReturns'])->middleware('permission.widget:marketplace.channel_returns.view')->name('channel-returns');
        Route::get('top-return-reasons', [MarketplaceController::class, 'topReturnReasons'])->middleware('permission.widget:marketplace.top_return_reasons.view')->name('top-return-reasons');
        Route::get('inventory/zero-orders', [MarketplaceController::class, 'zeroOrderSkus'])->middleware('permission.widget:marketplace.zero_order_skus.view')->name('zero-orders');
        Route::get('inventory/fast-moving', [MarketplaceController::class, 'fastMoving'])->middleware('permission.widget:marketplace.fast_moving.view')->name('fast-moving');
        Route::get('inventory/valuation', [MarketplaceController::class, 'inventoryValuation'])->middleware('permission.widget:marketplace.inventory_valuation.view')->name('valuation');
        Route::get('recent-orders', [MarketplaceController::class, 'recentOrders'])->middleware('permission.widget:marketplace.recent_orders.view')->name('recent-orders');
        Route::get('order-rows', [MarketplaceController::class, 'orderRows'])->middleware('permission.widget:marketplace.recent_orders.view')->name('order-rows');
        Route::get('settlements', [MarketplaceController::class, 'settlements'])->middleware('permission.widget:marketplace.settlements.view')->name('settlements');
        Route::get('buybox', [MarketplaceController::class, 'buybox'])->middleware('permission.widget:marketplace.buybox.view')->name('buybox');
        Route::get('price-competitiveness', [MarketplaceController::class, 'priceCompetitiveness'])->middleware('permission.widget:marketplace.price_competitiveness.view')->name('price-competitiveness');
    });

    Route::prefix('operations')->name('api.operations.')->group(function (): void {
        Route::get('kpis', [OperationsController::class, 'kpis'])->middleware('permission.widget:operations.kpi_strip.view')->name('kpis');
        Route::get('returns/kpis', [OperationsController::class, 'returnsKpis'])->middleware('permission.widget:operations.returns_kpis.view')->name('returns-kpis');
        Route::get('returns/by-reason', [OperationsController::class, 'returnsByReason'])->middleware('permission.widget:operations.returns_by_reason.view')->name('returns-by-reason');
        Route::get('returns/by-channel', [OperationsController::class, 'returnsByChannel'])->middleware('permission.widget:operations.returns_by_channel.view')->name('returns-by-channel');
        Route::get('returns/trend', [OperationsController::class, 'returnsTrend'])->middleware('permission.widget:operations.returns_trend.view')->name('returns-trend');
        Route::get('returns/rows', [OperationsController::class, 'returnRows'])->middleware('permission.widget:operations.returns_by_reason.view')->name('return-rows');
        Route::get('shipment-status', [OperationsController::class, 'shipmentStatus'])->middleware('permission.widget:operations.shipment_status.view')->name('shipment-status');
        Route::get('courier-scorecard', [OperationsController::class, 'courierScorecard'])->middleware('permission.widget:operations.courier_scorecard.view')->name('courier-scorecard');
        Route::get('rto-by-state', [OperationsController::class, 'rtoByState'])->middleware('permission.widget:operations.rto_by_state.view')->name('rto-by-state');
        Route::get('delivery-funnel', [OperationsController::class, 'deliveryFunnel'])->middleware('permission.widget:operations.delivery_funnel.view')->name('delivery-funnel');
        Route::get('delivery-performance', [OperationsController::class, 'deliveryPerformance'])->middleware('permission.widget:operations.delivery_performance.view')->name('delivery-performance');
        Route::get('ndr-queue', [OperationsController::class, 'ndrQueue'])->middleware('permission.widget:operations.ndr_queue.view')->name('ndr-queue');
        Route::get('order-aging', [OperationsController::class, 'orderAging'])->middleware('permission.widget:operations.order_aging.view')->name('order-aging');
        Route::get('pincode-risk', [OperationsController::class, 'pincodeRisk'])->middleware('permission.widget:operations.pincode_risk.view')->name('pincode-risk');
        Route::get('inventory', [OperationsController::class, 'inventory'])->middleware('permission.widget:catalog.inventory.view')->name('inventory');
        Route::get('reorder', [OperationsController::class, 'reorder'])->middleware('permission.widget:catalog.reorder.view')->name('reorder');
        Route::get('stockouts', [OperationsController::class, 'stockouts'])->middleware('permission.widget:catalog.stockouts.view')->name('stockouts');
        Route::get('inventory-valuation', [OperationsController::class, 'inventoryValuation'])->middleware('permission.widget:catalog.inventory.view')->name('inventory-valuation');
    });

    Route::prefix('customers')->name('api.customers.')->group(function (): void {
        Route::get('kpis', [CustomerController::class, 'kpis'])->middleware('permission.widget:customer_intelligence.kpi_strip.view')->name('kpis');
        Route::get('list', [CustomerController::class, 'list'])->middleware('permission.widget:customer_intelligence.top_customers.view')->name('list');
        Route::get('customer/{customer}', [CustomerController::class, 'show'])->middleware('permission.widget:customer_intelligence.customer_360.view')->name('show');
        Route::get('rfm', [CustomerController::class, 'rfm'])->middleware('permission.widget:customer_intelligence.rfm.view')->name('rfm');
        Route::get('cohorts', [CustomerController::class, 'cohorts'])->middleware('permission.widget:customer_intelligence.cohorts.view')->name('cohorts');
        Route::get('repeat-metrics', [CustomerController::class, 'repeatMetrics'])->middleware('permission.widget:customer_intelligence.repeat_metrics.view')->name('repeat-metrics');
        Route::get('churn', [CustomerController::class, 'churn'])->middleware('permission.widget:customer_intelligence.churn.view')->name('churn');
        Route::get('vip', [CustomerController::class, 'vip'])->middleware('permission.widget:customer_intelligence.vip.view')->name('vip');
        Route::get('ltv-distribution', [CustomerController::class, 'ltvDistribution'])->middleware('permission.widget:customer_intelligence.ltv_distribution.view')->name('ltv-distribution');
        Route::get('purchase-interval', [CustomerController::class, 'purchaseInterval'])->middleware('permission.widget:customer_intelligence.purchase_interval.view')->name('purchase-interval');
        Route::get('per-customer-returns', [CustomerController::class, 'serialReturners'])->middleware('permission.widget:customer_intelligence.serial_returners.view')->name('serial-returners');
        Route::get('geo', [CustomerController::class, 'geo'])->middleware('permission.widget:customer_intelligence.geo.view')->name('geo');
    });

    Route::prefix('reviews')->name('api.reviews.')->group(function (): void {
        Route::get('summary', [CustomerController::class, 'reviewSummary'])->middleware('permission.widget:reviews.summary.view')->name('summary');
        Route::get('recent', [CustomerController::class, 'recentReviews'])->middleware('permission.widget:reviews.recent.view')->name('recent');
        Route::get('return-correlation', [CustomerController::class, 'reviewReturnCorrelation'])->middleware('permission.widget:reviews.return_correlation.view')->name('return-correlation');
        Route::get('trend', [ReviewController::class, 'trend'])->middleware('permission.widget:reviews.trend.view')->name('trend');
        Route::get('top-rated', [ReviewController::class, 'topRated'])->middleware('permission.widget:reviews.top_rated.view')->name('top-rated');
        Route::get('worst-rated', [ReviewController::class, 'worstRated'])->middleware('permission.widget:reviews.worst_rated.view')->name('worst-rated');
        Route::get('sentiment', [ReviewController::class, 'sentiment'])->middleware('permission.widget:reviews.sentiment.view')->name('sentiment');
        Route::get('themes', [ReviewController::class, 'themes'])->middleware('permission.widget:reviews.themes.view')->name('themes');
    });

    Route::prefix('catalog')->name('api.catalog.')->group(function (): void {
        Route::get('kpis', [CatalogController::class, 'kpis'])->middleware('permission.widget:catalog.kpi_strip.view')->name('kpis');
        Route::get('products', [CatalogController::class, 'products'])->middleware('permission.widget:catalog.products.view')->name('products');
        Route::get('best-sellers', [CatalogController::class, 'bestSellers'])->middleware('permission.widget:catalog.best_sellers.view')->name('best-sellers');
        Route::get('slow-movers', [CatalogController::class, 'slowMovers'])->middleware('permission.widget:catalog.slow_movers.view')->name('slow-movers');
        Route::get('margin', [CatalogController::class, 'margin'])->middleware('permission.widget:catalog.margin.view')->name('margin');
        Route::get('skus/{sku}/cost-history', [CatalogController::class, 'costHistory'])->middleware('permission.widget:catalog.cost_editor.view')->name('cost-history');
        Route::put('skus/{sku}/cost', [CatalogController::class, 'updateCost'])->middleware('permission.widget:catalog.cost_editor.manage')->name('cost-update');
    });

    Route::prefix('connectors')->name('api.connectors.')->group(function (): void {
        Route::get('/', [ConnectorController::class, 'index'])->middleware('permission.widget:connectors.index.view')->name('index');
        Route::get('sync-runs', [ConnectorController::class, 'syncRuns'])->middleware('permission.widget:connectors.sync_health.view')->name('sync-runs');
        Route::post('{connector}/connect', [ConnectorController::class, 'connect'])->middleware('permission.widget:connectors.credentials.manage')->name('connect');
        Route::post('{connector}/disconnect', [ConnectorController::class, 'disconnect'])->middleware('permission.widget:connectors.credentials.manage')->name('disconnect');
        Route::post('{connector}/test', [ConnectorController::class, 'test'])->middleware('permission.widget:connectors.sync_health.manage')->name('test');
        Route::get('{connector}/resources/{key}', [ConnectorController::class, 'resources'])->middleware('permission.widget:connectors.credentials.manage')->name('resources');
        Route::post('{connector}/select', [ConnectorController::class, 'select'])->middleware('permission.widget:connectors.credentials.manage')->name('select');
        Route::post('{connector}/sync', [ConnectorController::class, 'sync'])->middleware(['permission.widget:connectors.sync_health.manage', 'throttle:manual-sync'])->name('sync');
    });

    /*
    | Row-level drill-down. Any widget can open the orders behind its number,
    | filtered by one of a fixed set of dimensions.
    */
    Route::prefix('drilldown')->name('api.drilldown.')->middleware('permission.widget:dashboard.recent_orders.view')->group(function (): void {
        Route::get('dimensions', [DrilldownController::class, 'dimensions'])->name('dimensions');
        Route::get('orders', [DrilldownController::class, 'orders'])->name('orders');
        Route::get('orders/{order}', [DrilldownController::class, 'order'])->whereNumber('order')->name('order');
    });

    /*
    | The customer explorer. Segment rules are built from a whitelisted field
    | registry, so no user input ever reaches the query as SQL.
    */
    Route::prefix('segments')->name('api.segments.')->group(function (): void {
        Route::get('/', [SegmentController::class, 'index'])->middleware('permission.widget:customer_intelligence.segments.view')->name('index');
        Route::post('preview', [SegmentController::class, 'preview'])->middleware('permission.widget:customer_intelligence.explorer.view')->name('preview');
        Route::post('/', [SegmentController::class, 'store'])->middleware('permission.widget:customer_intelligence.segments.manage')->name('store');
        Route::post('{segment}/refresh', [SegmentController::class, 'refresh'])->whereNumber('segment')->middleware('permission.widget:customer_intelligence.segments.view')->name('refresh');
        Route::delete('{segment}', [SegmentController::class, 'destroy'])->whereNumber('segment')->middleware('permission.widget:customer_intelligence.segments.manage')->name('destroy');
        Route::get('{segment}/export/{destination}', [SegmentController::class, 'export'])->whereNumber('segment')->middleware(['permission.widget:customer_intelligence.segments.export', 'throttle:exports'])->name('export');
    });

    /*
    | Saved filter sets. Private by default; sharing makes a view applicable by
    | anyone on the tenant but still only editable by its owner.
    */
    Route::prefix('saved-views')->name('api.saved-views.')->group(function (): void {
        Route::get('/', [SavedViewController::class, 'index'])->name('index');
        Route::post('/', [SavedViewController::class, 'store'])->name('store');
        Route::put('{view}', [SavedViewController::class, 'update'])->whereNumber('view')->name('update');
        Route::delete('{view}', [SavedViewController::class, 'destroy'])->whereNumber('view')->name('destroy');
    });

    /*
    | The setup wizard. Progress is derived from real data, so these endpoints
    | only store what the user typed and never a "done" flag.
    */
    Route::prefix('onboarding')->name('api.onboarding.')->group(function (): void {
        Route::get('/', [OnboardingController::class, 'show'])->name('show');
        Route::put('business', [OnboardingController::class, 'saveBusiness'])->name('business');
        Route::post('dismiss', [OnboardingController::class, 'dismiss'])->name('dismiss');
    });

    /*
    | The signed-in user's own account. No widget permission gates these — every
    | user may manage themselves.
    */
    Route::prefix('profile')->name('api.profile.')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('password', [ProfileController::class, 'updatePassword'])->name('password');
        Route::post('tokens', [ProfileController::class, 'createToken'])->name('tokens.create');
        Route::delete('tokens/{token}', [ProfileController::class, 'revokeToken'])->whereNumber('token')->name('tokens.revoke');
        Route::delete('sessions', [ProfileController::class, 'signOutOtherDevices'])->name('sessions.clear');
    });

    /*
    | The report library. Each report gates on its own reports.{key}.view
    | permission inside the controller, since the registry owns that mapping.
    */
    Route::prefix('reports')->name('api.reports.')->group(function (): void {
        Route::get('/', [ReportController::class, 'index'])->middleware('permission.widget:reports.library.view')->name('index');
        Route::get('{report}', [ReportController::class, 'show'])->name('show');
        Route::post('{report}/favourite', [ReportController::class, 'toggleFavourite'])->name('favourite');
        Route::get('{report}/shares', [ReportController::class, 'shares'])->name('shares');
        Route::post('{report}/share', [ReportController::class, 'share'])->name('share');
        Route::delete('{report}/shares/{share}', [ReportController::class, 'revokeShare'])->whereNumber('share')->name('share.revoke');
        Route::get('{report}/schedules', [ReportController::class, 'schedules'])->name('schedules');
        Route::post('{report}/schedules', [ReportController::class, 'saveSchedule'])->name('schedule.save');
        Route::delete('{report}/schedules/{schedule}', [ReportController::class, 'deleteSchedule'])->whereNumber('schedule')->name('schedule.delete');
    });

    /*
    | Exports. Gating happens per dataset inside the controller, because the
    | permission that applies is the one belonging to the widget it came from.
    */
    Route::prefix('export')->name('api.export.')->middleware('throttle:exports')->group(function (): void {
        Route::get('/', [ExportController::class, 'index'])->name('index');
        Route::get('{dataset}/{format}', [ExportController::class, 'download'])->name('download');
    });

    Route::prefix('instagram')->name('api.instagram.')->group(function (): void {
        Route::get('kpis', [InstagramController::class, 'kpis'])->middleware('permission.widget:instagram.kpi_strip.view')->name('kpis');
        Route::get('account-trend', [InstagramController::class, 'accountTrend'])->middleware('permission.widget:instagram.growth_reach.view')->name('account-trend');
        Route::get('engagement-trend', [InstagramController::class, 'engagementTrend'])->middleware('permission.widget:instagram.engagement.view')->name('engagement');
        Route::get('content', [InstagramController::class, 'content'])->middleware('permission.widget:instagram.content_performance.view')->name('content');
        Route::get('reels-vs-feed', [InstagramController::class, 'reelsVsFeed'])->middleware('permission.widget:instagram.reels_vs_feed.view')->name('reels-vs-feed');
        Route::get('stories', [InstagramController::class, 'stories'])->middleware('permission.widget:instagram.stories.view')->name('stories');
        Route::get('audience', [InstagramController::class, 'audience'])->middleware('permission.widget:instagram.audience.view')->name('audience');
        Route::get('best-time', [InstagramController::class, 'bestTime'])->middleware('permission.widget:instagram.best_time.view')->name('best-time');
        Route::get('hashtags', [InstagramController::class, 'hashtags'])->middleware('permission.widget:instagram.hashtags.view')->name('hashtags');
        Route::get('sales-correlation', [InstagramController::class, 'salesCorrelation'])->middleware('permission.widget:instagram.sales_correlation.view')->name('sales-correlation');
        Route::get('fb-page', [InstagramController::class, 'facebookPage'])->middleware('permission.widget:instagram.fb_page.view')->name('fb-page');
    });

    Route::prefix('ai')->name('api.ai.')->middleware('throttle:ai')->group(function (): void {
        Route::get('status', [AiController::class, 'status'])->middleware('permission.widget:ai.chat.view')->name('status');
        Route::post('ask/chat', [AiController::class, 'chat'])->middleware('permission.widget:ai.chat.view')->name('chat');
        Route::get('ask/history', [AiController::class, 'sessions'])->middleware('permission.widget:ai.chat.view')->name('history');
        Route::get('ask/session/{session}', [AiController::class, 'session'])->middleware('permission.widget:ai.chat.view')->name('session');
        Route::patch('ask/session/{session}', [AiController::class, 'renameSession'])->middleware('permission.widget:ai.chat.manage')->name('session-rename');
        Route::delete('ask/session/{session}', [AiController::class, 'deleteSession'])->middleware('permission.widget:ai.chat.manage')->name('session-delete');
        Route::post('ask/session/{session}/share', [AiController::class, 'share'])->middleware('permission.widget:ai.share.manage')->name('session-share');
        Route::post('chart-insight', [AiController::class, 'chartInsight'])->middleware('permission.widget:ai.chart_insight.view')->name('chart-insight');
    });

    Route::prefix('alerts')->name('api.alerts.')->group(function (): void {
        Route::get('schema', [AlertController::class, 'schema'])->middleware('permission.widget:alerts.rules.view')->name('schema');
        Route::get('rules', [AlertController::class, 'index'])->middleware('permission.widget:alerts.rules.view')->name('rules');
        Route::post('rules', [AlertController::class, 'store'])->middleware('permission.widget:alerts.rules.manage')->name('rules-store');
        Route::put('rules/{rule}', [AlertController::class, 'update'])->middleware('permission.widget:alerts.rules.manage')->name('rules-update');
        Route::delete('rules/{rule}', [AlertController::class, 'destroy'])->middleware('permission.widget:alerts.rules.manage')->name('rules-delete');
        Route::post('rules/{rule}/mute', [AlertController::class, 'mute'])->middleware('permission.widget:alerts.rules.manage')->name('rules-mute');
        Route::post('test', [AlertController::class, 'test'])->middleware('permission.widget:alerts.rules.manage')->name('test');
        Route::get('events', [AlertController::class, 'events'])->middleware('permission.widget:alerts.events.view')->name('events');
        Route::post('events/read', [AlertController::class, 'markRead'])->middleware('permission.widget:alerts.events.view')->name('events-read');
        Route::post('events/{event}/snooze', [AlertController::class, 'snooze'])->middleware('permission.widget:alerts.events.view')->name('events-snooze');
    });

    Route::prefix('admin')->name('api.admin.')->group(function (): void {
        Route::get('users', [AdminController::class, 'users'])->middleware('permission.widget:admin.users.view')->name('users');
        Route::post('users/invite', [AdminController::class, 'invite'])->middleware('permission.widget:admin.users.manage')->name('invite');
        Route::post('invitations/{invitation}/resend', [AdminController::class, 'resendInvite'])->middleware('permission.widget:admin.users.manage')->name('invite-resend');
        Route::delete('invitations/{invitation}', [AdminController::class, 'revokeInvite'])->middleware('permission.widget:admin.users.manage')->name('invite-revoke');
        Route::put('users/{user}', [AdminController::class, 'updateUser'])->middleware('permission.widget:admin.users.manage')->name('user-update');
        Route::post('users/{user}/password', [AdminController::class, 'resetPassword'])->middleware('permission.widget:admin.users.manage')->name('user-password');
        Route::get('users/{user}/permissions', [AdminController::class, 'permissions'])->middleware('permission.widget:admin.permissions.view')->name('user-permissions');
        Route::put('users/{user}/permissions', [AdminController::class, 'updatePermissions'])->middleware('permission.widget:admin.permissions.manage')->name('user-permissions-update');
        Route::post('users/{user}/permissions/reset', [AdminController::class, 'resetPermissions'])->middleware('permission.widget:admin.permissions.manage')->name('user-permissions-reset');
        Route::get('roles', [AdminController::class, 'roles'])->middleware('permission.widget:admin.roles.view')->name('roles');
        Route::get('roles/{role}', [AdminController::class, 'role'])->middleware('permission.widget:admin.roles.view')->name('role');
        Route::post('roles', [AdminController::class, 'saveRole'])->middleware('permission.widget:admin.roles.manage')->name('role-create');
        Route::put('roles/{role}', [AdminController::class, 'saveRole'])->middleware('permission.widget:admin.roles.manage')->name('role-update');
        Route::get('audit', [AdminController::class, 'audit'])->middleware('permission.widget:admin.audit.view')->name('audit');
        Route::get('settings', [AdminController::class, 'settings'])->middleware('permission.widget:admin.settings.view')->name('settings');
        Route::put('settings', [AdminController::class, 'saveSettings'])->middleware('permission.widget:admin.settings.manage')->name('settings-save');
    });
});
