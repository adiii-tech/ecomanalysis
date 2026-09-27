import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { DataTable, type Column } from '@/components/app/data-table';
import { PermissionGuard } from '@/components/app/permission-guard';
import { Badge } from '@/components/ui/badge';
import { useWidget } from '@/hooks/use-widget';
import { formatCurrency, formatDateTime, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { OrderDetailDrawer } from '@/components/orders/order-detail-drawer';

interface OrderRow {
    id: number;
    order_number: string;
    placed_at: string;
    status: string;
    status_label: string;
    payment_mode: string;
    channel: { id: number; name: string; code: string; color: string | null } | null;
    customer: { id: number; masked_email: string | null; name: string | null; city: string | null; state: string | null } | null;
    shipping_state: string | null;
    shipping_city: string | null;
    units: number;
    gross_amount: number;
    discount_amount: number;
    net_amount: number;
    cogs_amount: number;
    fees_amount: number;
    logistics_amount: number;
    contribution_margin: number;
    margin_pct: number | null;
    is_rto: boolean;
    has_return: boolean;
    is_first_order: boolean;
}

interface OrdersPayload {
    rows: OrderRow[];
    total: number;
    shown: number;
    truncated: boolean;
    status_counts: Record<string, number>;
}

/** Every status the desk can filter to, in the order an owner works through them. */
const STATUS_ORDER = ['placed', 'confirmed', 'shipped', 'delivered', 'rto', 'returned', 'cancelled'] as const;

const STATUS_LABEL: Record<string, string> = {
    placed: 'Placed',
    confirmed: 'Confirmed',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    returned: 'Returned',
    rto: 'RTO',
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'good' | 'bad' | 'warn' | 'muted'> = {
    placed: 'muted',
    confirmed: 'secondary',
    shipped: 'default',
    delivered: 'good',
    cancelled: 'muted',
    returned: 'bad',
    rto: 'bad',
};

export default function Orders() {
    const [status, setStatus] = useState<string | null>(null);
    const [openOrderId, setOpenOrderId] = useState<number | null>(null);

    const orders = useWidget<OrdersPayload>('orders', { status: status ?? '' });

    const rows = orders.data?.rows ?? [];
    const counts = orders.data?.status_counts ?? {};
    const totalInWindow = useMemo(() => Object.values(counts).reduce((sum, n) => sum + n, 0), [counts]);

    const columns: Column<OrderRow>[] = useMemo(
        () => [
            {
                key: 'order_number',
                header: 'Order',
                sortable: true,
                value: (row) => row.order_number,
                render: (row) => <span className="font-medium">{row.order_number}</span>,
            },
            {
                key: 'placed_at',
                header: 'Placed',
                sortable: true,
                value: (row) => row.placed_at,
                render: (row) => <span className="text-muted-foreground">{formatDateTime(row.placed_at)}</span>,
            },
            {
                key: 'customer',
                header: 'Customer',
                value: (row) => row.customer?.name ?? row.customer?.masked_email ?? '',
                render: (row) => (
                    <div className="min-w-0">
                        <p className="truncate">{row.customer?.name ?? row.customer?.masked_email ?? 'Guest'}</p>
                        {row.is_first_order && <span className="text-[10px] text-muted-foreground">first order</span>}
                    </div>
                ),
            },
            {
                key: 'channel',
                header: 'Channel',
                value: (row) => row.channel?.name ?? '',
                render: (row) => (
                    <span className="flex items-center gap-1.5">
                        <span className="size-2 shrink-0 rounded-full" style={{ background: row.channel?.color ?? 'var(--muted-foreground)' }} />
                        {row.channel?.name ?? '—'}
                    </span>
                ),
            },
            { key: 'shipping_state', header: 'State', value: (row) => row.shipping_state, render: (row) => row.shipping_state ?? '—' },
            {
                key: 'payment_mode',
                header: 'Payment',
                value: (row) => row.payment_mode,
                render: (row) => <Badge variant={row.payment_mode === 'cod' ? 'warn' : 'muted'}>{row.payment_mode === 'cod' ? 'COD' : 'Prepaid'}</Badge>,
            },
            {
                key: 'status',
                header: 'Status',
                value: (row) => row.status_label,
                render: (row) => <Badge variant={STATUS_VARIANT[row.status] ?? 'muted'}>{row.status_label}</Badge>,
            },
            { key: 'units', header: 'Units', align: 'right', sortable: true, value: (row) => row.units, render: (row) => formatNumber(row.units) },
            { key: 'net_amount', header: 'Net', align: 'right', sortable: true, value: (row) => row.net_amount, render: (row) => formatCurrency(row.net_amount) },
            {
                key: 'contribution_margin',
                header: 'Margin',
                align: 'right',
                sortable: true,
                value: (row) => row.contribution_margin,
                render: (row) => (
                    <span className={cn('tnum', row.contribution_margin < 0 && 'font-medium text-bad')}>
                        {formatCurrency(row.contribution_margin)}
                        <span className="ml-1 text-[10px] text-muted-foreground">{row.margin_pct === null ? '—' : formatPercent(row.margin_pct)}</span>
                    </span>
                ),
            },
        ],
        [],
    );

    return (
        <AppLayout title="Orders" description="Every order, and what happened to it" surface="orders">
            <Head title="Orders" />

            <PermissionGuard permission="dashboard.recent_orders.view">
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => setStatus(null)}
                        className={cn(
                            'rounded-lg border border-border px-3 py-2 text-left transition',
                            status === null ? 'border-foreground shadow-sm' : 'hover:border-muted-foreground',
                        )}
                    >
                        <p className="text-xs font-semibold">All · {formatNumber(totalInWindow)}</p>
                    </button>
                    {STATUS_ORDER.filter((key) => (counts[key] ?? 0) > 0).map((key) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setStatus(status === key ? null : key)}
                            className={cn(
                                'rounded-lg border border-border px-3 py-2 text-left transition',
                                status === key ? 'border-foreground shadow-sm' : 'hover:border-muted-foreground',
                            )}
                        >
                            <p className="text-xs font-semibold">{STATUS_LABEL[key]} · {formatNumber(counts[key] ?? 0)}</p>
                        </button>
                    ))}
                </div>

                <ChartCard
                    title="Orders"
                    subtitle={
                        orders.data
                            ? `${formatNumber(orders.data.total)} orders in this window${orders.data.truncated ? ` · showing the ${formatNumber(orders.data.shown)} most recent` : ''} — click one for the full breakdown`
                            : 'Click any order for the full breakdown'
                    }
                    widgetKey="dashboard.recent_orders"
                    exportDataset="orders"
                    loading={orders.loading}
                    error={orders.error}
                    onRetry={orders.reload}
                    empty={!orders.loading && rows.length === 0}
                >
                    <DataTable<OrderRow>
                        rows={rows}
                        loading={orders.loading}
                        rowKey={(row) => row.id}
                        columns={columns}
                        searchable
                        searchPlaceholder="Search order, city or state…"
                        onRowClick={(row) => setOpenOrderId(row.id)}
                        initialSort={{ key: 'placed_at', direction: 'desc' }}
                    />
                </ChartCard>

                <OrderDetailDrawer orderId={openOrderId} onClose={() => setOpenOrderId(null)} />
            </PermissionGuard>
        </AppLayout>
    );
}
