import { Head } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Cell, Line, ComposedChart, ReferenceLine, ResponsiveContainer, Tooltip as RTooltip, XAxis, YAxis } from 'recharts';
import { AppLayout } from '@/layouts/app-layout';
import { StatStrip } from '@/components/app/stat-strip';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { ReturnsBasisToggle } from '@/components/app/returns-basis-toggle';
import { DataTable } from '@/components/app/data-table';
import { BarList } from '@/components/app/bar-list';
import { Funnel } from '@/components/app/funnel';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { AXIS_PROPS, CHART_COLORS, ChartLegend, ChartTooltip, GRID_PROPS, axisDate, axisNumber, axisPercent } from '@/components/charts/chart-primitives';
import { useWidget } from '@/hooks/use-widget';
import { formatCompactCurrency, formatCurrency, formatDateTime, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Verdict } from '@/types';

export default function Operations() {
    const kpis = useWidget<Record<string, unknown>>('operations/kpis');
    const returnKpis = useWidget<Record<string, unknown>>('operations/returns/kpis');
    const reasons = useWidget<{ rows: { label: string; count: number; units: number; refund_amount: number }[] }>('operations/returns/by-reason');
    const byChannel = useWidget<{ rows: { name: string; color: string | null; orders: number; customer_returns: number; rto_events: number; return_pct: number }[] }>('operations/returns/by-channel');
    const trend = useWidget<{ series: { date: string; customer_returns: number; rto_events: number; refund_amount: number }[]; basis: string }>('operations/returns/trend');
    const shipmentStatus = useWidget<{ rows: Record<string, string | number>[]; statuses: { key: string; label: string }[]; caveat: string }>('operations/shipment-status');
    const couriers = useWidget<{ rows: { courier: string; shipments: number; delivered_pct: number; rto_pct: number; on_time_pct: number; avg_days: number; cost_per_shipment: number; ndr_resolution_pct: number; score: number; meets_sla: boolean }[]; caveat: string; verdict: Verdict }>('operations/courier-scorecard');
    const rtoStates = useWidget<{ rows: { state: string; orders: number; rto_count: number; rto_pct: number; cod_share_pct: number }[]; threshold: number; caveat: string; verdict: Verdict }>('operations/rto-by-state');
    const funnel = useWidget<{ steps: { key: string; label: string; value: number; pct: number }[] }>('operations/delivery-funnel');
    const delivery = useWidget<{ on_time_pct: number; avg_days: number; median_days: number; late_count: number; delivered_count: number; sla_days: number; distribution: { day: number; count: number }[]; verdict: Verdict }>('operations/delivery-performance');
    const ndr = useWidget<{ rows: { id: number; awb: string; courier: string; attempts: number; ndr_reason: string | null; order_number: string; net_amount: number; destination_city: string; destination_state: string; days_since_dispatch: number | null; urgency: string }[]; count: number; value_at_risk: number; verdict: Verdict }>('operations/ndr-queue');
    const aging = useWidget<{ buckets: { bucket: string; count: number; value: number }[]; rows: { id: number; order_number: string; placed_at: string; net_amount: number; age_days: number; bucket: string; sla_breached: boolean; channel_name: string | null; shipping_state: string | null }[]; total: number; breached_count: number; sla_days: number; caveat: string; verdict: Verdict }>('operations/order-aging');
    const pincodes = useWidget<{ rows: { pincode: string; city: string; state: string; shipments_count: number; rto_count: number; rto_rate: number; risk_band: string; cod_serviceable: number }[]; count: number; caveat: string }>('operations/pincode-risk');

    return (
        <AppLayout
            title="Operations"
            description="Shipments, returns, RTO and the queues that need a human today"
            filterExtras={<ReturnsBasisToggle />}
        >
            <Head title="Operations" />

            <PermissionGuard permission="operations.kpi_strip.view">
                <StatStrip stats={kpis.data ?? null} loading={kpis.loading} columns={4} />
            </PermissionGuard>

            <PermissionGuard permission="operations.returns_kpis.view">
                <StatStrip stats={returnKpis.data ?? null} loading={returnKpis.loading} columns={4} />
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="operations.order_aging.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Order aging"
                        subtitle="Everything still unshipped, right now"
                        widgetKey="operations.order_aging"
                        loading={aging.loading}
                        error={aging.error}
                        onRetry={aging.reload}
                        verdict={aging.data?.verdict}
                        caveat={aging.data?.caveat}
                        exportDataset="order_aging"
                    >
                        <div className="grid grid-cols-4 gap-2">
                            {(aging.data?.buckets ?? []).map((bucket) => (
                                <div
                                    key={bucket.bucket}
                                    className={cn(
                                        'rounded-lg border p-3',
                                        bucket.bucket === '>3d' && bucket.count > 0 ? 'border-bad/30 bg-bad-soft/40' : 'border-border',
                                    )}
                                >
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{bucket.bucket}</p>
                                    <p className={cn('mt-0.5 text-lg font-semibold tnum', bucket.bucket === '>3d' && bucket.count > 0 && 'text-bad')}>
                                        {formatNumber(bucket.count)}
                                    </p>
                                    <p className="text-[11px] text-muted-foreground tnum">{formatCompactCurrency(bucket.value)}</p>
                                </div>
                            ))}
                        </div>
                        <DataTable
                            dense
                            searchable
                            searchPlaceholder="Search unshipped orders…"
                            rows={aging.data?.rows ?? []}
                            rowKey={(row) => row.id}
                            columns={[
                                { key: 'order', header: 'Order', value: (r) => r.order_number, render: (r) => (
                                    <span className="flex items-center gap-1.5">
                                        <span className="font-medium">{r.order_number}</span>
                                        {r.sla_breached && <Badge variant="bad">SLA</Badge>}
                                    </span>
                                ) },
                                { key: 'channel', header: 'Channel', value: (r) => r.channel_name, render: (r) => r.channel_name ?? '—' },
                                { key: 'state', header: 'State', value: (r) => r.shipping_state, render: (r) => r.shipping_state ?? '—' },
                                { key: 'placed', header: 'Placed', value: (r) => r.placed_at, render: (r) => <span className="text-muted-foreground">{formatDateTime(r.placed_at)}</span> },
                                { key: 'age', header: 'Age', align: 'right', sortable: true, value: (r) => r.age_days, render: (r) => (
                                    <span className={r.sla_breached ? 'font-medium text-bad' : ''}>{r.age_days.toFixed(1)}d</span>
                                ) },
                                { key: 'value', header: 'Value', align: 'right', sortable: true, value: (r) => r.net_amount, render: (r) => formatCurrency(r.net_amount) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="operations.delivery_performance.view">
                    <ChartCard
                        title="Delivery performance"
                        subtitle="Against your delivery SLA"
                        widgetKey="operations.delivery_performance"
                        loading={delivery.loading}
                        error={delivery.error}
                        onRetry={delivery.reload}
                        verdict={delivery.data?.verdict}
                    >
                        <div className="grid grid-cols-3 gap-2">
                            {[
                                ['On time', formatPercent(delivery.data?.on_time_pct ?? 0)],
                                ['Median', `${delivery.data?.median_days ?? 0}d`],
                                ['Late', formatNumber(delivery.data?.late_count ?? 0)],
                            ].map(([label, value]) => (
                                <div key={label} className="rounded-lg border border-border p-2.5 text-center">
                                    <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</p>
                                    <p className="mt-0.5 text-sm font-semibold tnum">{value}</p>
                                </div>
                            ))}
                        </div>
                        <ResponsiveContainer width="100%" height={170}>
                            <BarChart data={delivery.data?.distribution ?? []} margin={{ top: 8, right: 4, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="day" {...AXIS_PROPS} tickFormatter={(v) => `${v}d`} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisNumber} width={36} />
                                <ReferenceLine x={delivery.data?.sla_days} stroke="var(--bad)" strokeDasharray="4 4" />
                                <RTooltip content={<ChartTooltip format="number" labelFormatter={(l) => `${l} days`} />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                <Bar dataKey="count" name="Shipments" radius={[3, 3, 0, 0]} isAnimationActive={false}>
                                    {(delivery.data?.distribution ?? []).map((row) => (
                                        <Cell key={row.day} fill={row.day <= (delivery.data?.sla_days ?? 7) ? 'var(--good)' : 'var(--bad)'} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="operations.ndr_queue.view">
                <ChartCard
                    title="NDR queue"
                    subtitle="Undelivered attempts waiting on a decision"
                    widgetKey="operations.ndr_queue"
                    tooltip="After the third failed attempt most couriers return the shipment automatically. Acting before then is what stops an RTO."
                    loading={ndr.loading}
                    error={ndr.error}
                    onRetry={ndr.reload}
                    verdict={ndr.data?.verdict}
                    empty={(ndr.data?.count ?? 0) === 0}
                >
                    <DataTable
                        searchable
                        searchPlaceholder="Search AWB, order or city…"
                        rows={ndr.data?.rows ?? []}
                        rowKey={(row) => row.id}
                        columns={[
                            { key: 'awb', header: 'AWB', value: (r) => r.awb, render: (r) => <span className="font-medium tnum">{r.awb}</span> },
                            { key: 'order', header: 'Order', value: (r) => r.order_number, render: (r) => r.order_number },
                            { key: 'courier', header: 'Courier', value: (r) => r.courier, render: (r) => r.courier },
                            { key: 'dest', header: 'Destination', value: (r) => `${r.destination_city} ${r.destination_state}`, render: (r) => (
                                <span className="text-muted-foreground">{r.destination_city}, {r.destination_state}</span>
                            ) },
                            { key: 'reason', header: 'Reason', value: (r) => r.ndr_reason, render: (r) => r.ndr_reason ?? '—' },
                            { key: 'attempts', header: 'Attempts', align: 'center', sortable: true, value: (r) => r.attempts, render: (r) => (
                                <Badge variant={r.attempts >= 3 ? 'bad' : 'warn'}>{r.attempts}</Badge>
                            ) },
                            { key: 'value', header: 'At risk', align: 'right', sortable: true, value: (r) => r.net_amount, render: (r) => formatCurrency(r.net_amount) },
                        ]}
                    />
                </ChartCard>
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="operations.courier_scorecard.view">
                    <ChartCard
                        title="Courier scorecard"
                        subtitle="Who deserves the next shipment"
                        widgetKey="operations.courier_scorecard"
                        tooltip="Score weights delivery rate 40%, on-time 35% and low RTO 25%."
                        loading={couriers.loading}
                        error={couriers.error}
                        onRetry={couriers.reload}
                        verdict={couriers.data?.verdict}
                        caveat={couriers.data?.caveat}
                        exportDataset="courier_scorecard"
                    >
                        <DataTable
                            dense
                            rows={couriers.data?.rows ?? []}
                            rowKey={(row) => row.courier}
                            columns={[
                                { key: 'courier', header: 'Courier', value: (r) => r.courier, render: (r) => (
                                    <span className="flex items-center gap-1.5 font-medium">
                                        {r.courier}
                                        {!r.meets_sla && <Badge variant="warn">below SLA</Badge>}
                                    </span>
                                ) },
                                { key: 'ship', header: 'Shipments', align: 'right', sortable: true, value: (r) => r.shipments, render: (r) => formatNumber(r.shipments) },
                                { key: 'ontime', header: 'On time', align: 'right', sortable: true, value: (r) => r.on_time_pct, render: (r) => formatPercent(r.on_time_pct) },
                                { key: 'rto', header: 'RTO', align: 'right', sortable: true, value: (r) => r.rto_pct, render: (r) => (
                                    <span className={r.rto_pct > 15 ? 'text-bad' : ''}>{formatPercent(r.rto_pct)}</span>
                                ) },
                                { key: 'days', header: 'Avg days', align: 'right', sortable: true, value: (r) => r.avg_days, render: (r) => `${r.avg_days}d` },
                                { key: 'cost', header: 'Cost/ship', align: 'right', sortable: true, value: (r) => r.cost_per_shipment, render: (r) => formatCurrency(r.cost_per_shipment) },
                                { key: 'score', header: 'Score', align: 'right', sortable: true, value: (r) => r.score, render: (r) => (
                                    <span className={cn('font-semibold', r.score >= 85 ? 'text-good' : r.score < 70 ? 'text-bad' : '')}>{r.score}</span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="operations.rto_by_state.view">
                    <ChartCard
                        title="RTO by state"
                        subtitle="Flagged against your threshold"
                        widgetKey="operations.rto_by_state"
                        loading={rtoStates.loading}
                        error={rtoStates.error}
                        onRetry={rtoStates.reload}
                        verdict={rtoStates.data?.verdict}
                        caveat={rtoStates.data?.caveat}
                        exportDataset="rto_by_state"
                    >
                        <ResponsiveContainer width="100%" height={280}>
                            <BarChart data={(rtoStates.data?.rows ?? []).slice(0, 12)} layout="vertical" margin={{ top: 0, right: 12, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} horizontal={false} vertical />
                                <XAxis type="number" {...AXIS_PROPS} tickFormatter={axisPercent} />
                                <YAxis type="category" dataKey="state" {...AXIS_PROPS} width={110} interval={0} />
                                <ReferenceLine x={rtoStates.data?.threshold ?? 15} stroke="var(--bad)" strokeDasharray="4 4" />
                                <RTooltip content={<ChartTooltip format="percent" labelFormatter={(l) => l} />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                <Bar dataKey="rto_pct" name="RTO %" radius={[0, 3, 3, 0]} isAnimationActive={false}>
                                    {(rtoStates.data?.rows ?? []).slice(0, 12).map((row) => (
                                        <Cell key={row.state} fill={row.rto_pct >= (rtoStates.data?.threshold ?? 15) ? 'var(--bad)' : 'var(--chart-4)'} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="operations.returns_trend.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Returns trend"
                        subtitle={`On the ${trend.data?.basis === 'return_date' ? 'return' : 'order'} date basis`}
                        widgetKey="operations.returns_trend"
                        loading={trend.loading}
                        error={trend.error}
                        onRetry={trend.reload}
                        exportDataset="returns_register"
                    >
                        <ResponsiveContainer width="100%" height={240}>
                            <ComposedChart data={trend.data?.series ?? []} margin={{ top: 4, right: 8, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={26} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisNumber} width={40} />
                                <RTooltip content={<ChartTooltip format="number" />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                <Bar dataKey="customer_returns" name="Customer returns" stackId="r" fill="var(--chart-5)" isAnimationActive={false} />
                                <Bar dataKey="rto_events" name="RTO" stackId="r" fill="var(--chart-4)" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                            </ComposedChart>
                        </ResponsiveContainer>
                        <ChartLegend items={[{ label: 'Customer returns', color: 'var(--chart-5)' }, { label: 'RTO', color: 'var(--chart-4)' }]} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="operations.returns_by_reason.view">
                    <ChartCard title="Returns by reason" widgetKey="operations.returns_by_reason" loading={reasons.loading} error={reasons.error} onRetry={reasons.reload} exportDataset="returns_register">
                        <BarList
                            format="number"
                            rows={(reasons.data?.rows ?? []).map((row, index) => ({
                                label: row.label,
                                value: row.count,
                                color: CHART_COLORS[index % CHART_COLORS.length],
                                secondary: formatCompactCurrency(row.refund_amount),
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="operations.returns_by_channel.view">
                    <ChartCard title="Returns by channel" widgetKey="operations.returns_by_channel" loading={byChannel.loading} error={byChannel.error} onRetry={byChannel.reload} exportDataset="returns_register">
                        <DataTable
                            dense
                            rows={byChannel.data?.rows ?? []}
                            rowKey={(row) => row.name}
                            columns={[
                                { key: 'name', header: 'Channel', value: (r) => r.name, render: (r) => (
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2 rounded-full" style={{ background: r.color ?? 'var(--chart-1)' }} />
                                        {r.name}
                                    </span>
                                ) },
                                { key: 'returns', header: 'Returns', align: 'right', value: (r) => r.customer_returns, render: (r) => formatNumber(r.customer_returns) },
                                { key: 'rto', header: 'RTO', align: 'right', value: (r) => r.rto_events, render: (r) => formatNumber(r.rto_events) },
                                { key: 'pct', header: '%', align: 'right', sortable: true, value: (r) => r.return_pct, render: (r) => (
                                    <span className={r.return_pct > 10 ? 'font-medium text-bad' : ''}>{formatPercent(r.return_pct)}</span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="operations.delivery_funnel.view">
                    <ChartCard title="Delivery funnel" subtitle="Shipped → delivered" widgetKey="operations.delivery_funnel" loading={funnel.loading} error={funnel.error} onRetry={funnel.reload}>
                        <Funnel steps={funnel.data?.steps ?? []} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="operations.pincode_risk.view">
                    <ChartCard
                        title="Risky pincodes"
                        subtitle="High RTO — expose to checkout via the risk API"
                        widgetKey="operations.pincode_risk"
                        loading={pincodes.loading}
                        error={pincodes.error}
                        onRetry={pincodes.reload}
                        caveat={pincodes.data?.caveat}
                        empty={(pincodes.data?.count ?? 0) === 0}
                    >
                        <DataTable
                            dense
                            rows={pincodes.data?.rows ?? []}
                            rowKey={(row) => row.pincode}
                            columns={[
                                { key: 'pin', header: 'Pincode', value: (r) => r.pincode, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="font-medium tnum">{r.pincode}</p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.city}</p>
                                    </div>
                                ) },
                                { key: 'ships', header: 'Ships', align: 'right', value: (r) => r.shipments_count, render: (r) => formatNumber(r.shipments_count) },
                                { key: 'rto', header: 'RTO %', align: 'right', sortable: true, value: (r) => r.rto_rate, render: (r) => (
                                    <Badge variant={r.risk_band === 'critical' ? 'bad' : 'warn'}>{formatPercent(r.rto_rate)}</Badge>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="operations.shipment_status.view">
                <ChartCard
                    title="Shipment status by courier"
                    widgetKey="operations.shipment_status"
                    loading={shipmentStatus.loading}
                    error={shipmentStatus.error}
                    onRetry={shipmentStatus.reload}
                    caveat={shipmentStatus.data?.caveat}
                    exportDataset="courier_scorecard"
                >
                    <DataTable
                        rows={shipmentStatus.data?.rows ?? []}
                        rowKey={(row) => String(row.courier)}
                        columns={[
                            { key: 'courier', header: 'Courier', value: (r) => String(r.courier), render: (r) => <span className="font-medium">{r.courier}</span> },
                            ...(shipmentStatus.data?.statuses ?? [])
                                .filter((status) => (shipmentStatus.data?.rows ?? []).some((row) => Number(row[status.key] ?? 0) > 0))
                                .map((status) => ({
                                    key: status.key,
                                    header: status.label,
                                    align: 'right' as const,
                                    sortable: true,
                                    value: (r: Record<string, string | number>) => Number(r[status.key] ?? 0),
                                    render: (r: Record<string, string | number>) => {
                                        const value = Number(r[status.key] ?? 0);
                                        return value === 0 ? <span className="text-muted-foreground/50">—</span> : formatNumber(value);
                                    },
                                })),
                            { key: 'total', header: 'Total', align: 'right', sortable: true, value: (r) => Number(r.total), render: (r) => <span className="font-medium">{formatNumber(Number(r.total))}</span> },
                        ]}
                    />
                </ChartCard>
            </PermissionGuard>
        </AppLayout>
    );
}
