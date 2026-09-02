import { useCallback, useEffect, useState } from 'react';
import { DrilldownDrawer } from '@/components/app/drilldown-drawer';
import { Badge } from '@/components/ui/badge';
import type { Column } from '@/components/app/data-table';
import { apiGet } from '@/lib/api';
import { formatCurrency, formatDateTime, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface DrilldownTarget {
    /** One of the dimensions the API allows: state, channel, sku, courier… */
    dimension?: string;
    value?: string | number | null;
    only?: 'loss' | 'returned' | 'rto' | 'cod' | 'unshipped';
    title: string;
    description?: string;
}

interface OrderRow {
    id: number;
    order_number: string;
    placed_at: string;
    status: string;
    payment_mode: string;
    channel: string | null;
    shipping_state: string | null;
    units_count: number;
    net_amount: number;
    cogs_amount: number;
    fees_amount: number;
    logistics_amount: number;
    contribution_margin: number;
    margin_pct: number;
}

const COLUMNS: Column<OrderRow>[] = [
    {
        key: 'order_number',
        header: 'Order',
        sortable: true,
        value: (row) => row.order_number,
        render: (row) => <span className="font-medium">{row.order_number}</span>,
    },
    { key: 'placed_at', header: 'Placed', sortable: true, value: (row) => row.placed_at, render: (row) => formatDateTime(row.placed_at) },
    { key: 'channel', header: 'Channel', render: (row) => row.channel ?? '—' },
    { key: 'status', header: 'Status', render: (row) => <Badge variant="muted">{row.status}</Badge> },
    { key: 'payment_mode', header: 'Payment', render: (row) => row.payment_mode },
    { key: 'shipping_state', header: 'State', render: (row) => row.shipping_state ?? '—' },
    { key: 'units_count', header: 'Units', align: 'right', sortable: true, value: (row) => row.units_count, render: (row) => formatNumber(row.units_count) },
    { key: 'net_amount', header: 'Net', align: 'right', sortable: true, value: (row) => row.net_amount, render: (row) => formatCurrency(row.net_amount) },
    { key: 'cogs_amount', header: 'COGS', align: 'right', sortable: true, value: (row) => row.cogs_amount, render: (row) => formatCurrency(row.cogs_amount) },
    { key: 'fees_amount', header: 'Fees', align: 'right', sortable: true, value: (row) => row.fees_amount, render: (row) => formatCurrency(row.fees_amount) },
    { key: 'logistics_amount', header: 'Logistics', align: 'right', sortable: true, value: (row) => row.logistics_amount, render: (row) => formatCurrency(row.logistics_amount) },
    {
        key: 'contribution_margin',
        header: 'Margin',
        align: 'right',
        sortable: true,
        value: (row) => row.contribution_margin,
        render: (row) => (
            <span className={cn('tnum', row.contribution_margin < 0 && 'text-bad')}>{formatCurrency(row.contribution_margin)}</span>
        ),
    },
    { key: 'margin_pct', header: 'Margin %', align: 'right', sortable: true, value: (row) => row.margin_pct, render: (row) => formatPercent(row.margin_pct) },
];

/**
 * The orders behind a number. Any widget can open this with a dimension and a
 * value; the point is that no figure in the product is a dead end.
 */
export function OrdersDrilldown({ target, onClose }: { target: DrilldownTarget | null; onClose: () => void }) {
    const [rows, setRows] = useState<OrderRow[] | null>(null);
    const [loading, setLoading] = useState(false);
    const [meta, setMeta] = useState<{ total: number; shown: number; truncated: boolean } | null>(null);

    const load = useCallback(async (next: DrilldownTarget) => {
        setLoading(true);
        try {
            const response = await apiGet<{ rows: OrderRow[]; total: number; shown: number; truncated: boolean }>(
                '/drilldown/orders',
                {
                    dimension: next.dimension ?? '',
                    value: next.value === null || next.value === undefined ? '' : String(next.value),
                    only: next.only ?? '',
                },
            );
            setRows(response.data.rows);
            setMeta({ total: response.data.total, shown: response.data.shown, truncated: response.data.truncated });
        } catch {
            setRows([]);
            setMeta(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (target) {
            void load(target);
        } else {
            setRows(null);
            setMeta(null);
        }
    }, [target, load]);

    return (
        <DrilldownDrawer<OrderRow>
            open={target !== null}
            onOpenChange={(open) => !open && onClose()}
            title={target?.title ?? 'Orders'}
            description={
                meta
                    ? `${formatNumber(meta.total)} orders${meta.truncated ? `, showing the ${formatNumber(meta.shown)} most recent` : ''}. ${target?.description ?? ''}`
                    : target?.description
            }
            columns={COLUMNS}
            rows={rows}
            loading={loading}
            rowKey={(row) => row.id}
            exportDataset="orders"
            emptyTitle="No orders behind this number"
        />
    );
}
