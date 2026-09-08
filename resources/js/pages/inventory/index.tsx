import { Head, Link } from '@inertiajs/react';
import {
    ArrowRightLeft, Boxes, CalendarClock, ClipboardList, Layers, Loader2, Pencil, Plus, Trash2,
    ScrollText, Truck, Upload, Warehouse,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { DataTable, type Column } from '@/components/app/data-table';
import { VerdictNote } from '@/components/app/verdict-note';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input, Label } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { SkeletonChart } from '@/components/ui/skeleton';
import { WidgetError } from '@/components/app/empty-state';
import { PermissionGuard } from '@/components/app/permission-guard';
import { apiGet, apiSend } from '@/lib/api';
import { usePermissions } from '@/hooks/use-permissions';
import { formatCurrency, formatDate, formatDateTime, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Verdict } from '@/types';

interface StockRow {
    sku_id: number;
    sku_code: string;
    name: string;
    category: string | null;
    barcode: string | null;
    supplier_name: string | null;
    tracks_inventory: boolean;
    on_hand: number;
    reserved: number;
    available: number;
    incoming: number;
    cost_price: number;
    selling_price: number;
    stock_value: number;
    units_30d: number;
    daily_rate: number;
    days_of_cover: number;
    reorder_point: number;
    reorder_point_is_manual: boolean;
    safety_stock: number | null;
    reorder_quantity: number | null;
    lead_time_days: number | null;
    needs_reorder: boolean;
}

interface LocationRow {
    id: number;
    name: string;
    type: string;
    city: string | null;
    state: string | null;
    is_default: boolean;
    units: number;
}

interface LevelsPayload {
    rows: StockRow[];
    summary: {
        skus: number;
        stock_value: number;
        units: number;
        reserved: number;
        out_of_stock: number;
        needs_reorder: number;
        overstock: number;
    };
    verdict: Verdict | null;
    locations: LocationRow[];
    reasons: { value: string; label: string }[];
}

interface MovementRow {
    id: number;
    type_label: string;
    quantity: number;
    balance_after: number;
    reason: string | null;
    note: string | null;
    location: string | null;
    by: string;
    reference: string | null;
    happened_at: string | null;
}

const FILTERS = [
    { key: 'all', label: 'All' },
    { key: 'reorder', label: 'Needs reorder' },
    { key: 'out_of_stock', label: 'Out of stock' },
    { key: 'overstock', label: 'Overstock' },
    { key: 'untracked', label: 'Not tracked' },
];

