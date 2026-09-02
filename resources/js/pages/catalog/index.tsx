import { Head } from '@inertiajs/react';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable } from '@/components/app/data-table';
import { BarList } from '@/components/app/bar-list';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { CHART_COLORS } from '@/components/charts/chart-primitives';
import { useWidget } from '@/hooks/use-widget';
import { formatCompactCurrency, formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Verdict } from '@/types';

interface CoverageRow {
    sku_id: number;
    sku_code: string;
    name: string;
    category: string | null;
    stock: number;
    cost_price: number;
    selling_price: number;
    stock_value: number;
    units_30d: number;
    daily_rate: number;
    days_of_cover: number;
    monthly_revenue: number;
    suggested_reorder_qty: number;
    abc_class?: 'A' | 'B' | 'C';
}

export default function Catalog() {
    const inventory = useWidget<{ rows: CoverageRow[] }>('operations/inventory');
    const reorder = useWidget<{ rows: CoverageRow[]; dead_stock: CoverageRow[]; caveat: string }>('operations/reorder');
    const stockouts = useWidget<{ rows: (CoverageRow & { days_out_of_stock: number; estimated_lost_revenue: number })[]; total_lost_revenue: number; caveat: string }>('operations/stockouts');
    const valuation = useWidget<{ rows: { category: string; sku_count: number; units: string | number; value_at_cost: string | number; value_at_retail: string | number }[]; total_at_cost: number; total_at_retail: number; total_units: number }>('operations/inventory-valuation');

    const activeSkus = inventory.data?.rows.length ?? 0;
    const outOfStock = (inventory.data?.rows ?? []).filter((row) => row.stock <= 0).length;

    return (
        <AppLayout title="Catalog & inventory" description="What you hold, what it is worth, and what to reorder">
            <Head title="Catalog" />

            <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
                {[
                    ['Active SKUs', formatNumber(activeSkus)],
                    ['Out of stock', formatNumber(outOfStock)],
                    ['Stock at cost', formatCompactCurrency(valuation.data?.total_at_cost ?? 0)],
                    ['Stock at retail', formatCompactCurrency(valuation.data?.total_at_retail ?? 0)],
                ].map(([label, value]) => (
                    <Card key={label} className="p-4">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                        <p className="mt-2 text-xl font-semibold tnum">{value}</p>
                    </Card>
                ))}
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="catalog.reorder.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Reorder plan"
                        subtitle="Days of cover, suggested quantity and ABC class"
                        widgetKey="catalog.reorder"
                        tooltip="A/B/C class ranks SKUs by their share of revenue — A is the top 80%."
                        loading={reorder.loading}
                        error={reorder.error}
                        onRetry={reorder.reload}
                        caveat={reorder.data?.caveat}
                        exportDataset="reorder"
                    >
                        <DataTable<CoverageRow>
                            searchable
                            searchPlaceholder="Search SKU…"
                            rows={reorder.data?.rows ?? []}
                            rowKey={(row) => row.sku_id}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => `${r.sku_code} ${r.name}`, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-1.5 truncate font-medium">
                                            {r.sku_code}
                                            {r.abc_class && <Badge variant={r.abc_class === 'A' ? 'good' : r.abc_class === 'B' ? 'muted' : 'outline'}>{r.abc_class}</Badge>}
                                        </p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                    </div>
                                ) },
                                { key: 'stock', header: 'Stock', align: 'right', sortable: true, value: (r) => r.stock, render: (r) => (
                                    <span className={r.stock <= 0 ? 'font-medium text-bad' : ''}>{formatNumber(r.stock)}</span>
                                ) },
                                { key: 'rate', header: 'Units/day', align: 'right', sortable: true, value: (r) => r.daily_rate, render: (r) => r.daily_rate.toFixed(2) },
                                { key: 'cover', header: 'Days cover', align: 'right', sortable: true, value: (r) => r.days_of_cover, render: (r) => (
                                    <span className={cn(r.days_of_cover < 7 ? 'font-medium text-bad' : r.days_of_cover < 14 ? 'text-warn' : '')}>
                                        {r.days_of_cover >= 999 ? '∞' : `${r.days_of_cover.toFixed(1)}d`}
                                    </span>
                                ) },
                                { key: 'reorder', header: 'Reorder', align: 'right', sortable: true, value: (r) => r.suggested_reorder_qty, render: (r) => (
                                    r.suggested_reorder_qty > 0 ? <span className="font-semibold">{formatNumber(r.suggested_reorder_qty)}</span> : <span className="text-muted-foreground">—</span>
                                ) },
                                { key: 'rev', header: 'Rev / 30d', align: 'right', sortable: true, value: (r) => r.monthly_revenue, render: (r) => formatCompactCurrency(r.monthly_revenue) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="catalog.inventory.view">
                    <ChartCard
                        title="Inventory value by category"
                        widgetKey="catalog.inventory"
                        loading={valuation.loading}
                        error={valuation.error}
                        onRetry={valuation.reload}
                        exportDataset="inventory_health"
                    >
                        <BarList
                            rows={(valuation.data?.rows ?? []).map((row, index) => ({
                                label: row.category,
                                value: Number(row.value_at_cost),
                                color: CHART_COLORS[index % CHART_COLORS.length],
                                secondary: `${formatNumber(Number(row.units))}u`,
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="catalog.stockouts.view">
                    <ChartCard
                        title="Stockout impact"
                        subtitle={`About ${formatCurrency(stockouts.data?.total_lost_revenue ?? 0)} of revenue not captured`}
                        widgetKey="catalog.stockouts"
                        loading={stockouts.loading}
                        error={stockouts.error}
                        onRetry={stockouts.reload}
                        caveat={stockouts.data?.caveat}
                        empty={(stockouts.data?.rows.length ?? 0) === 0}
                        emptyState={<p className="py-8 text-center text-xs text-muted-foreground">Nothing that was selling has run out. 🎉</p>}
                        exportDataset="stockout"
                    >
                        <DataTable
                            dense
                            rows={stockouts.data?.rows ?? []}
                            rowKey={(row) => row.sku_id}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => r.sku_code, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{r.sku_code}</p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                    </div>
                                ) },
                                { key: 'rate', header: 'Units/day', align: 'right', sortable: true, value: (r) => r.daily_rate, render: (r) => r.daily_rate.toFixed(2) },
                                { key: 'lost', header: 'Est. lost', align: 'right', sortable: true, value: (r) => r.estimated_lost_revenue, render: (r) => (
                                    <span className="font-medium text-bad">{formatCompactCurrency(r.estimated_lost_revenue)}</span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="catalog.slow_movers.view">
                    <ChartCard
                        title="Dead stock"
                        subtitle="Holding stock, selling nothing"
                        widgetKey="catalog.slow_movers"
                        loading={reorder.loading}
                        error={reorder.error}
                        onRetry={reorder.reload}
                        empty={(reorder.data?.dead_stock.length ?? 0) === 0}
                        emptyState={<p className="py-8 text-center text-xs text-muted-foreground">Every SKU holding stock is selling.</p>}
                        exportDataset="zero_order_skus"
                    >
                        <DataTable<CoverageRow>
                            dense
                            rows={reorder.data?.dead_stock ?? []}
                            rowKey={(row) => row.sku_id}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => r.sku_code, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{r.sku_code}</p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                    </div>
                                ) },
                                { key: 'stock', header: 'Stock', align: 'right', sortable: true, value: (r) => r.stock, render: (r) => formatNumber(r.stock) },
                                { key: 'value', header: 'Capital', align: 'right', sortable: true, value: (r) => r.stock_value, render: (r) => (
                                    <span className="font-medium text-warn">{formatCompactCurrency(r.stock_value)}</span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="catalog.products.view">
                <ChartCard
                    title="All SKUs"
                    subtitle="Stock, sell-through and margin per SKU"
                    widgetKey="catalog.products"
                    loading={inventory.loading}
                    error={inventory.error}
                    onRetry={inventory.reload}
                    exportDataset="inventory_health"
                >
                    <DataTable<CoverageRow>
                        searchable
                        searchPlaceholder="Search SKU or product…"
                        rows={inventory.data?.rows ?? []}
                        rowKey={(row) => row.sku_id}
                        initialSort={{ key: 'rev', direction: 'desc' }}
                        columns={[
                            { key: 'sku', header: 'SKU', value: (r) => `${r.sku_code} ${r.name}`, render: (r) => (
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{r.sku_code}</p>
                                    <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                </div>
                            ) },
                            { key: 'category', header: 'Category', value: (r) => r.category, render: (r) => r.category ?? '—' },
                            { key: 'cost', header: 'Cost', align: 'right', sortable: true, value: (r) => r.cost_price, render: (r) => formatCurrency(r.cost_price) },
                            { key: 'price', header: 'Price', align: 'right', sortable: true, value: (r) => r.selling_price, render: (r) => formatCurrency(r.selling_price) },
                            { key: 'stock', header: 'Stock', align: 'right', sortable: true, value: (r) => r.stock, render: (r) => (
                                <span className={r.stock <= 0 ? 'font-medium text-bad' : ''}>{formatNumber(r.stock)}</span>
                            ) },
                            { key: 'units', header: 'Sold / 30d', align: 'right', sortable: true, value: (r) => r.units_30d, render: (r) => formatNumber(r.units_30d) },
                            { key: 'rev', header: 'Rev / 30d', align: 'right', sortable: true, value: (r) => r.monthly_revenue, render: (r) => formatCompactCurrency(r.monthly_revenue) },
                        ]}
                    />
                </ChartCard>
            </PermissionGuard>
        </AppLayout>
    );
}
