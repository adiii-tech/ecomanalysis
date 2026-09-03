import { Head } from '@inertiajs/react';
import { CheckCheck, ClipboardList, Loader2, Plus } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { DataTable, type Column } from '@/components/app/data-table';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input, Label } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { SkeletonChart } from '@/components/ui/skeleton';
import { WidgetError } from '@/components/app/empty-state';
import { PermissionGuard } from '@/components/app/permission-guard';
import { apiGet, apiSend } from '@/lib/api';
import { usePermissions } from '@/hooks/use-permissions';
import { formatCurrency, formatDateTime, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface CountRow {
    id: number;
    reference: string;
    status: string;
    scope: string;
    location: string | null;
    items: number;
    created_at: string | null;
    applied_at: string | null;
}

interface CountItem {
    id: number;
    sku_code: string;
    name: string;
    expected_quantity: number;
    counted_quantity: number | null;
    variance: number | null;
    variance_value: number | null;
    reason: string | null;
}

interface CountDetail {
    count: { id: number; reference: string; status: string; location: string | null; applied_at: string | null };
    items: CountItem[];
    summary: { counted: number; total: number; variance_units: number; variance_value: number };
    reasons: { value: string; label: string }[];
}

export default function StockCounts() {
    const { can } = usePermissions();
    const [rows, setRows] = useState<CountRow[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [detail, setDetail] = useState<CountDetail | null>(null);
    const [creating, setCreating] = useState(false);

    const load = useCallback(() => {
        apiGet<{ rows: CountRow[] }>('/inventory/counts')
            .then((response) => { setRows(response.data.rows); setError(null); })
            .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Could not load counts.'));
    }, []);

    useEffect(load, [load]);

    const open = async (id: number) => {
        try {
            const response = await apiGet<CountDetail>(`/inventory/counts/${id}`);
            setDetail(response.data);
        } catch {
            toast.error('Could not open that count.');
        }
    };

    const columns: Column<CountRow>[] = [
        {
            key: 'reference',
            header: 'Reference',
            sortable: true,
            value: (row) => row.reference,
            render: (row) => (
                <button type="button" className="text-xs font-medium text-primary hover:underline" onClick={() => open(row.id)}>
                    {row.reference}
                </button>
            ),
        },
        { key: 'status', header: 'Status', render: (row) => <Badge variant={row.status === 'applied' ? 'good' : 'warn'}>{row.status}</Badge> },
        { key: 'scope', header: 'Scope', render: (row) => row.scope },
        { key: 'location', header: 'Location', render: (row) => row.location ?? '—' },
        { key: 'items', header: 'SKUs', align: 'right', sortable: true, value: (row) => row.items, render: (row) => formatNumber(row.items) },
        { key: 'created_at', header: 'Opened', render: (row) => (row.created_at ? formatDateTime(row.created_at) : '—') },
        { key: 'applied_at', header: 'Applied', render: (row) => (row.applied_at ? formatDateTime(row.applied_at) : '—') },
    ];

    return (
        <AppLayout
            title="Stock counts"
            description="Count the shelf, compare it with the system, correct the difference"
            showFilters={false}
            breadcrumb={{ label: 'Inventory', href: '/inventory' }}
            actions={
                <PermissionGuard permission="catalog.stock_counts.manage">
                    <Button size="sm" onClick={() => setCreating(true)}>
                        <Plus className="size-3.5" /> Start a count
                    </Button>
                </PermissionGuard>
            }
        >
            <Head title="Stock counts" />

            {error && <WidgetError message={error} onRetry={load} />}
            {!rows && !error && <SkeletonChart className="h-64" />}

            {rows && (
                <ChartCard
                    title="Counts"
                    subtitle="A count freezes what the system believed when it was opened, so the variance is measured against that moment"
                    bodyClassName="p-0"
                    empty={rows.length === 0}
                    emptyState={
                        <div className="p-10 text-center">
                            <ClipboardList className="mx-auto size-8 text-muted-foreground" />
                            <p className="mt-2 text-sm text-muted-foreground">No counts yet. Start one to reconcile the shelf with the system.</p>
                        </div>
                    }
                >
                    <DataTable columns={columns} rows={rows} rowKey={(row) => row.id} dense />
                </ChartCard>
            )}

            <CountSheet detail={detail} canManage={can('catalog.stock_counts.manage')} onClose={() => setDetail(null)} onChanged={() => { load(); setDetail(null); }} />
            <CreateSheet open={creating} onOpenChange={setCreating} onCreated={(id) => { load(); void open(id); }} />
        </AppLayout>
    );
}

function CountSheet({
    detail,
    canManage,
    onClose,
    onChanged,
}: {
    detail: CountDetail | null;
    canManage: boolean;
    onClose: () => void;
    onChanged: () => void;
}) {
    const [counts, setCounts] = useState<Record<number, string>>({});
    const [reasons, setReasons] = useState<Record<number, string>>({});
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (detail) {
            setCounts(Object.fromEntries(detail.items.map((item) => [item.id, item.counted_quantity === null ? '' : String(item.counted_quantity)])));
            setReasons(Object.fromEntries(detail.items.map((item) => [item.id, item.reason ?? ''])));
        }
    }, [detail]);

    if (!detail) return null;

    const applied = detail.count.status === 'applied';

    const save = async (thenApply: boolean) => {
        setBusy(true);
        try {
            await apiSend('PUT', `/inventory/counts/${detail.count.id}`, {
                items: detail.items.map((item) => ({
                    id: item.id,
                    counted_quantity: counts[item.id] === '' ? null : Number(counts[item.id]),
                    reason: reasons[item.id] || null,
                })),
            });

            if (!thenApply) {
                toast.success('Count saved.');
                setBusy(false);
                return;
            }

            const response = await apiSend<null>('POST', `/inventory/counts/${detail.count.id}/apply`);
            toast.success(response.message);
            onChanged();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save the count.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full sm:max-w-3xl">
                <SheetHeader>
                    <SheetTitle className="flex items-center gap-2">
                        {detail.count.reference}
                        <Badge variant={applied ? 'good' : 'warn'}>{detail.count.status}</Badge>
                    </SheetTitle>
                    <SheetDescription>
                        {detail.count.location ?? 'Default location'} · {detail.summary.counted} of {detail.summary.total} counted.
                        Leave a line blank if nobody counted it — blank is not zero.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-2 overflow-y-auto px-4">
                    {detail.items.map((item) => {
                        const typed = counts[item.id];
                        const variance = typed === '' || typed === undefined ? null : Number(typed) - item.expected_quantity;

                        return (
                            <div key={item.id} className="flex flex-wrap items-end gap-2 rounded-lg border border-border p-2.5">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-xs font-medium">{item.sku_code}</p>
                                    <p className="truncate text-[11px] text-muted-foreground">{item.name}</p>
                                    <p className="mt-0.5 text-[11px] text-muted-foreground">System says {formatNumber(item.expected_quantity)}</p>
                                </div>

                                <div className="w-24">
                                    <Label htmlFor={`count-${item.id}`} className="text-[10px]">Counted</Label>
                                    <Input
                                        id={`count-${item.id}`}
                                        type="number"
                                        min={0}
                                        disabled={applied || !canManage}
                                        value={typed ?? ''}
                                        onChange={(event) => setCounts((current) => ({ ...current, [item.id]: event.target.value }))}
                                    />
                                </div>

                                <div className="w-16 text-right">
                                    <p className="text-[10px] text-muted-foreground">Variance</p>
                                    <p className={cn('text-sm font-semibold tnum', variance === null && 'text-muted-foreground', (variance ?? 0) < 0 && 'text-bad', (variance ?? 0) > 0 && 'text-good')}>
                                        {variance === null ? '—' : `${variance > 0 ? '+' : ''}${variance}`}
                                    </p>
                                </div>

                                {variance !== null && variance !== 0 && !applied && (
                                    <div className="w-40">
                                        <Label className="text-[10px]">Reason</Label>
                                        <Select
                                            value={reasons[item.id] ?? ''}
                                            onValueChange={(value) => setReasons((current) => ({ ...current, [item.id]: value }))}
                                        >
                                            <SelectTrigger className="h-9"><SelectValue placeholder="Why?" /></SelectTrigger>
                                            <SelectContent>
                                                {detail.reasons.map((reason) => (
                                                    <SelectItem key={reason.value} value={reason.value}>{reason.label}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>

                {!applied && canManage && (
                    <div className="flex gap-2 border-t border-border px-4 py-3">
                        <Button variant="outline" size="sm" onClick={() => save(false)} disabled={busy}>Save progress</Button>
                        <Button size="sm" onClick={() => save(true)} disabled={busy}>
                            {busy ? <Loader2 className="size-4 animate-spin" /> : <CheckCheck className="size-3.5" />} Apply to stock
                        </Button>
                    </div>
                )}

                {applied && (
                    <div className="px-4 py-3">
                        <Card className="p-3 text-xs">
                            Applied {detail.count.applied_at ? formatDateTime(detail.count.applied_at) : ''} ·
                            net variance {formatNumber(detail.summary.variance_units)} units ({formatCurrency(detail.summary.variance_value)} at cost).
                        </Card>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

function CreateSheet({
    open,
    onOpenChange,
    onCreated,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (id: number) => void;
}) {
    const [scope, setScope] = useState('full');
    const [category, setCategory] = useState('');
    const [saving, setSaving] = useState(false);

    const create = async () => {
        setSaving(true);
        try {
            const response = await apiSend<{ id: number }>('POST', '/inventory/counts', {
                scope,
                category: scope === 'category' ? category : null,
            });
            toast.success(response.message);
            onOpenChange(false);
            onCreated(response.data.id);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not start the count.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Start a stock count</SheetTitle>
                    <SheetDescription>
                        The sheet records what the system believes right now, so a slow count is still measured against the right baseline.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-3 px-4">
                    <div className="space-y-1.5">
                        <Label>Scope</Label>
                        <Select value={scope} onValueChange={setScope}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="full">Everything</SelectItem>
                                <SelectItem value="category">One category</SelectItem>
                                <SelectItem value="reorder">Only what needs reordering</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {scope === 'category' && (
                        <div className="space-y-1.5">
                            <Label htmlFor="count-category">Category</Label>
                            <Input id="count-category" value={category} onChange={(event) => setCategory(event.target.value)} placeholder="Ethnic Wear" />
                        </div>
                    )}

                    <Button className="w-full" onClick={create} disabled={saving}>
                        {saving && <Loader2 className="size-4 animate-spin" />} Open count sheet
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