export default function InventoryIndex() {
    const { can } = usePermissions();
    const [data, setData] = useState<LevelsPayload | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('all');
    const [locationId, setLocationId] = useState<string>('all');
    const [adjusting, setAdjusting] = useState<StockRow | null>(null);
    const [ledgerFor, setLedgerFor] = useState<StockRow | null>(null);
    const [importing, setImporting] = useState(false);
    const [transferring, setTransferring] = useState(false);
    const [locationsOpen, setLocationsOpen] = useState(false);
    const [batchesFor, setBatchesFor] = useState<StockRow | null>(null);
    const [bundleFor, setBundleFor] = useState<StockRow | null>(null);
    const [expiryOpen, setExpiryOpen] = useState(false);

    const load = useCallback(() => {
        setLoading(true);
        apiGet<LevelsPayload>('/inventory/levels', {
            filter,
            location_id: locationId === 'all' ? '' : locationId,
        })
            .then((response) => {
                setData(response.data);
                setError(null);
            })
            .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Could not load stock.'))
            .finally(() => setLoading(false));
    }, [filter, locationId]);

    useEffect(load, [load]);

    const summary = data?.summary;

    const columns: Column<StockRow>[] = useMemo(() => [
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
        { key: 'supplier_name', header: 'Supplier', render: (row) => row.supplier_name ?? '—' },
        {
            key: 'on_hand',
            header: 'On hand',
            align: 'right',
            sortable: true,
            value: (row) => row.on_hand,
            render: (row) =>
                row.tracks_inventory ? (
                    <span className={cn('tnum font-medium', row.on_hand <= 0 && 'text-bad')}>{formatNumber(row.on_hand)}</span>
                ) : (
                    <Badge variant="muted">not tracked</Badge>
                ),
        },
        { key: 'reserved', header: 'Reserved', align: 'right', sortable: true, value: (row) => row.reserved, render: (row) => formatNumber(row.reserved) },
        { key: 'available', header: 'Available', align: 'right', sortable: true, value: (row) => row.available, render: (row) => formatNumber(row.available) },
        {
            key: 'incoming',
            header: 'Incoming',
            align: 'right',
            sortable: true,
            value: (row) => row.incoming,
            render: (row) => (row.incoming > 0 ? <span className="tnum text-good">+{formatNumber(row.incoming)}</span> : '—'),
        },
        {
            key: 'days_of_cover',
            header: 'Cover',
            align: 'right',
            sortable: true,
            value: (row) => row.days_of_cover,
            tooltip: 'Days of stock left at the last 30 days of sell-through.',
            render: (row) =>
                row.days_of_cover >= 999 ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    <span className={cn('tnum', row.needs_reorder && 'text-warn')}>{row.days_of_cover}d</span>
                ),
        },
        {
            key: 'reorder_point',
            header: 'Reorder at',
            align: 'right',
            sortable: true,
            value: (row) => row.reorder_point,
            render: (row) => (
                <span className={cn('tnum', !row.reorder_point_is_manual && 'text-muted-foreground')}>
                    {formatNumber(row.reorder_point)}
                    {!row.reorder_point_is_manual && <span className="ml-1 text-[10px]">auto</span>}
                </span>
            ),
        },
        { key: 'stock_value', header: 'Value at cost', align: 'right', sortable: true, value: (row) => row.stock_value, render: (row) => formatCurrency(row.stock_value) },
        {
            key: 'actions',
            header: '',
            render: (row) => (
                <div className="flex justify-end gap-0.5">
                    <Button variant="ghost" size="icon" aria-label={`Ledger for ${row.sku_code}`} onClick={() => setLedgerFor(row)}>
                        <ScrollText className="size-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" aria-label={`Batches for ${row.sku_code}`} onClick={() => setBatchesFor(row)}>
                        <Layers className="size-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" aria-label={`Bundle for ${row.sku_code}`} onClick={() => setBundleFor(row)}>
                        <Boxes className="size-3.5" />
                    </Button>
                    {can('catalog.stock.manage') && (
                        <Button variant="ghost" size="icon" aria-label={`Adjust ${row.sku_code}`} onClick={() => setAdjusting(row)}>
                            <Pencil className="size-3.5" />
                        </Button>
                    )}
                </div>
            ),
        },
    ], [can]);

    return (
        <AppLayout
            title="Inventory"
            description="Stock you hold, what it is worth, and every change behind it"
            showFilters={false}
            actions={
                <div className="flex flex-wrap items-center gap-1.5">
                    <Button variant="outline" size="sm" onClick={() => setLocationsOpen(true)}>
                        <Warehouse className="size-3.5" /> Locations
                    </Button>
                    <PermissionGuard permission="catalog.purchase_orders.view">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/inventory/purchasing">
                                <Truck className="size-3.5" /> Purchasing
                            </Link>
                        </Button>
                    </PermissionGuard>
                    <PermissionGuard permission="catalog.stock_counts.view">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/inventory/counts">
                                <ClipboardList className="size-3.5" /> Counts
                            </Link>
                        </Button>
                    </PermissionGuard>
                    <PermissionGuard permission="catalog.batches.view">
                        <Button variant="outline" size="sm" onClick={() => setExpiryOpen(true)}>
                            <CalendarClock className="size-3.5" /> Expiry
                        </Button>
                    </PermissionGuard>
                    <PermissionGuard permission="catalog.transfers.manage">
                        <Button variant="outline" size="sm" onClick={() => setTransferring(true)}>
                            <ArrowRightLeft className="size-3.5" /> Transfer
                        </Button>
                    </PermissionGuard>
                    <PermissionGuard permission="catalog.stock.manage">
                        <Button size="sm" onClick={() => setImporting(true)}>
                            <Upload className="size-3.5" /> Opening stock
                        </Button>
                    </PermissionGuard>
                </div>
            }
        >
            <Head title="Inventory" />

            {error && <WidgetError message={error} onRetry={load} />}
            {!data && !error && <SkeletonChart className="h-64" />}

            {data && (
                <>
                    <VerdictNote verdict={data.verdict} />

                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        {[
                            { label: 'Stock at cost', value: formatCurrency(summary?.stock_value ?? 0) },
                            { label: 'Units on hand', value: formatNumber(summary?.units ?? 0) },
                            { label: 'SKUs tracked', value: formatNumber(summary?.skus ?? 0) },
                            { label: 'Out of stock', value: formatNumber(summary?.out_of_stock ?? 0), tone: (summary?.out_of_stock ?? 0) > 0 ? 'bad' : undefined },
                            { label: 'Need reorder', value: formatNumber(summary?.needs_reorder ?? 0), tone: (summary?.needs_reorder ?? 0) > 0 ? 'warn' : undefined },
                        ].map((card) => (
                            <Card key={card.label} className="p-4">
                                <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{card.label}</p>
                                <p className={cn('mt-1.5 text-xl font-semibold tnum', card.tone === 'bad' && 'text-bad', card.tone === 'warn' && 'text-warn')}>
                                    {card.value}
                                </p>
                            </Card>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Tabs value={filter} onValueChange={setFilter}>
                            <TabsList>
                                {FILTERS.map((item) => (
                                    <TabsTrigger key={item.key} value={item.key}>{item.label}</TabsTrigger>
                                ))}
                            </TabsList>
                        </Tabs>

                        <Select value={locationId} onValueChange={setLocationId}>
                            <SelectTrigger className="h-9 w-52"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All locations</SelectItem>
                                {data.locations.map((location) => (
                                    <SelectItem key={location.id} value={String(location.id)}>
                                        {location.name} ({formatNumber(location.units)})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <ChartCard
                        title="Stock levels"
                        subtitle={`${data.rows.length} SKUs`}
                        bodyClassName="p-0"
                        loading={loading && data === null}
                        empty={data.rows.length === 0}
                    >
                        <DataTable
                            columns={columns}
                            rows={data.rows}
                            searchable
                            searchPlaceholder="Search SKU, name or barcode…"
                            rowKey={(row) => row.sku_id}
                            maxHeight="34rem"
                            dense
                        />
                    </ChartCard>
                </>
            )}

            <AdjustSheet row={adjusting} reasons={data?.reasons ?? []} locations={data?.locations ?? []} onClose={() => setAdjusting(null)} onSaved={load} />
            <LedgerSheet row={ledgerFor} onClose={() => setLedgerFor(null)} />
            <ImportSheet open={importing} locations={data?.locations ?? []} onOpenChange={setImporting} onSaved={load} />
            <TransferSheet open={transferring} rows={data?.rows ?? []} locations={data?.locations ?? []} onOpenChange={setTransferring} onSaved={load} />
            <LocationsSheet open={locationsOpen} locations={data?.locations ?? []} onOpenChange={setLocationsOpen} onSaved={load} />
            <BatchesSheet row={batchesFor} locations={data?.locations ?? []} onClose={() => setBatchesFor(null)} onSaved={load} />
            <BundleSheet row={bundleFor} rows={data?.rows ?? []} onClose={() => setBundleFor(null)} onSaved={load} />
            <ExpirySheet open={expiryOpen} onOpenChange={setExpiryOpen} onSaved={load} />
        </AppLayout>
    );
}

function AdjustSheet({
    row,
    reasons,
    locations,
    onClose,
    onSaved,
}: {
    row: StockRow | null;
    reasons: { value: string; label: string }[];
    locations: LocationRow[];
    onClose: () => void;
    onSaved: () => void;
}) {
    const [mode, setMode] = useState<'delta' | 'set'>('delta');
    const [quantity, setQuantity] = useState('');
    const [reason, setReason] = useState('damaged');
    const [note, setNote] = useState('');
    const [locationId, setLocationId] = useState<string>('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (row) {
            setMode('delta');
            setQuantity('');
            setReason('damaged');
            setNote('');
            setLocationId(String(locations.find((l) => l.is_default)?.id ?? locations[0]?.id ?? ''));
        }
    }, [row, locations]);

    const save = async () => {
        if (!row || quantity === '') {
            toast.error('Enter a quantity.');
            return;
        }

        setSaving(true);
        try {
            const response = await apiSend<{ changed: boolean }>('POST', '/inventory/adjust', {
                sku_id: row.sku_id,
                location_id: locationId ? Number(locationId) : null,
                mode,
                quantity: Number(quantity),
                reason,
                note: note || null,
            });
            toast.success(response.message);
            onSaved();
            onClose();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not adjust stock.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open={row !== null} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Adjust {row?.sku_code}</SheetTitle>
                    <SheetDescription>
                        {row?.name} · currently {formatNumber(row?.on_hand ?? 0)} on hand. Every adjustment is recorded with its reason.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-4 px-4">
                    <Tabs value={mode} onValueChange={(value) => setMode(value as 'delta' | 'set')}>
                        <TabsList className="w-full">
                            <TabsTrigger value="delta" className="flex-1">Change by</TabsTrigger>
                            <TabsTrigger value="set" className="flex-1">Set to</TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <div className="space-y-1.5">
                        <Label htmlFor="adjust-qty">{mode === 'delta' ? 'Quantity (use a minus for stock going out)' : 'Counted quantity'}</Label>
                        <Input
                            id="adjust-qty"
                            type="number"
                            value={quantity}
                            onChange={(event) => setQuantity(event.target.value)}
                            placeholder={mode === 'delta' ? '-12' : '42'}
                        />
                    </div>

                    {locations.length > 1 && (
                        <div className="space-y-1.5">
                            <Label>Location</Label>
                            <Select value={locationId} onValueChange={setLocationId}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {locations.map((location) => (
                                        <SelectItem key={location.id} value={String(location.id)}>{location.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label>Reason</Label>
                        <Select value={reason} onValueChange={setReason}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {reasons.map((item) => (
                                    <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="adjust-note">Note</Label>
                        <Input id="adjust-note" value={note} onChange={(event) => setNote(event.target.value)} placeholder="Water damage in transit" />
                    </div>

                    <Button className="w-full" onClick={save} disabled={saving}>
                        {saving && <Loader2 className="size-4 animate-spin" />} Save adjustment
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function LedgerSheet({ row, onClose }: { row: StockRow | null; onClose: () => void }) {
    const [rows, setRows] = useState<MovementRow[] | null>(null);
    const [onHand, setOnHand] = useState(0);

    useEffect(() => {
        if (!row) {
            setRows(null);
            return;
        }

        apiGet<{ rows: MovementRow[]; on_hand: number }>(`/inventory/movements/${row.sku_id}`)
            .then((response) => {
                setRows(response.data.rows);
                setOnHand(response.data.on_hand);
            })
            .catch(() => setRows([]));
    }, [row]);

    return (
        <Sheet open={row !== null} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>{row?.sku_code} · stock ledger</SheetTitle>
                    <SheetDescription>
                        {formatNumber(onHand)} on hand. Every movement that got it there, newest first.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-1.5 overflow-y-auto px-4">
                    {rows === null && <p className="text-sm text-muted-foreground">Loading…</p>}
                    {rows?.length === 0 && <p className="text-sm text-muted-foreground">No movements recorded yet.</p>}

                    {rows?.map((movement) => (
                        <div key={movement.id} className="flex items-start justify-between gap-3 rounded-lg border border-border p-2.5">
                            <div className="min-w-0">
                                <p className="text-xs font-medium">
                                    {movement.type_label}
                                    {movement.reason && <span className="ml-1.5 text-muted-foreground">· {movement.reason.replace(/_/g, ' ')}</span>}
                                </p>
                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                    {movement.happened_at ? formatDateTime(movement.happened_at) : '—'} · {movement.by}
                                    {movement.location && ` · ${movement.location}`}
                                    {movement.reference && ` · ${movement.reference}`}
                                </p>
                                {movement.note && <p className="mt-0.5 text-[11px] italic text-muted-foreground">{movement.note}</p>}
                            </div>
                            <div className="shrink-0 text-right">
                                <p className={cn('text-sm font-semibold tnum', movement.quantity > 0 ? 'text-good' : 'text-bad')}>
                                    {movement.quantity > 0 ? '+' : ''}{formatNumber(movement.quantity)}
                                </p>
                                <p className="text-[11px] tnum text-muted-foreground">→ {formatNumber(movement.balance_after)}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function ImportSheet({
    open,
    locations,
    onOpenChange,
    onSaved,
}: {
    open: boolean;
    locations: LocationRow[];
    onOpenChange: (open: boolean) => void;
    onSaved: () => void;
}) {
    const [text, setText] = useState('');
    const [locationId, setLocationId] = useState('');
    const [saving, setSaving] = useState(false);
    const [result, setResult] = useState<{ applied: number; unchanged: number; unknown_skus: string[] } | null>(null);

    useEffect(() => {
        if (open) {
            setLocationId(String(locations.find((l) => l.is_default)?.id ?? locations[0]?.id ?? ''));
            setResult(null);
        }
    }, [open, locations]);

    const save = async () => {
        const rows = text
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean)
            .map((line) => {
                const [sku_code, quantity] = line.split(/[,\t]/).map((part) => part.trim());
                return { sku_code, quantity: Number(quantity) };
            })
            .filter((row) => row.sku_code && Number.isFinite(row.quantity));

        if (rows.length === 0) {
            toast.error('Paste at least one row of "SKU, quantity".');
            return;
        }

        setSaving(true);
        try {
            const response = await apiSend<{ applied: number; unchanged: number; unknown_skus: string[] }>(
                'POST',
                '/inventory/bulk-levels',
                { location_id: locationId ? Number(locationId) : null, rows },
            );
            setResult(response.data);
            toast.success(response.message);
            onSaved();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not import.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Set opening stock</SheetTitle>
                    <SheetDescription>
                        Paste one SKU per line as <code className="text-[11px]">SKU-CODE, quantity</code>. Each line sets the level
                        to that number and records the difference in the ledger.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-3 px-4">
                    {locations.length > 1 && (
                        <div className="space-y-1.5">
                            <Label>Location</Label>
                            <Select value={locationId} onValueChange={setLocationId}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {locations.map((location) => (
                                        <SelectItem key={location.id} value={String(location.id)}>{location.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label htmlFor="import-rows">Rows</Label>
                        <textarea
                            id="import-rows"
                            value={text}
                            onChange={(event) => setText(event.target.value)}
                            rows={12}
                            placeholder={'KL-102, 120\nKL-210, 45'}
                            className="w-full rounded-(--radius-input) border border-border bg-background px-3 py-2 font-mono text-xs"
                        />
                    </div>

                    {result && (
                        <Card className="space-y-1 p-3 text-xs">
                            <p><span className="font-medium">{result.applied}</span> SKUs updated · {result.unchanged} already matched</p>
                            {result.unknown_skus.length > 0 && (
                                <p className="text-bad">
                                    Not recognised: {result.unknown_skus.join(', ')}
                                </p>
                            )}
                        </Card>
                    )}

                    <Button className="w-full" onClick={save} disabled={saving}>
                        {saving && <Loader2 className="size-4 animate-spin" />} Apply
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function TransferSheet({
    open,
    rows,
    locations,
    onOpenChange,
    onSaved,
}: {
    open: boolean;
    rows: StockRow[];
    locations: LocationRow[];
    onOpenChange: (open: boolean) => void;
    onSaved: () => void;
}) {
    const [skuId, setSkuId] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [quantity, setQuantity] = useState('');
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const response = await apiSend<null>('POST', '/inventory/transfer', {
                sku_id: Number(skuId),
                from_location_id: Number(from),
                to_location_id: Number(to),
                quantity: Number(quantity),
            });
            toast.success(response.message);
            setQuantity('');
            onSaved();
            onOpenChange(false);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not transfer.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Move stock between locations</SheetTitle>
                    <SheetDescription>Recorded as a matched pair, so the total you hold never changes.</SheetDescription>
                </SheetHeader>

                <div className="space-y-3 px-4">
                    <div className="space-y-1.5">
                        <Label>SKU</Label>
                        <Select value={skuId} onValueChange={setSkuId}>
                            <SelectTrigger><SelectValue placeholder="Pick a SKU" /></SelectTrigger>
                            <SelectContent>
                                {rows.slice(0, 300).map((row) => (
                                    <SelectItem key={row.sku_id} value={String(row.sku_id)}>
                                        {row.sku_code} — {row.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1.5">
                            <Label>From</Label>
                            <Select value={from} onValueChange={setFrom}>
                                <SelectTrigger><SelectValue placeholder="Source" /></SelectTrigger>
                                <SelectContent>
                                    {locations.map((location) => (
                                        <SelectItem key={location.id} value={String(location.id)}>{location.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>To</Label>
                            <Select value={to} onValueChange={setTo}>
                                <SelectTrigger><SelectValue placeholder="Destination" /></SelectTrigger>
                                <SelectContent>
                                    {locations.filter((location) => String(location.id) !== from).map((location) => (
                                        <SelectItem key={location.id} value={String(location.id)}>{location.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="transfer-qty">Quantity</Label>
                        <Input id="transfer-qty" type="number" min={1} value={quantity} onChange={(event) => setQuantity(event.target.value)} />
                    </div>

                    <Button className="w-full" onClick={save} disabled={saving || !skuId || !from || !to || !quantity}>
                        {saving && <Loader2 className="size-4 animate-spin" />} Move stock
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function LocationsSheet({
    open,
    locations,
    onOpenChange,
    onSaved,
}: {
    open: boolean;
    locations: LocationRow[];
    onOpenChange: (open: boolean) => void;
    onSaved: () => void;
}) {
    const { can } = usePermissions();
    const [name, setName] = useState('');
    const [type, setType] = useState('warehouse');
    const [city, setCity] = useState('');
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const response = await apiSend<null>('POST', '/inventory/locations', {
                name,
                type,
                city: city || null,
                is_active: true,
                is_default: locations.length === 0,
            });
            toast.success(response.message);
            setName('');
            setCity('');
            onSaved();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save the location.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Warehouses & locations</SheetTitle>
                    <SheetDescription>Stock is held per location, so cover and transfers are per location too.</SheetDescription>
                </SheetHeader>

                <div className="space-y-3 px-4">
                    <div className="space-y-1.5">
                        {locations.map((location) => (
                            <Card key={location.id} className="flex items-center justify-between gap-2 p-3">
                                <div>
                                    <p className="text-xs font-medium">
                                        {location.name}
                                        {location.is_default && <Badge variant="muted" className="ml-1.5">default</Badge>}
                                    </p>
                                    <p className="text-[11px] text-muted-foreground">
                                        {location.type}{location.city ? ` · ${location.city}` : ''} · {formatNumber(location.units)} units
                                    </p>
                                </div>
                            </Card>
                        ))}
                    </div>

                    {can('catalog.locations.manage') && (
                        <div className="space-y-2 border-t border-border pt-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="loc-name">Name</Label>
                                <Input id="loc-name" value={name} onChange={(event) => setName(event.target.value)} placeholder="Bhiwandi warehouse" />
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                <div className="space-y-1.5">
                                    <Label>Type</Label>
                                    <Select value={type} onValueChange={setType}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="warehouse">Warehouse</SelectItem>
                                            <SelectItem value="store">Retail store</SelectItem>
                                            <SelectItem value="3pl">3PL</SelectItem>
                                            <SelectItem value="virtual">Virtual</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="loc-city">City</Label>
                                    <Input id="loc-city" value={city} onChange={(event) => setCity(event.target.value)} />
                                </div>
                            </div>
                            <Button className="w-full" size="sm" onClick={save} disabled={saving || name.trim() === ''}>
                                {saving ? <Loader2 className="size-4 animate-spin" /> : <Plus className="size-3.5" />} Add location
                            </Button>
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

interface BatchRow {
    id: number;
    batch_code: string;
    location: string | null;
    quantity: number;
    unit_cost: number;
    value: number;
    expires_on: string | null;
    days_to_expiry: number | null;
    is_expired: boolean;
}

function BatchesSheet({
    row,
    locations,
    onClose,
    onSaved,
}: {
    row: StockRow | null;
    locations: LocationRow[];
    onClose: () => void;
    onSaved: () => void;
}) {
    const { can } = usePermissions();
    const [data, setData] = useState<{ rows: BatchRow[]; total_units: number; total_value: number } | null>(null);
    const [code, setCode] = useState('');
    const [quantity, setQuantity] = useState('');
    const [cost, setCost] = useState('');
    const [expires, setExpires] = useState('');
    const [saving, setSaving] = useState(false);

    const load = useCallback(() => {
        if (!row) return;
        apiGet<{ rows: BatchRow[]; total_units: number; total_value: number }>(`/inventory/batches/${row.sku_id}`)
            .then((response) => setData(response.data))
            .catch(() => setData(null));
    }, [row]);

    useEffect(() => {
        if (row) {
            load();
            setCode('');
            setQuantity('');
            setCost(String((row.cost_price / 100).toFixed(2)));
            setExpires('');
        } else {
            setData(null);
        }
    }, [row, load]);

    const save = async () => {
        if (!row) return;
        setSaving(true);
        try {
            const response = await apiSend<null>('POST', '/inventory/batches', {
                sku_id: row.sku_id,
                batch_code: code,
                quantity: Number(quantity),
                unit_cost: Number(cost || 0),
                expires_on: expires || null,
                location_id: locations.find((l) => l.is_default)?.id ?? null,
            });
            toast.success(response.message);
            setCode('');
            setQuantity('');
            load();
            onSaved();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not book the batch in.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open={row !== null} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>{row?.sku_code} · batches</SheetTitle>
                    <SheetDescription>
                        Stock leaves closest-expiry-first. {formatNumber(data?.total_units ?? 0)} units in batches,
                        worth {formatCurrency(data?.total_value ?? 0)} at what they actually cost.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-3 overflow-y-auto px-4">
                    {data?.rows.length === 0 && <p className="text-sm text-muted-foreground">No batches yet.</p>}

                    {data?.rows.map((batch) => (
                        <Card key={batch.id} className="flex items-center justify-between gap-3 p-3">
                            <div className="min-w-0">
                                <p className="text-xs font-medium">
                                    {batch.batch_code}
                                    {batch.is_expired && <Badge variant="bad" className="ml-1.5">expired</Badge>}
                                </p>
                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                    {formatNumber(batch.quantity)} units · {formatCurrency(batch.unit_cost)}/unit
                                    {batch.expires_on && ` · expires ${formatDate(batch.expires_on)}`}
                                    {batch.days_to_expiry !== null && !batch.is_expired && ` (${batch.days_to_expiry}d)`}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <span className="text-xs font-semibold tnum">{formatCurrency(batch.value)}</span>
                                {can('catalog.batches.manage') && batch.quantity > 0 && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Write off ${batch.batch_code}`}
                                        onClick={async () => {
                                            try {
                                                const response = await apiSend<null>('POST', `/inventory/batches/${batch.id}/write-off`);
                                                toast.success(response.message);
                                                load();
                                                onSaved();
                                            } catch (error) {
                                                toast.error(error instanceof Error ? error.message : 'Could not write it off.');
                                            }
                                        }}
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                )}
                            </div>
                        </Card>
                    ))}

                    {can('catalog.batches.manage') && (
                        <div className="space-y-2 border-t border-border pt-3">
                            <p className="text-xs font-medium">Book a batch in</p>
                            <div className="grid grid-cols-2 gap-2">
                                <div className="space-y-1">
                                    <Label htmlFor="batch-code">Batch code</Label>
                                    <Input id="batch-code" value={code} onChange={(event) => setCode(event.target.value)} placeholder="B-2609" />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="batch-qty">Quantity</Label>
                                    <Input id="batch-qty" type="number" min={1} value={quantity} onChange={(event) => setQuantity(event.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="batch-cost">Unit cost (₹)</Label>
                                    <Input id="batch-cost" type="number" value={cost} onChange={(event) => setCost(event.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="batch-expires">Expires on</Label>
                                    <Input id="batch-expires" type="date" value={expires} onChange={(event) => setExpires(event.target.value)} />
                                </div>
                            </div>
                            <Button size="sm" className="w-full" onClick={save} disabled={saving || !code || !quantity}>
                                {saving && <Loader2 className="size-4 animate-spin" />} Book in
                            </Button>
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function BundleSheet({
    row,
    rows,
    onClose,
    onSaved,
}: {
    row: StockRow | null;
    rows: StockRow[];
    onClose: () => void;
    onSaved: () => void;
}) {
    const { can } = usePermissions();
    const [data, setData] = useState<{ buildable: number; limiting_sku: string | null; components: Record<string, unknown>[] } | null>(null);
    const [lines, setLines] = useState<{ sku_id: string; quantity: string }[]>([]);
    const [saving, setSaving] = useState(false);

    const load = useCallback(() => {
        if (!row) return;
        apiGet<{ buildable: number; limiting_sku: string | null; components: Record<string, unknown>[] }>(`/inventory/bundles/${row.sku_id}`)
            .then((response) => {
                setData(response.data);
                setLines(response.data.components.map((component) => ({
                    sku_id: String(component.sku_id),
                    quantity: String(component.required_per_bundle),
                })));
            })
            .catch(() => setData(null));
    }, [row]);

    useEffect(() => {
        if (row) load();
        else setData(null);
    }, [row, load]);

    const save = async () => {
        if (!row) return;
        setSaving(true);
        try {
            const response = await apiSend<null>('PUT', `/inventory/bundles/${row.sku_id}`, {
                components: lines
                    .filter((line) => line.sku_id && Number(line.quantity) > 0)
                    .map((line) => ({ sku_id: Number(line.sku_id), quantity: Number(line.quantity) })),
            });
            toast.success(response.message);
            load();
            onSaved();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save the bundle.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open={row !== null} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{row?.sku_code} · bundle</SheetTitle>
                    <SheetDescription>
                        A bundle holds no stock of its own — it can only be built as far as its scarcest component allows.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-3 px-4">
                    {data && lines.length > 0 && (
                        <Card className="p-3">
                            <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Buildable right now</p>
                            <p className="mt-1 text-2xl font-semibold tnum">{formatNumber(data.buildable)}</p>
                            {data.limiting_sku && (
                                <p className="mt-0.5 text-[11px] text-muted-foreground">Limited by {data.limiting_sku}</p>
                            )}
                        </Card>
                    )}

                    {can('catalog.bundles.manage') && (
                        <div className="space-y-2">
                            {lines.map((line, index) => (
                                <div key={index} className="flex items-end gap-1.5">
                                    <div className="flex-1 space-y-1">
                                        <Label className="text-[10px]">Component</Label>
                                        <Select
                                            value={line.sku_id}
                                            onValueChange={(value) => setLines((current) =>
                                                current.map((item, position) => (position === index ? { ...item, sku_id: value } : item)))}
                                        >
                                            <SelectTrigger><SelectValue placeholder="Pick a SKU" /></SelectTrigger>
                                            <SelectContent>
                                                {rows.filter((option) => option.sku_id !== row?.sku_id).slice(0, 300).map((option) => (
                                                    <SelectItem key={option.sku_id} value={String(option.sku_id)}>
                                                        {option.sku_code} — {option.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="w-20 space-y-1">
                                        <Label className="text-[10px]">Per set</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={line.quantity}
                                            onChange={(event) => setLines((current) =>
                                                current.map((item, position) => (position === index ? { ...item, quantity: event.target.value } : item)))}
                                        />
                                    </div>
                                    <Button variant="ghost" size="icon" aria-label="Remove component" onClick={() => setLines((current) => current.filter((_, position) => position !== index))}>
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}

                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" onClick={() => setLines((current) => [...current, { sku_id: '', quantity: '1' }])}>
                                    <Plus className="size-3.5" /> Add component
                                </Button>
                                <Button size="sm" onClick={save} disabled={saving}>
                                    {saving && <Loader2 className="size-4 animate-spin" />} Save bundle
                                </Button>
                            </div>

                            {lines.length === 0 && (
                                <p className="text-[11px] text-muted-foreground">
                                    No components — this SKU carries its own stock. Add one to turn it into a bundle.
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function ExpirySheet({
    open,
    onOpenChange,
    onSaved,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSaved: () => void;
}) {
    const { can } = usePermissions();
    const [data, setData] = useState<{
        rows: (BatchRow & { sku_code: string; name: string; value_at_cost: number })[];
        value_at_risk: number;
        expired_units: number;
    } | null>(null);
    const [days, setDays] = useState('90');

    const load = useCallback(() => {
        apiGet<{ rows: (BatchRow & { sku_code: string; name: string; value_at_cost: number })[]; value_at_risk: number; expired_units: number }>(
            '/inventory/batches/expiring',
            { within_days: days },
        )
            .then((response) => setData(response.data))
            .catch(() => setData(null));
    }, [days]);

    useEffect(() => {
        if (open) load();
    }, [open, load]);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>Expiring stock</SheetTitle>
                    <SheetDescription>
                        {formatCurrency(data?.value_at_risk ?? 0)} at cost is on a clock
                        {(data?.expired_units ?? 0) > 0 && `, and ${formatNumber(data?.expired_units ?? 0)} units have already gone`}.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-3 overflow-y-auto px-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="expiry-days">Look ahead (days)</Label>
                        <Input id="expiry-days" type="number" min={1} value={days} onChange={(event) => setDays(event.target.value)} />
                    </div>

                    {data?.rows.length === 0 && (
                        <p className="text-sm text-muted-foreground">Nothing expires inside that window.</p>
                    )}

                    {data?.rows.map((batch) => (
                        <Card key={batch.id} className="flex items-center justify-between gap-3 p-3">
                            <div className="min-w-0">
                                <p className="text-xs font-medium">
                                    {batch.sku_code} · {batch.batch_code}
                                    {batch.is_expired && <Badge variant="bad" className="ml-1.5">expired</Badge>}
                                </p>
                                <p className="mt-0.5 truncate text-[11px] text-muted-foreground">
                                    {batch.name} · {formatNumber(batch.quantity)} units
                                    {batch.expires_on && ` · ${formatDate(batch.expires_on)}`}
                                    {batch.days_to_expiry !== null && !batch.is_expired && ` (${batch.days_to_expiry}d left)`}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <span className="text-xs font-semibold tnum">{formatCurrency(batch.value_at_cost)}</span>
                                {can('catalog.batches.manage') && batch.is_expired && (
                                    <Button
                                        size="xs"
                                        variant="outline"
                                        onClick={async () => {
                                            try {
                                                const response = await apiSend<null>('POST', `/inventory/batches/${batch.id}/write-off`);
                                                toast.success(response.message);
                                                load();
                                                onSaved();
                                            } catch (error) {
                                                toast.error(error instanceof Error ? error.message : 'Could not write it off.');
                                            }
                                        }}
                                    >
                                        Write off
                                    </Button>
                                )}
                            </div>
                        </Card>
                    ))}
                </div>
            </SheetContent>
        </Sheet>
    );
}
