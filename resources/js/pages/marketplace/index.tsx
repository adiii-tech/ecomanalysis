import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip as RTooltip, XAxis, YAxis } from 'recharts';
import { AppLayout } from '@/layouts/app-layout';
import { KpiStrip } from '@/components/app/kpi-card';
import { StatStrip } from '@/components/app/stat-strip';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable } from '@/components/app/data-table';
import { BarList } from '@/components/app/bar-list';
import { SalesSummaryTable, type SummaryRow } from '@/components/dashboard/sales-summary-table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card } from '@/components/ui/card';
import { AXIS_PROPS, CHART_COLORS, ChartLegend, ChartTooltip, GRID_PROPS, axisCurrency, axisDate } from '@/components/charts/chart-primitives';
import { useWidget } from '@/hooks/use-widget';
import { formatCompactCurrency, formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import type { Metric, Verdict } from '@/types';

export default function Marketplace() {
    const [metric, setMetric] = useState<'invoiced_sales' | 'net_sales' | 'items_count'>('invoiced_sales');

    const snapshot = useWidget<Record<string, unknown>>('marketplace/today-snapshot');
    const kpis = useWidget<Metric[]>('marketplace/kpis');
    const trend = useWidget<{ series: Record<string, number | string>[]; channels: { code: string; name: string; color: string }[] }>('marketplace/revenue-trend', { metric });
    const comparison = useWidget<{ rows: { name: string; code: string; color: string | null; orders: number; net_sales: number; share_pct: number; margin_pct: number }[]; verdict: Verdict }>('marketplace/channel-comparison');
    const summary = useWidget<{ rows: SummaryRow[]; verdict: Verdict }>('marketplace/sales-summary');
    const categories = useWidget<{ rows: { category: string; net_sales: number; share_pct: number; margin_pct: number }[] }>('marketplace/top-categories');
    const status = useWidget<{ rows: { status: string; label: string; count: number; share_pct: number }[] }>('marketplace/order-status');
    const states = useWidget<{ rows: { state: string; orders: number; net_sales: number; share_pct: number }[] }>('marketplace/top-states');
    const products = useWidget<{ rows: { sku_code: string; name: string; units: string | number; net_sales: number; margin_pct: number; share_pct: number }[] }>('marketplace/top-products');
    const matrix = useWidget<{ rows: Record<string, string | number>[]; channels: { code: string; name: string; color: string | null }[] }>('marketplace/top-products-by-channel');
    const channelReturns = useWidget<{ rows: { name: string; color: string | null; orders: number; returns_total: number; return_pct: number }[] }>('marketplace/channel-returns');
    const reasons = useWidget<{ rows: { label: string; count: number; refund_amount: number }[] }>('marketplace/top-return-reasons');
    const zeroOrders = useWidget<{ rows: { sku_code: string; name: string; stock: string | number; capital_held: string | number }[]; sku_count: number; capital_held: number; verdict: Verdict }>('marketplace/inventory/zero-orders');
    const fastMoving = useWidget<{ rows: { sku_code: string; name: string; units_30d: number; daily_rate: number; days_of_cover: number }[] }>('marketplace/inventory/fast-moving');
    const valuation = useWidget<{ rows: { category: string; sku_count: number; units: string | number; value_at_cost: string | number }[]; total_at_cost: number; total_units: number }>('marketplace/inventory/valuation');
    const settlements = useWidget<{ rows: unknown[]; caveat?: unknown; expected_total?: number; received_total?: number; variance_total?: number }>('marketplace/settlements');
    const buybox = useWidget<{ rows: unknown[]; caveat: unknown }>('marketplace/buybox');
    const pricing = useWidget<{ rows: { sku_code: string; name: string; mrp: number; cheapest_channel: string | null; price_spread: number; channels: { channel_name: string; realised_price: number; discount_from_mrp_pct: number }[] }[]; caveat: string }>('marketplace/price-competitiveness');
    const orders = useWidget<{ rows: { id: number; order_number: string; channel: { name: string; color: string | null } | null; status_label: string; payment_mode: string; net_amount: number; contribution_margin: number }[] }>('marketplace/recent-orders');

    return (
        <AppLayout title="Marketplace" description="Amazon, Flipkart, Myntra and the rest — after commission">
            <Head title="Marketplace" />

            <PermissionGuard permission="marketplace.today_snapshot.view">
                <div className="space-y-1.5">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                        Today, against yesterday — independent of the date filter above
                    </p>
                    <StatStrip stats={snapshot.data ?? null} loading={snapshot.loading} columns={5} />
                </div>
            </PermissionGuard>

            <PermissionGuard permission="marketplace.kpi_strip.view">
                <KpiStrip metrics={kpis.data} loading={kpis.loading} columns={6} />
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="marketplace.sales_trend.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Sales by marketplace"
                        subtitle="Daily, stacked by channel"
                        widgetKey="marketplace.sales_trend"
                        loading={trend.loading}
                        error={trend.error}
                        onRetry={trend.reload}
                        insightPayload={trend.data}
                        tabs={
                            <Tabs value={metric} onValueChange={(value) => setMetric(value as typeof metric)}>
                                <TabsList>
                                    <TabsTrigger value="invoiced_sales">Sales</TabsTrigger>
                                    <TabsTrigger value="net_sales">Net</TabsTrigger>
                                    <TabsTrigger value="items_count">Items</TabsTrigger>
                                </TabsList>
                            </Tabs>
                        }
                    >
                        <ResponsiveContainer width="100%" height={260}>
                            <BarChart data={trend.data?.series ?? []} margin={{ top: 4, right: 4, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={26} />
                                <YAxis {...AXIS_PROPS} tickFormatter={metric === 'items_count' ? undefined : axisCurrency} width={54} />
                                <RTooltip content={<ChartTooltip format={metric === 'items_count' ? 'number' : 'currency'} />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                {(trend.data?.channels ?? []).map((channel, index) => (
                                    <Bar
                                        key={channel.code}
                                        dataKey={channel.code}
                                        name={channel.name}
                                        stackId="c"
                                        fill={channel.color ?? CHART_COLORS[index % CHART_COLORS.length]}
                                        radius={index === (trend.data?.channels.length ?? 1) - 1 ? [3, 3, 0, 0] : 0}
                                        isAnimationActive={false}
                                    />
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                        <ChartLegend items={(trend.data?.channels ?? []).map((c, i) => ({ label: c.name, color: c.color ?? CHART_COLORS[i % CHART_COLORS.length] }))} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketplace.channel_comparison.view">
                    <ChartCard
                        title="Channel comparison"
                        subtitle="Share of net sales and margin"
                        widgetKey="marketplace.channel_comparison"
                        loading={comparison.loading}
                        error={comparison.error}
                        onRetry={comparison.reload}
                        verdict={comparison.data?.verdict}
                        exportDataset="channel_scorecard"
                    >
                        <BarList
                            rows={(comparison.data?.rows ?? []).map((row) => ({
                                label: row.name,
                                value: row.net_sales,
                                share: row.share_pct,
                                color: row.color,
                                secondary: formatPercent(row.margin_pct),
                                tone: row.margin_pct < 0 ? 'bad' : row.margin_pct > 30 ? 'good' : 'neutral',
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="marketplace.sales_summary.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Marketplace sales summary"
                        subtitle="Gross → net, including COD charges"
                        widgetKey="marketplace.sales_summary"
                        loading={summary.loading}
                        error={summary.error}
                        onRetry={summary.reload}
                        verdict={summary.data?.verdict}
                        exportDataset="sales_summary"
                    >
                        <SalesSummaryTable rows={summary.data?.rows ?? []} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketplace.order_status.view">
                    <ChartCard title="Order status" widgetKey="marketplace.order_status" loading={status.loading} error={status.error} onRetry={status.reload} exportDataset="orders">
                        <BarList
                            format="number"
                            rows={(status.data?.rows ?? []).map((row, index) => ({
                                label: row.label,
                                value: row.count,
                                share: row.share_pct,
                                color: CHART_COLORS[index % CHART_COLORS.length],
                                secondary: formatPercent(row.share_pct, 0),
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="marketplace.top_categories.view">
                    <ChartCard title="Top categories" widgetKey="marketplace.top_categories" loading={categories.loading} error={categories.error} onRetry={categories.reload} exportDataset="top_skus">
                        <BarList
                            rows={(categories.data?.rows ?? []).map((row, index) => ({
                                label: row.category,
                                value: row.net_sales,
                                share: row.share_pct,
                                color: CHART_COLORS[index % CHART_COLORS.length],
                                secondary: formatPercent(row.margin_pct),
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketplace.top_states.view">
                    <ChartCard title="Top states" widgetKey="marketplace.top_states" loading={states.loading} error={states.error} onRetry={states.reload} exportDataset="geo_states">
                        <BarList rows={(states.data?.rows ?? []).map((row) => ({ label: row.state, value: row.net_sales, share: row.share_pct, secondary: `${formatNumber(row.orders)} orders` }))} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketplace.channel_returns.view">
                    <ChartCard title="Return % by channel" widgetKey="marketplace.channel_returns" loading={channelReturns.loading} error={channelReturns.error} onRetry={channelReturns.reload} exportDataset="returns_register">
                        <DataTable
                            dense
                            rows={channelReturns.data?.rows ?? []}
                            rowKey={(row) => row.name}
                            columns={[
                                { key: 'name', header: 'Channel', value: (r) => r.name, render: (r) => (
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2 rounded-full" style={{ background: r.color ?? 'var(--chart-1)' }} />
                                        {r.name}
                                    </span>
                                ) },
                                { key: 'orders', header: 'Orders', align: 'right', value: (r) => r.orders, render: (r) => formatNumber(r.orders) },
                                { key: 'pct', header: 'Return %', align: 'right', sortable: true, value: (r) => r.return_pct, render: (r) => (
                                    <span className={r.return_pct > 10 ? 'font-medium text-bad' : ''}>{formatPercent(r.return_pct)}</span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="marketplace.top_products.view">
                    <ChartCard title="Top products" subtitle="Share of total net sales" widgetKey="marketplace.top_products" loading={products.loading} error={products.error} onRetry={products.reload} exportDataset="top_skus">
                        <DataTable
                            dense
                            rows={products.data?.rows ?? []}
                            rowKey={(row) => row.sku_code}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => r.sku_code, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{r.sku_code}</p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                    </div>
                                ) },
                                { key: 'units', header: 'Units', align: 'right', sortable: true, value: (r) => Number(r.units), render: (r) => formatNumber(Number(r.units)) },
                                { key: 'sales', header: 'Net sales', align: 'right', sortable: true, value: (r) => r.net_sales, render: (r) => formatCompactCurrency(r.net_sales) },
                                { key: 'share', header: 'Share', align: 'right', value: (r) => r.share_pct, render: (r) => formatPercent(r.share_pct) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketplace.products_by_channel.view">
                    <ChartCard title="Products by channel" subtitle="Net sales per SKU, per marketplace" widgetKey="marketplace.products_by_channel" loading={matrix.loading} error={matrix.error} onRetry={matrix.reload} exportDataset="top_skus">
                        <DataTable
                            dense
                            rows={matrix.data?.rows ?? []}
                            rowKey={(row) => String(row.sku_id)}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => String(r.sku_code), render: (r) => <span className="font-medium">{r.sku_code}</span> },
                                ...(matrix.data?.channels ?? []).map((channel) => ({
                                    key: channel.code,
                                    header: channel.name,
                                    align: 'right' as const,
                                    sortable: true,
                                    value: (r: Record<string, string | number>) => Number(r[channel.code] ?? 0),
                                    render: (r: Record<string, string | number>) => {
                                        const value = Number(r[channel.code] ?? 0);
                                        return value === 0 ? <span className="text-muted-foreground/50">—</span> : formatCompactCurrency(value);
                                    },
                                })),
                                { key: 'total', header: 'Total', align: 'right', sortable: true, value: (r) => Number(r.total), render: (r) => <span className="font-medium">{formatCompactCurrency(Number(r.total))}</span> },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="marketplace.zero_order_skus.view">
                    <ChartCard
                        title="Products with zero orders"
                        subtitle="Dead stock, and the capital tied up in it"
                        widgetKey="marketplace.zero_order_skus"
                        loading={zeroOrders.loading}
                        error={zeroOrders.error}
                        onRetry={zeroOrders.reload}
                        verdict={zeroOrders.data?.verdict}
                        exportDataset="zero_order_skus"
                    >
                        <DataTable
                            dense
                            rows={zeroOrders.data?.rows ?? []}
                            rowKey={(row) => row.sku_code}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => r.sku_code, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{r.sku_code}</p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                    </div>
                                ) },
                                { key: 'stock', header: 'Stock', align: 'right', sortable: true, value: (r) => Number(r.stock), render: (r) => formatNumber(Number(r.stock)) },
                                { key: 'capital', header: 'Capital', align: 'right', sortable: true, value: (r) => Number(r.capital_held), render: (r) => formatCompactCurrency(Number(r.capital_held)) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketplace.fast_moving.view">
                    <ChartCard title="Fast-moving SKUs" subtitle="Highest daily sell-through" widgetKey="marketplace.fast_moving" loading={fastMoving.loading} error={fastMoving.error} onRetry={fastMoving.reload} exportDataset="inventory_health">
                        <DataTable
                            dense
                            rows={fastMoving.data?.rows ?? []}
                            rowKey={(row) => row.sku_code}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => r.sku_code, render: (r) => <span className="font-medium">{r.sku_code}</span> },
                                { key: 'rate', header: 'Units / day', align: 'right', sortable: true, value: (r) => r.daily_rate, render: (r) => r.daily_rate.toFixed(2) },
                                { key: 'cover', header: 'Days left', align: 'right', sortable: true, value: (r) => r.days_of_cover, render: (r) => (
                                    <span className={r.days_of_cover < 14 ? 'font-medium text-warn' : ''}>{r.days_of_cover.toFixed(1)}d</span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketplace.inventory_valuation.view">
                    <ChartCard
                        title="Inventory valuation"
                        subtitle={`${formatNumber(valuation.data?.total_units ?? 0)} units · ${formatCurrency(valuation.data?.total_at_cost ?? 0)} at cost`}
                        widgetKey="marketplace.inventory_valuation"
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
                <PermissionGuard permission="marketplace.settlements.view">
                    <ChartCard
                        title="Settlement reconciliation"
                        subtitle="Expected vs received per cycle"
                        widgetKey="marketplace.settlements"
                        loading={settlements.loading}
                        error={settlements.error}
                        onRetry={settlements.reload}
                        caveat={settlements.data?.caveat as never}
                        empty={(settlements.data?.rows.length ?? 0) === 0}
                    >
                        <div className="grid grid-cols-3 gap-3">
                            {[
                                ['Expected', settlements.data?.expected_total ?? 0],
                                ['Received', settlements.data?.received_total ?? 0],
                                ['Variance', settlements.data?.variance_total ?? 0],
                            ].map(([label, value]) => (
                                <div key={label as string} className="rounded-lg border border-border p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{label}</p>
                                    <p className="mt-0.5 text-base font-semibold tnum">{formatCompactCurrency(value as number)}</p>
                                </div>
                            ))}
                        </div>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketplace.buybox.view">
                    <ChartCard
                        title="Buy Box & listing health"
                        widgetKey="marketplace.buybox"
                        loading={buybox.loading}
                        error={buybox.error}
                        onRetry={buybox.reload}
                        caveat={buybox.data?.caveat as never}
                        empty
                    >
                        <div />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="marketplace.price_competitiveness.view">
                <ChartCard
                    title="Price competitiveness"
                    subtitle="What customers actually paid, per channel"
                    widgetKey="marketplace.price_competitiveness"
                    loading={pricing.loading}
                    error={pricing.error}
                    onRetry={pricing.reload}
                    caveat={pricing.data?.caveat}
                    empty={(pricing.data?.rows.length ?? 0) === 0}
                >
                    <div className="space-y-3">
                        {(pricing.data?.rows ?? []).slice(0, 10).map((row) => (
                            <Card key={row.sku_code} className="p-3">
                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="truncate text-xs font-medium">{row.sku_code} · {row.name}</p>
                                        <p className="text-[11px] text-muted-foreground">MRP {formatCurrency(row.mrp)}</p>
                                    </div>
                                    <p className="text-[11px] text-muted-foreground">
                                        Spread <span className="font-semibold tnum text-foreground">{formatCurrency(row.price_spread)}</span>
                                    </p>
                                </div>
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {row.channels.map((channel) => (
                                        <span key={channel.channel_name} className="rounded-md border border-border px-2 py-1 text-[11px] tnum">
                                            {channel.channel_name} · {formatCurrency(channel.realised_price)}
                                            <span className="ml-1 text-muted-foreground">−{formatPercent(channel.discount_from_mrp_pct, 0)}</span>
                                        </span>
                                    ))}
                                </div>
                            </Card>
                        ))}
                    </div>
                </ChartCard>
            </PermissionGuard>

            <PermissionGuard permission="marketplace.recent_orders.view">
                <ChartCard title="Recent orders" widgetKey="marketplace.recent_orders" loading={orders.loading} error={orders.error} onRetry={orders.reload} exportDataset="orders">
                    <DataTable
                        rows={orders.data?.rows ?? []}
                        rowKey={(row) => row.id}
                        columns={[
                            { key: 'order', header: 'Order', value: (r) => r.order_number, render: (r) => <span className="font-medium">{r.order_number}</span> },
                            { key: 'channel', header: 'Channel', value: (r) => r.channel?.name ?? '', render: (r) => (
                                <span className="flex items-center gap-1.5">
                                    <span className="size-2 rounded-full" style={{ background: r.channel?.color ?? 'var(--muted-foreground)' }} />
                                    {r.channel?.name ?? '—'}
                                </span>
                            ) },
                            { key: 'status', header: 'Status', value: (r) => r.status_label, render: (r) => r.status_label },
                            { key: 'payment', header: 'Payment', value: (r) => r.payment_mode, render: (r) => (r.payment_mode === 'cod' ? 'COD' : 'Prepaid') },
                            { key: 'net', header: 'Net', align: 'right', sortable: true, value: (r) => r.net_amount, render: (r) => formatCurrency(r.net_amount) },
                            { key: 'margin', header: 'Margin', align: 'right', sortable: true, value: (r) => r.contribution_margin, render: (r) => (
                                <span className={r.contribution_margin < 0 ? 'font-medium text-bad' : ''}>{formatCurrency(r.contribution_margin)}</span>
                            ) },
                        ]}
                    />
                </ChartCard>
            </PermissionGuard>
        </AppLayout>
    );
}
