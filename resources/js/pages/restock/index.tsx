import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Download, RefreshCw } from 'lucide-react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable, type Column } from '@/components/app/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input, Label } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { apiGet } from '@/lib/api';
import { formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface RestockRow {
    sku_id: number;
    sku_code: string;
    name: string;
    variant_title: string | null;
    type: string;
    supplier_name: string | null;
    image_url: string | null;
    stock: number;
    incoming: number;
    units_window: number;
    units_30: number;
    units_prev_30: number;
    returns_window: number;
    revenue_window: number;
    velocity: number;
    projected_velocity: number;
    projection_drives: boolean;
    velocity_adjusted: boolean;
    low_confidence: boolean;
    cover_days: number | null;
    days_since_sale: number | null;
    age_days: number | null;
    bucket: string;
    suggested_qty: number;
    order_value: number;
    unit_cost: number;
    cost_estimated: boolean;
    stock_value: number;
    blocked_value: number;
    excess_units: number;
    abc: string;
    bestseller_rank: number | null;
    bestseller_units: number;
    is_top_seller: boolean;
}

interface BucketRow {
    bucket: string;
    skus: number;
    stock_value: number;
    blocked_value: number;
    order_value: number;
}

interface CategoryRow {
    type: string;
    skus_in_stock: number;
    stock_value: number;
    blocked_value: number;
    units: number;
    sales_share_pct: number;
}

interface RestockPayload {
    rows: RestockRow[];
    summary: {
        stock_value: number;
        working_value: number;
        dead_value: number;
        excess_value: number;
        releasable_value: number;
        spend_now: number;
        spend_soon: number;
        out_of_stock_sellers: number;
        skus_holding_stock: number;
        dead_after_days: number;
    };
    buckets: BucketRow[];
    categories: CategoryRow[];
    health: { level: string; text: string }[];
    anchor: string;
    window_days_used: number;
    history_days: number;
}

interface Settings {
    window: string;
    lead: number;
    safety: number;
    target: number;
    over: number;
    dead: number;
    new_days: number;
    round: number;
    projection: number;
    best: string;
    exclude: string;
    returns: string;
    cost_mode: string;
    cost_value: number;
}

const DEFAULTS: Settings = {
    window: '90',
    lead: 21,
    safety: 14,
    target: 60,
    over: 150,
    dead: 90,
    new_days: 30,
    round: 1,
    projection: 0,
    best: 'all',
    exclude: 'stack, combo',
    returns: 'net',
    cost_mode: 'flat',
    cost_value: 150,
};

const BUCKET_LABEL: Record<string, string> = {
    reorder: 'Reorder now',
    soon: 'Order soon',
    healthy: 'Healthy',
    overstock: 'Overstock',
    dead: 'Dead stock',
    new: 'New',
    inactive: 'Inactive',
    excluded: 'Excluded',
};

const BUCKET_VARIANT: Record<string, 'good' | 'bad' | 'warn' | 'muted' | 'secondary'> = {
    reorder: 'bad',
    soon: 'warn',
    healthy: 'good',
    overstock: 'secondary',
    dead: 'muted',
    new: 'good',
    inactive: 'muted',
    excluded: 'muted',
};

/** Buckets a buyer works through, in the order the money matters. */
const CHIP_ORDER = ['reorder', 'soon', 'healthy', 'overstock', 'dead', 'new', 'inactive', 'excluded'];

export default function Restock() {
    const [settings, setSettings] = useState<Settings>(() => {
        try {
            const saved = localStorage.getItem('restock_settings');
            return saved ? { ...DEFAULTS, ...(JSON.parse(saved) as Partial<Settings>) } : DEFAULTS;
        } catch {
            return DEFAULTS;
        }
    });
    const [data, setData] = useState<RestockPayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [bucket, setBucket] = useState<string | null>(null);
    const [type, setType] = useState<string | null>(null);
    const [abc, setAbc] = useState('');
    const [search, setSearch] = useState('');
    const [qtyOverrides, setQtyOverrides] = useState<Record<number, number>>({});

    const load = useCallback(() => {
        setLoading(true);
        apiGet<RestockPayload>('/restock', settings as unknown as Record<string, string | number>)
            .then((response) => {
                setData(response.data);
                setError(null);
            })
            .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Could not load the restock desk.'))
            .finally(() => setLoading(false));
    }, [settings]);

    // Every lever recomputes the whole page, so the numbers can be argued with.
    useEffect(() => {
        const timer = setTimeout(load, 300);
        return () => clearTimeout(timer);
    }, [load]);

    useEffect(() => {
        try {
            localStorage.setItem('restock_settings', JSON.stringify(settings));
        } catch {
            /* a browser refusing storage should not break the page */
        }
    }, [settings]);

    const set = <K extends keyof Settings>(key: K, value: Settings[K]) => setSettings((s) => ({ ...s, [key]: value }));
    const qtyFor = (row: RestockRow) => qtyOverrides[row.sku_id] ?? row.suggested_qty;

    const rows = useMemo(() => {
        const q = search.trim().toLowerCase();

        return (data?.rows ?? []).filter((row) => {
            if (bucket && row.bucket !== bucket) return false;
            if (!bucket && (row.bucket === 'inactive' || row.bucket === 'excluded')) return false;
            if (type && row.type !== type) return false;
            if (abc && row.abc !== abc) return false;
            if (q && !`${row.sku_code} ${row.name} ${row.type}`.toLowerCase().includes(q)) return false;
            return true;
        });
    }, [data, bucket, type, abc, search]);

    const summary = data?.summary;
    const orderLines = useMemo(() => rows.filter((row) => qtyFor(row) > 0), [rows, qtyOverrides]);

    function exportCsv(name: string, header: string[], lines: (string | number)[][]) {
        const cell = (v: string | number) => (/[",\n]/.test(String(v)) ? `"${String(v).replace(/"/g, '""')}"` : String(v));
        const csv = [header, ...lines].map((line) => line.map(cell).join(',')).join('\n');
        const url = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = `${name}-${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 4000);
        toast.success('Exported.');
    }

    const columns: Column<RestockRow>[] = useMemo(
        () => [
            {
                key: 'sku_code',
                header: 'SKU',
                sortable: true,
                value: (row) => row.sku_code,
                render: (row) => (
                    <div className="min-w-0">
                        <p className="truncate text-xs font-medium">{row.sku_code}</p>
                        <p className="truncate text-[11px] text-muted-foreground">{row.name}</p>
                    </div>
                ),
            },
            { key: 'type', header: 'Type', value: (row) => row.type, render: (row) => row.type },
            { key: 'abc', header: 'ABC', value: (row) => row.abc, render: (row) => row.abc },
            {
                key: 'bestseller_rank',
                header: 'Bestseller',
                align: 'right',
                sortable: true,
                value: (row) => row.bestseller_rank ?? 1e9,
                render: (row) => (row.bestseller_rank ? <Badge variant="muted">#{formatNumber(row.bestseller_rank)}</Badge> : '—'),
            },
            { key: 'stock', header: 'Stock', align: 'right', sortable: true, value: (row) => row.stock, render: (row) => (
                <span className={cn('tnum', row.stock <= 0 && 'text-bad')}>{formatNumber(row.stock)}</span>
            ) },
            { key: 'incoming', header: 'Inc', align: 'right', sortable: true, value: (row) => row.incoming, render: (row) => (row.incoming ? formatNumber(row.incoming) : '—') },
            {
                key: 'velocity',
                header: 'Sold / day',
                align: 'right',
                sortable: true,
                tooltip: 'Units a day over the sales window. † counts only the days the SKU was in stock.',
                value: (row) => row.velocity,
                render: (row) => (
                    <span className="tnum">
                        {row.velocity.toFixed(2)}
                        {row.velocity_adjusted && <span className="ml-0.5 text-warn" title="Stockout-adjusted">†</span>}
                        {row.low_confidence && <span className="ml-0.5 text-warn" title="Under 5 sales in the window">!</span>}
                    </span>
                ),
            },
            {
                key: 'cover_days',
                header: 'Cover',
                align: 'right',
                sortable: true,
                value: (row) => row.cover_days ?? 1e9,
                render: (row) => (row.stock <= 0 ? <span className="text-bad">OUT</span> : row.cover_days === null ? '∞' : `${Math.round(row.cover_days)}d`),
            },
            {
                key: 'days_since_sale',
                header: 'Last sale',
                align: 'right',
                sortable: true,
                value: (row) => row.days_since_sale ?? 1e9,
                render: (row) => (row.days_since_sale === null ? 'never' : row.days_since_sale === 0 ? 'today' : `${row.days_since_sale}d ago`),
            },
            {
                key: 'bucket',
                header: 'Bucket',
                value: (row) => row.bucket,
                render: (row) => <Badge variant={BUCKET_VARIANT[row.bucket] ?? 'muted'}>{BUCKET_LABEL[row.bucket] ?? row.bucket}</Badge>,
            },
            {
                key: 'suggested_qty',
                header: 'Order qty',
                align: 'right',
                sortable: true,
                tooltip: 'Target cover × sold/day, minus stock on hand and anything already incoming.',
                value: (row) => qtyFor(row),
                render: (row) =>
                    row.bucket === 'reorder' || row.bucket === 'soon' || qtyOverrides[row.sku_id] !== undefined ? (
                        <input
                            type="number"
                            min={0}
                            defaultValue={qtyFor(row)}
                            key={`${row.sku_id}-${row.suggested_qty}`}
                            onBlur={(e) => setQtyOverrides((q) => ({ ...q, [row.sku_id]: Math.max(0, Math.round(+e.target.value || 0)) }))}
                            className="tnum h-7 w-16 rounded-md border border-input bg-background px-2 text-right text-xs font-semibold text-bad"
                        />
                    ) : (
                        '—'
                    ),
            },
            {
                key: 'order_value',
                header: 'Order ₹',
                align: 'right',
                sortable: true,
                value: (row) => qtyFor(row) * row.unit_cost,
                render: (row) => (qtyFor(row) ? formatCurrency(qtyFor(row) * row.unit_cost) : '—'),
            },
            {
                key: 'blocked_value',
                header: '₹ blocked',
                align: 'right',
                sortable: true,
                value: (row) => row.blocked_value,
                render: (row) => (row.blocked_value ? <span className="text-bad">{formatCurrency(row.blocked_value)}</span> : '—'),
            },
            {
                key: 'stock_value',
                header: 'Stock ₹',
                align: 'right',
                sortable: true,
                value: (row) => row.stock_value,
                render: (row) => (
                    <span className={cn(row.cost_estimated && 'text-warn')} title={row.cost_estimated ? 'Cost estimated — fill cost price in Catalog' : ''}>
                        {row.stock_value ? formatCurrency(row.stock_value) : '—'}
                    </span>
                ),
            },
        ],
        [qtyOverrides],
    );

    return (
        <AppLayout title="Restock" description="Where the stock money sits, and what to buy next" showFilters={false}>
            <Head title="Restock" />

            <PermissionGuard permission="catalog.restock.view">
                <div className="grid gap-3 lg:grid-cols-4">
                    <Card className="p-4 lg:col-span-2">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Stock on hand, at cost</p>
                        <p className="mt-1 text-3xl font-semibold tnum">{formatCurrency(summary?.stock_value ?? 0)}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {formatNumber(summary?.skus_holding_stock ?? 0)} SKUs holding stock · sales anchored to {data?.anchor ?? '—'}
                        </p>

                        <div className="mt-3 flex h-8 overflow-hidden rounded-md border border-border">
                            {(
                                [
                                    ['working', summary?.working_value ?? 0, 'bg-good'],
                                    ['excess', summary?.excess_value ?? 0, 'bg-primary'],
                                    ['dead', summary?.dead_value ?? 0, 'bg-muted-foreground'],
                                ] as const
                            ).map(([key, value, colour]) => {
                                const total = summary?.stock_value || 1;
                                return <div key={key} className={colour} style={{ width: `${(value / total) * 100}%` }} title={`${key}: ${formatCurrency(value)}`} />;
                            })}
                        </div>
                        <div className="mt-2 flex flex-wrap gap-4 text-[11px] text-muted-foreground">
                            <span><span className="mr-1 inline-block size-2 rounded-sm bg-good" />Working {formatCurrency(summary?.working_value ?? 0)}</span>
                            <span><span className="mr-1 inline-block size-2 rounded-sm bg-primary" />Excess {formatCurrency(summary?.excess_value ?? 0)}</span>
                            <span><span className="mr-1 inline-block size-2 rounded-sm bg-muted-foreground" />Dead {formatCurrency(summary?.dead_value ?? 0)}</span>
                        </div>
                    </Card>

                    <Card className="border-l-4 border-l-primary p-4">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Cash you can release</p>
                        <p className="mt-1 text-2xl font-semibold tnum">{formatCurrency(summary?.releasable_value ?? 0)}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {formatCurrency(summary?.dead_value ?? 0)} dead + {formatCurrency(summary?.excess_value ?? 0)} excess — clear these, stop repeating them
                        </p>
                    </Card>

                    <Card className="border-l-4 border-l-bad p-4">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Reorder spend needed</p>
                        <p className="mt-1 text-2xl font-semibold tnum">{formatCurrency(summary?.spend_now ?? 0)}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {formatNumber(summary?.out_of_stock_sellers ?? 0)} already out of stock · {formatCurrency(summary?.spend_soon ?? 0)} due soon
                        </p>
                    </Card>
                </div>

                {(data?.health.length ?? 0) > 0 && (
                    <Card className="p-3">
                        <div className="grid gap-1 md:grid-cols-2">
                            {data?.health.map((note) => (
                                <p key={note.text} className="flex items-start gap-2 text-[11px] text-muted-foreground">
                                    <span
                                        className={cn(
                                            'mt-1 size-2 shrink-0 rounded-full',
                                            note.level === 'bad' ? 'bg-bad' : note.level === 'warn' ? 'bg-warn' : 'bg-good',
                                        )}
                                    />
                                    {note.text}
                                </p>
                            ))}
                        </div>
                    </Card>
                )}

                <Card className="flex flex-wrap items-end gap-3 p-3">
                    <div className="space-y-1">
                        <Label htmlFor="r-window">Sales window</Label>
                        <Select value={settings.window} onValueChange={(v) => set('window', v)}>
                            <SelectTrigger id="r-window" className="h-8 w-24"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {['30', '60', '90', '180', '365'].map((d) => <SelectItem key={d} value={d}>{d} days</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </div>
                    {(
                        [
                            ['lead', 'Lead time (d)'],
                            ['safety', 'Safety (d)'],
                            ['target', 'Target cover (d)'],
                            ['over', 'Overstock over (d)'],
                            ['dead', 'Dead after (d)'],
                            ['new_days', 'New for (d)'],
                            ['round', 'Round qty to'],
                            ['projection', 'Proj. orders/day'],
                        ] as const
                    ).map(([key, label]) => (
                        <div key={key} className="space-y-1">
                            <Label htmlFor={`r-${key}`}>{label}</Label>
                            <Input
                                id={`r-${key}`}
                                type="number"
                                min={0}
                                className="h-8 w-24"
                                value={settings[key]}
                                onChange={(e) => set(key, Math.max(0, +e.target.value || 0) as Settings[typeof key])}
                            />
                        </div>
                    ))}
                    <div className="space-y-1">
                        <Label htmlFor="r-returns">Returns / RTO</Label>
                        <Select value={settings.returns} onValueChange={(v) => set('returns', v)}>
                            <SelectTrigger id="r-returns" className="h-8 w-32"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="net">Exclude (net)</SelectItem>
                                <SelectItem value="gross">Count as sales</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="r-best">Bestseller window</Label>
                        <Select value={settings.best} onValueChange={(v) => set('best', v)}>
                            <SelectTrigger id="r-best" className="h-8 w-28"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All time</SelectItem>
                                <SelectItem value="365">12 months</SelectItem>
                                <SelectItem value="180">6 months</SelectItem>
                                <SelectItem value="90">90 days</SelectItem>
                                <SelectItem value="30">30 days</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="r-exclude">Exclude combos</Label>
                        <Input id="r-exclude" className="h-8 w-36" value={settings.exclude} onChange={(e) => set('exclude', e.target.value)} />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="r-cost">Missing cost</Label>
                        <div className="flex gap-1">
                            <Select value={settings.cost_mode} onValueChange={(v) => set('cost_mode', v)}>
                                <SelectTrigger id="r-cost" className="h-8 w-24"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="flat">₹/unit</SelectItem>
                                    <SelectItem value="percent">% of price</SelectItem>
                                </SelectContent>
                            </Select>
                            <Input type="number" min={0} className="h-8 w-20" value={settings.cost_value} onChange={(e) => set('cost_value', Math.max(0, +e.target.value || 0))} />
                        </div>
                    </div>
                    <Button size="sm" variant="outline" className="ml-auto" onClick={load} disabled={loading}>
                        <RefreshCw className={cn('size-3.5', loading && 'animate-spin')} />
                        Recompute
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => setSettings(DEFAULTS)}>Reset</Button>
                </Card>

                <div className="flex flex-wrap gap-2">
                    {CHIP_ORDER.map((key) => {
                        const b = data?.buckets.find((x) => x.bucket === key);
                        if (!b || b.skus === 0) return null;
                        const detail =
                            key === 'reorder' || key === 'soon'
                                ? `spend ${formatCurrency(b.order_value)}`
                                : b.blocked_value
                                  ? `${formatCurrency(b.blocked_value)} blocked`
                                  : `${formatCurrency(b.stock_value)} held`;

                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setBucket(bucket === key ? null : key)}
                                className={cn(
                                    'rounded-lg border border-border px-3 py-2 text-left transition',
                                    bucket === key ? 'border-foreground shadow-sm' : 'hover:border-muted-foreground',
                                )}
                            >
                                <p className="text-xs font-semibold">{BUCKET_LABEL[key]} · {formatNumber(b.skus)}</p>
                                <p className="text-[11px] text-muted-foreground">{detail}</p>
                            </button>
                        );
                    })}
                </div>

                {(data?.categories.length ?? 0) > 0 && (
                    <ChartCard title="Where the money is stuck" subtitle="By product type — click a row to filter the table" widgetKey="catalog.restock">
                        <DataTable<CategoryRow>
                            dense
                            rows={data?.categories ?? []}
                            rowKey={(row) => row.type}
                            onRowClick={(row) => setType(type === row.type ? null : row.type)}
                            columns={[
                                { key: 'type', header: 'Type', value: (row) => row.type, render: (row) => row.type },
                                { key: 'skus_in_stock', header: 'SKUs in stock', align: 'right', sortable: true, value: (row) => row.skus_in_stock, render: (row) => formatNumber(row.skus_in_stock) },
                                { key: 'stock_value', header: 'Stock value', align: 'right', sortable: true, value: (row) => row.stock_value, render: (row) => formatCurrency(row.stock_value) },
                                { key: 'blocked_value', header: '₹ blocked', align: 'right', sortable: true, value: (row) => row.blocked_value, render: (row) => formatCurrency(row.blocked_value) },
                                { key: 'sales_share_pct', header: 'Share of sales', align: 'right', sortable: true, value: (row) => row.sales_share_pct, render: (row) => `${row.sales_share_pct}%` },
                            ]}
                        />
                    </ChartCard>
                )}

                <ChartCard
                    title="Restock list"
                    subtitle={`${formatNumber(rows.length)} SKUs in view · ${formatNumber(orderLines.length)} on the order`}
                    widgetKey="catalog.restock"
                    loading={loading && !data}
                    error={error}
                    onRetry={load}
                >
                    <div className="mb-2 flex flex-wrap items-center gap-2">
                        <Input className="h-8 w-56" placeholder="Search SKU, product or type…" value={search} onChange={(e) => setSearch(e.target.value)} />
                        <Select value={abc || 'all'} onValueChange={(v) => setAbc(v === 'all' ? '' : v)}>
                            <SelectTrigger className="h-8 w-28"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">ABC: all</SelectItem>
                                <SelectItem value="A">A only</SelectItem>
                                <SelectItem value="B">B only</SelectItem>
                                <SelectItem value="C">C only</SelectItem>
                            </SelectContent>
                        </Select>
                        {type && (
                            <Button size="xs" variant="ghost" onClick={() => setType(null)}>
                                type: {type} ✕
                            </Button>
                        )}
                        <div className="ml-auto flex gap-2">
                            <Button
                                size="xs"
                                variant="outline"
                                onClick={() =>
                                    exportCsv(
                                        'restock-view',
                                        ['SKU', 'Product', 'Type', 'ABC', 'Stock', 'Incoming', 'Sold per day', 'Cover days', 'Bucket', 'Order qty', 'Unit cost', 'Order value', 'Blocked', 'Stock value'],
                                        rows.map((row) => [
                                            row.sku_code, row.name, row.type, row.abc, row.stock, row.incoming, row.velocity,
                                            row.cover_days ?? '', BUCKET_LABEL[row.bucket] ?? row.bucket, qtyFor(row),
                                            (row.unit_cost / 100).toFixed(2), ((qtyFor(row) * row.unit_cost) / 100).toFixed(2),
                                            (row.blocked_value / 100).toFixed(2), (row.stock_value / 100).toFixed(2),
                                        ]),
                                    )
                                }
                            >
                                <Download className="size-3" />
                                View CSV
                            </Button>
                            <Button
                                size="xs"
                                onClick={() =>
                                    orderLines.length === 0
                                        ? toast.error('Nothing to order at these settings.')
                                        : exportCsv(
                                              'purchase-order',
                                              ['SKU', 'Product', 'Supplier', 'Order qty', 'Unit cost', 'Order value'],
                                              orderLines.map((row) => [
                                                  row.sku_code, row.name, row.supplier_name ?? '', qtyFor(row),
                                                  (row.unit_cost / 100).toFixed(2), ((qtyFor(row) * row.unit_cost) / 100).toFixed(2),
                                              ]),
                                          )
                                }
                            >
                                <Download className="size-3" />
                                PO CSV ({formatNumber(orderLines.length)})
                            </Button>
                        </div>
                    </div>

                    <DataTable<RestockRow> rows={rows} rowKey={(row) => row.sku_id} columns={columns} />
                </ChartCard>
            </PermissionGuard>
        </AppLayout>
    );
}
