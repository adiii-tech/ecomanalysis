import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Download, FileImage, RefreshCw, RotateCcw } from 'lucide-react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable, type Column } from '@/components/app/data-table';
import { PoColumnPicker } from '@/components/restock/po-column-picker';
import { SkuDrawer } from '@/components/restock/sku-drawer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input, Label } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { apiGet } from '@/lib/api';
import { formatCurrency, formatNumber } from '@/lib/format';
import { exportAnjaniPo, exportPoCsv, exportPoPhotos, exportViewCsv } from '@/lib/restock-export';
import {
    BUCKET_LABEL,
    BUCKET_VARIANT,
    RESTOCK_DEFAULTS,
    SEGMENT_BUCKETS,
    buildPoLines,
    byAttention,
    loadPoColumns,
    poEligible,
    poIncluded,
    poQtyOf,
    poSuggested,
    savePoColumns,
    type CategoryRow,
    type RestockPayload,
    type RestockRow,
    type RestockSettings,
} from '@/lib/restock';
import { cn } from '@/lib/utils';

/** Buckets a buyer works through, in the order the money matters. */
const CHIP_ORDER = ['reorder', 'soon', 'healthy', 'overstock', 'dead', 'new', 'inactive', 'excluded'];

export default function Restock() {
    const [settings, setSettings] = useState<RestockSettings>(() => {
        try {
            const saved = localStorage.getItem('restock_settings');
            return saved ? { ...RESTOCK_DEFAULTS, ...(JSON.parse(saved) as Partial<RestockSettings>) } : RESTOCK_DEFAULTS;
        } catch {
            return RESTOCK_DEFAULTS;
        }
    });
    const [data, setData] = useState<RestockPayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [bucket, setBucket] = useState<string | null>(null);
    const [segment, setSegment] = useState<string | null>(null);
    const [outOnly, setOutOnly] = useState(false);
    const [type, setType] = useState<string | null>(null);
    const [abc, setAbc] = useState('');
    const [search, setSearch] = useState('');
    const [topOnly, setTopOnly] = useState(false);
    const [poInclude, setPoInclude] = useState<Record<number, boolean>>({});
    const [poQty, setPoQty] = useState<Record<number, number>>({});
    const [poColumns, setPoColumns] = useState<string[]>(() => loadPoColumns());
    const [openSku, setOpenSku] = useState<number | null>(null);

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

    useEffect(() => savePoColumns(poColumns), [poColumns]);

    const set = <K extends keyof RestockSettings>(key: K, value: RestockSettings[K]) => setSettings((s) => ({ ...s, [key]: value }));
    const qtyFor = useCallback((row: RestockRow) => poQtyOf(row, poQty, settings), [poQty, settings]);
    const isOn = useCallback((row: RestockRow) => poIncluded(row, poInclude), [poInclude]);

    /** Worst first — the list opens on what needs buying, not on SKU-001. */
    const ranked = useMemo(() => [...(data?.rows ?? [])].sort(byAttention), [data]);

    const rows = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return ranked.filter((row) => {
            if (topOnly && !row.is_top_seller) return false;
            if (segment && !(SEGMENT_BUCKETS[segment] ?? []).includes(row.bucket)) return false;
            if (outOnly && !(row.bucket === 'reorder' && row.stock <= 0)) return false;
            if (bucket && row.bucket !== bucket) return false;
            if (!bucket && !segment && !outOnly && (row.bucket === 'inactive' || row.bucket === 'excluded')) return false;
            if (type && row.type !== type) return false;
            if (abc && row.abc !== abc) return false;
            if (needle && !`${row.sku_code} ${row.name} ${row.type}`.toLowerCase().includes(needle)) return false;
            return true;
        });
    }, [ranked, topOnly, segment, outOnly, bucket, type, abc, search]);

    const summary = data?.summary;
    const po = useMemo(() => buildPoLines(ranked, poInclude, poQty, settings), [ranked, poInclude, poQty, settings]);
    const suggestedCount = useMemo(() => ranked.filter(poSuggested).length, [ranked]);
    const hasEstimatedCost = useMemo(() => ranked.some((row) => row.cost_estimated && row.stock_value > 0), [ranked]);
    const touchedPo = Object.keys(poInclude).length > 0 || Object.keys(poQty).length > 0;

    const clearFilters = () => {
        setBucket(null);
        setSegment(null);
        setOutOnly(false);
    };

    const togglePo = (skuId: number, include: boolean) => setPoInclude((current) => ({ ...current, [skuId]: include }));
    const setQty = (skuId: number, qty: number) => setPoQty((current) => ({ ...current, [skuId]: qty }));

    const requirePoLines = (): boolean => {
        if (po.lines.length > 0) return true;
        toast.error(suggestedCount > 0 ? 'Nothing is ticked for the order.' : 'Nothing to reorder at these settings.');
        return false;
    };

    const reportDownload = (ok: boolean) =>
        ok ? toast.success('Exported.') : toast.error('Your browser blocked the download. Allow downloads for this site and try again.');

    const visibleSelectable = rows.filter(poEligible);
    const visibleSelected = visibleSelectable.filter(isOn).length;

    const columns: Column<RestockRow>[] = useMemo(
        () => [
            {
                key: 'select',
                width: '34px',
                align: 'center',
                header: (
                    <input
                        type="checkbox"
                        aria-label="Select every product in view for the purchase order"
                        title="Select every product in view for the purchase order"
                        className="size-3.5 accent-[var(--good)]"
                        disabled={visibleSelectable.length === 0}
                        checked={visibleSelectable.length > 0 && visibleSelected === visibleSelectable.length}
                        ref={(node) => {
                            if (node) node.indeterminate = visibleSelected > 0 && visibleSelected < visibleSelectable.length;
                        }}
                        onChange={(event) =>
                            setPoInclude((current) => ({
                                ...current,
                                ...Object.fromEntries(visibleSelectable.map((row) => [row.sku_id, event.target.checked])),
                            }))
                        }
                    />
                ),
                render: (row) =>
                    poEligible(row) ? (
                        <input
                            type="checkbox"
                            aria-label={`Include ${row.sku_code} in the purchase order`}
                            className="size-3.5 accent-[var(--good)]"
                            checked={isOn(row)}
                            onClick={(event) => event.stopPropagation()}
                            onChange={(event) => togglePo(row.sku_id, event.target.checked)}
                        />
                    ) : null,
            },
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
                render: (row) =>
                    row.bestseller_rank ? (
                        <Badge variant={row.bestseller_rank <= 10 ? 'warn' : row.bestseller_rank <= 50 ? 'good' : row.bestseller_rank <= 300 ? 'secondary' : 'muted'}>
                            {row.bestseller_rank <= 10 && '★ '}#{formatNumber(row.bestseller_rank)}
                        </Badge>
                    ) : (
                        '—'
                    ),
            },
            {
                key: 'stock',
                header: 'Stock',
                align: 'right',
                sortable: true,
                value: (row) => row.stock,
                render: (row) => <span className={cn('tnum', row.stock <= 0 && 'text-bad')}>{formatNumber(row.stock)}</span>,
            },
            { key: 'incoming', header: 'Inc', align: 'right', sortable: true, value: (row) => row.incoming, render: (row) => (row.incoming ? formatNumber(row.incoming) : '—') },
            {
                key: 'velocity',
                header: 'Sold / day',
                align: 'right',
                sortable: true,
                tooltip: 'Units a day over the sales window. † counts only the days the SKU was in stock. ▴▾ compares the last 30 days with the 30 before them.',
                value: (row) => row.velocity,
                render: (row) => (
                    <span className="tnum">
                        {row.velocity.toFixed(2)}
                        {row.velocity_adjusted && <span className="ml-0.5 text-warn" title="Stockout-adjusted">†</span>}
                        {row.low_confidence && <span className="ml-0.5 text-warn" title="Under 5 sales in the window">!</span>}
                        {row.units_30 > row.units_prev_30 && <span className="ml-0.5 text-good" title="Selling faster than the previous 30 days">▴</span>}
                        {row.units_30 < row.units_prev_30 && <span className="ml-0.5 text-bad" title="Selling slower than the previous 30 days">▾</span>}
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
                value: (row) => (isOn(row) ? qtyFor(row) : 0),
                render: (row) =>
                    isOn(row) ? (
                        <input
                            type="number"
                            min={1}
                            defaultValue={qtyFor(row)}
                            key={`${row.sku_id}-${row.suggested_qty}-${poQty[row.sku_id] ?? ''}`}
                            onClick={(event) => event.stopPropagation()}
                            onBlur={(event) => setQty(row.sku_id, Math.max(1, Math.round(+event.target.value || 0)))}
                            className="tnum h-7 w-16 rounded-md border border-input bg-background px-2 text-right text-xs font-semibold text-bad"
                        />
                    ) : row.suggested_qty ? (
                        <span className="tnum text-muted-foreground">{formatNumber(row.suggested_qty)}</span>
                    ) : (
                        '—'
                    ),
            },
            {
                key: 'order_value',
                header: 'Order ₹',
                align: 'right',
                sortable: true,
                value: (row) => (isOn(row) ? qtyFor(row) * row.unit_cost : 0),
                render: (row) => (isOn(row) ? formatCurrency(qtyFor(row) * row.unit_cost) : '—'),
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
        [isOn, qtyFor, poQty, visibleSelectable, visibleSelected],
    );

    const openRow = openSku === null ? null : (data?.rows.find((row) => row.sku_id === openSku) ?? null);

    return (
        <AppLayout title="Restock" description="Where the stock money sits, and what to buy next" showFilters={false}>
            <Head title="Restock" />

            <PermissionGuard permission="catalog.restock.view">
                <div className="grid gap-3 lg:grid-cols-4">
                    <Card className="p-4 lg:col-span-2">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Stock on hand, at cost</p>
                        <p className="mt-1 text-3xl font-semibold tnum">{formatCurrency(summary?.stock_value ?? 0)}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {formatNumber(summary?.skus_holding_stock ?? 0)} SKUs holding stock · sales anchored to {data?.anchor ?? '—'} ·{' '}
                            {formatNumber(data?.history_days ?? 0)} days of history
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
                                return (
                                    <button
                                        key={key}
                                        type="button"
                                        title={`${key}: ${formatCurrency(value)} — click to filter the list`}
                                        onClick={() => {
                                            setBucket(null);
                                            setOutOnly(false);
                                            setSegment(segment === key ? null : key);
                                        }}
                                        className={cn(colour, 'transition', segment === key && 'ring-2 ring-inset ring-foreground')}
                                        style={{ width: `${(value / total) * 100}%` }}
                                    />
                                );
                            })}
                        </div>
                        <div className="mt-2 flex flex-wrap gap-4 text-[11px] text-muted-foreground">
                            {(
                                [
                                    ['working', 'Working', summary?.working_value ?? 0, 'bg-good'],
                                    ['excess', 'Excess', summary?.excess_value ?? 0, 'bg-primary'],
                                    ['dead', 'Dead', summary?.dead_value ?? 0, 'bg-muted-foreground'],
                                ] as const
                            ).map(([key, label, value, colour]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => {
                                        setBucket(null);
                                        setOutOnly(false);
                                        setSegment(segment === key ? null : key);
                                    }}
                                    className={cn('transition hover:text-foreground', segment === key && 'font-semibold text-foreground')}
                                >
                                    <span className={cn('mr-1 inline-block size-2 rounded-sm', colour)} />
                                    {label} {formatCurrency(value)}
                                </button>
                            ))}
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
                            {po.budget > 0 && (
                                <>
                                    <br />
                                    Budget {formatCurrency(po.budget)} covers <b>{po.fittedCount}</b> of {po.lines.length} lines ({formatCurrency(po.fittedValue)}).
                                </>
                            )}
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
                            ['budget', 'PO budget ₹'],
                        ] as const
                    ).map(([key, label]) => (
                        <div key={key} className="space-y-1">
                            <Label htmlFor={`r-${key}`}>{label}</Label>
                            <Input
                                id={`r-${key}`}
                                type="number"
                                min={0}
                                className="h-8 w-24"
                                placeholder={key === 'budget' ? 'no cap' : undefined}
                                value={settings[key]}
                                onChange={(e) => set(key, Math.max(0, +e.target.value || 0) as RestockSettings[typeof key])}
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
                    <label className="flex h-8 cursor-pointer items-center gap-1.5 text-xs font-medium">
                        <input type="checkbox" className="size-3.5 accent-[var(--good)]" checked={topOnly} onChange={(e) => setTopOnly(e.target.checked)} />
                        Top 300 only
                    </label>
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
                    <Button size="sm" variant="ghost" onClick={() => setSettings(RESTOCK_DEFAULTS)}>Reset</Button>
                </Card>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => {
                            setSegment(null);
                            setBucket(null);
                            setOutOnly(!outOnly);
                        }}
                        className={cn(
                            'rounded-lg border border-border px-3 py-2 text-left transition',
                            outOnly ? 'border-foreground shadow-sm' : 'hover:border-muted-foreground',
                        )}
                    >
                        <p className="text-xs font-semibold">Stocked-out sellers · {formatNumber(summary?.out_of_stock_sellers ?? 0)}</p>
                        <p className="text-[11px] text-muted-foreground">selling, nothing on the shelf</p>
                    </button>

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
                                onClick={() => {
                                    setSegment(null);
                                    setOutOnly(false);
                                    setBucket(bucket === key ? null : key);
                                }}
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
                                {
                                    key: 'blocked_bar',
                                    header: '',
                                    width: '90px',
                                    render: (row) => {
                                        const worst = Math.max(1, ...(data?.categories ?? []).map((c) => c.blocked_value));
                                        return (
                                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                <div className="h-full rounded-full bg-muted-foreground" style={{ width: `${(row.blocked_value / worst) * 100}%` }} />
                                            </div>
                                        );
                                    },
                                },
                                { key: 'sales_share_pct', header: 'Share of sales', align: 'right', sortable: true, value: (row) => row.sales_share_pct, render: (row) => `${row.sales_share_pct}%` },
                            ]}
                        />
                    </ChartCard>
                )}

                <ChartCard
                    title="Restock list"
                    subtitle={`${formatNumber(rows.length)} SKUs in view · ${formatNumber(po.lines.length)} on the order (${formatNumber(suggestedCount)} suggested)`}
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
                        {(bucket || segment || outOnly) && (
                            <Button size="xs" variant="ghost" onClick={clearFilters}>
                                {outOnly ? 'stocked out' : (segment ?? BUCKET_LABEL[bucket ?? ''])} ✕
                            </Button>
                        )}

                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            {touchedPo && (
                                <Button
                                    size="xs"
                                    variant="ghost"
                                    title="Back to the suggested order at default quantities"
                                    onClick={() => {
                                        setPoInclude({});
                                        setPoQty({});
                                    }}
                                >
                                    <RotateCcw className="size-3" />
                                    Reset PO
                                </Button>
                            )}
                            <Button size="xs" variant="outline" onClick={() => reportDownload(exportViewCsv(rows, qtyFor))}>
                                <Download className="size-3" />
                                View CSV
                            </Button>
                            <PoColumnPicker selected={poColumns} onChange={setPoColumns} />
                            <Button
                                size="xs"
                                variant="outline"
                                onClick={() => {
                                    if (poColumns.length === 0) return toast.error('Pick at least one PO column first.');
                                    if (requirePoLines()) reportDownload(exportPoCsv(po.lines, poColumns, po.budget));
                                }}
                            >
                                <Download className="size-3" />
                                PO CSV ({formatNumber(po.lines.length)})
                            </Button>
                            <Button
                                size="xs"
                                variant="outline"
                                title="Fixed supplier format: SKU, IMAGE-NAME, DESCRIPTION, PO QTY, REMARKS"
                                onClick={() => requirePoLines() && reportDownload(exportAnjaniPo(po.lines))}
                            >
                                <Download className="size-3" />
                                Anjani PO
                            </Button>
                            <Button
                                size="xs"
                                onClick={() => {
                                    if (poColumns.length === 0) return toast.error('Pick at least one PO column first.');
                                    if (requirePoLines()) {
                                        reportDownload(exportPoPhotos(po.lines, poColumns, { budget: po.budget, fittedValue: po.fittedValue, hasEstimatedCost, settings }));
                                    }
                                }}
                            >
                                <FileImage className="size-3" />
                                PO with photos
                            </Button>
                        </div>
                    </div>

                    <DataTable<RestockRow> rows={rows} rowKey={(row) => row.sku_id} columns={columns} onRowClick={(row) => setOpenSku(row.sku_id)} />
                </ChartCard>

                <SkuDrawer
                    row={openRow}
                    settings={settings}
                    included={openRow ? isOn(openRow) : false}
                    quantity={openRow ? qtyFor(openRow) : 0}
                    onToggleInclude={togglePo}
                    onQuantity={setQty}
                    onClose={() => setOpenSku(null)}
                />
            </PermissionGuard>
        </AppLayout>
    );
}
