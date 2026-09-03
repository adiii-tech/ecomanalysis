import { Head } from '@inertiajs/react';
import { Check, Loader2, PackageCheck, Plus, Send, Trash2, TriangleAlert, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { DataTable, type Column } from '@/components/app/data-table';
import { VerdictNote } from '@/components/app/verdict-note';
import { CaveatNote } from '@/components/app/caveat-note';
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
import { formatCurrency, formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Verdict } from '@/types';

interface OrderRow {
    id: number;
    po_number: string;
    status: string;
    supplier: string | null;
    location: string | null;
    expected_at: string | null;
    subtotal: number;
    total: number;
    items: number;
    is_overdue: boolean;
}

interface SupplierRow {
    id: number;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    lead_time_days: number;
    payment_terms_days: number;
    is_active: boolean;
    purchase_orders: number;
    skus: number;
}

interface OrderDetail {
    order: {
        id: number;
        po_number: string;
        status: string;
        supplier: string | null;
        location: string | null;
        expected_at: string | null;
        freight_cost: number;
        subtotal: number;
        tax_amount: number;
        total: number;
        notes: string | null;
        is_editable: boolean;
        is_receivable: boolean;
    };
    items: {
        id: number;
        sku_id: number;
        sku_code: string;
        name: string;
        quantity_ordered: number;
        quantity_received: number;
        outstanding: number;
        unit_cost: number;
        landed_unit_cost: number;
        current_cost: number;
        line_total: number;
    }[];
}

interface SuggestionGroup {
    supplier: string;
    skus: number;
    units: number;
    cost: number;
    rows: { sku_id: number; sku_code: string; name: string; on_hand: number; suggested_quantity: number; cost_price: number }[];
}

const STATUS_TONE: Record<string, string> = {
    draft: 'muted',
    sent: 'warn',
    partial: 'warn',
    received: 'good',
    cancelled: 'muted',
};

export default function Purchasing() {
    const { can } = usePermissions();
    const [tab, setTab] = useState('orders');
    const [orders, setOrders] = useState<{ rows: OrderRow[]; summary: Record<string, number>; verdict: Verdict | null } | null>(null);
    const [suppliers, setSuppliers] = useState<SupplierRow[] | null>(null);
    const [suggestions, setSuggestions] = useState<{ groups: SuggestionGroup[]; total_cost: number; caveat: string } | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [detail, setDetail] = useState<OrderDetail | null>(null);
    const [composing, setComposing] = useState(false);

    const load = useCallback(() => {
        apiGet<{ rows: OrderRow[]; summary: Record<string, number>; verdict: Verdict | null }>('/purchasing/orders')
            .then((response) => { setOrders(response.data); setError(null); })
            .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Could not load purchase orders.'));

        apiGet<{ rows: SupplierRow[] }>('/purchasing/suppliers')
            .then((response) => setSuppliers(response.data.rows))
            .catch(() => setSuppliers([]));

        apiGet<{ groups: SuggestionGroup[]; total_cost: number; caveat: string }>('/purchasing/orders/suggestions')
            .then((response) => setSuggestions(response.data))
            .catch(() => setSuggestions(null));
    }, []);

    useEffect(load, [load]);

    const openOrder = async (id: number) => {
        try {
            const response = await apiGet<OrderDetail>(`/purchasing/orders/${id}`);
            setDetail(response.data);
        } catch {
            toast.error('Could not open that order.');
        }
    };

    const orderColumns: Column<OrderRow>[] = [
        {
            key: 'po_number',
            header: 'PO',
            sortable: true,
            value: (row) => row.po_number,
            render: (row) => (
                <button type="button" className="text-xs font-medium text-primary hover:underline" onClick={() => openOrder(row.id)}>
                    {row.po_number}
                </button>
            ),
        },
        { key: 'supplier', header: 'Supplier', render: (row) => row.supplier ?? '—' },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <div className="flex items-center gap-1.5">
                    <Badge variant={(STATUS_TONE[row.status] ?? 'muted') as 'muted'}>{row.status}</Badge>
                    {row.is_overdue && <TriangleAlert className="size-3.5 text-bad" />}
                </div>
            ),
        },
        { key: 'items', header: 'Lines', align: 'right', sortable: true, value: (row) => row.items, render: (row) => formatNumber(row.items) },
        {
            key: 'expected_at',
            header: 'Expected',
            sortable: true,
            value: (row) => row.expected_at ?? '',
            render: (row) => (row.expected_at ? <span className={cn(row.is_overdue && 'text-bad')}>{formatDate(row.expected_at)}</span> : '—'),
        },
        { key: 'total', header: 'Total', align: 'right', sortable: true, value: (row) => row.total, render: (row) => formatCurrency(row.total) },
    ];

    return (
        <AppLayout
            title="Purchasing"
            description="Suppliers, purchase orders and what is on its way in"
            showFilters={false}
            breadcrumb={{ label: 'Inventory', href: '/inventory' }}
            actions={
                <PermissionGuard permission="catalog.purchase_orders.manage">
                    <Button size="sm" onClick={() => setComposing(true)}>
                        <Plus className="size-3.5" /> New purchase order
                    </Button>
                </PermissionGuard>
            }
        >
            <Head title="Purchasing" />

            {error && <WidgetError message={error} onRetry={load} />}
            {!orders && !error && <SkeletonChart className="h-64" />}

            {orders && (
                <>
                    <VerdictNote verdict={orders.verdict} />

                    <div className="grid gap-3 sm:grid-cols-3">
                        <Card className="p-4">
                            <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Open orders</p>
                            <p className="mt-1.5 text-xl font-semibold tnum">{formatNumber(orders.summary.open ?? 0)}</p>
                        </Card>
                        <Card className="p-4">
                            <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Value inbound</p>
                            <p className="mt-1.5 text-xl font-semibold tnum">{formatCurrency(orders.summary.open_value ?? 0)}</p>
                        </Card>
                        <Card className="p-4">
                            <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Overdue</p>
                            <p className={cn('mt-1.5 text-xl font-semibold tnum', (orders.summary.overdue ?? 0) > 0 && 'text-bad')}>
                                {formatNumber(orders.summary.overdue ?? 0)}
                            </p>
                        </Card>
                    </div>

                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="orders">Purchase orders</TabsTrigger>
                            <TabsTrigger value="suggestions">What to reorder</TabsTrigger>
                            <TabsTrigger value="suppliers">Suppliers</TabsTrigger>
                        </TabsList>
                    </Tabs>

                    {tab === 'orders' && (
                        <ChartCard title="Purchase orders" subtitle="Newest first" bodyClassName="p-0" empty={orders.rows.length === 0}>
                            <DataTable columns={orderColumns} rows={orders.rows} searchable rowKey={(row) => row.id} dense />
                        </ChartCard>
                    )}

                    {tab === 'suggestions' && suggestions && (
                        <div className="space-y-3">
                            <CaveatNote caveat={suggestions.caveat} />
                            {suggestions.groups.length === 0 && (
                                <p className="py-10 text-center text-sm text-muted-foreground">Nothing is below its reorder point right now.</p>
                            )}
                            {suggestions.groups.map((group) => (
                                <ChartCard
                                    key={group.supplier}
                                    title={group.supplier}
                                    subtitle={`${group.skus} SKUs · ${formatNumber(group.units)} units · ${formatCurrency(group.cost)}`}
                                    bodyClassName="p-0"
                                >
                                    <DataTable
                                        columns={[
                                            { key: 'sku_code', header: 'SKU', render: (row) => row.sku_code },
                                            { key: 'name', header: 'Product', render: (row) => row.name },
                                            { key: 'on_hand', header: 'On hand', align: 'right', render: (row) => formatNumber(row.on_hand) },
                                            { key: 'suggested_quantity', header: 'Suggested', align: 'right', render: (row) => formatNumber(row.suggested_quantity) },
                                            { key: 'cost', header: 'Cost', align: 'right', render: (row) => formatCurrency(row.suggested_quantity * row.cost_price) },
                                        ]}
                                        rows={group.rows}
                                        rowKey={(row) => row.sku_id}
                                        dense
                                    />
                                </ChartCard>
                            ))}
                        </div>
                    )}

                    {tab === 'suppliers' && (
                        <SuppliersPanel suppliers={suppliers} canManage={can('catalog.suppliers.manage')} onSaved={load} />
                    )}
                </>
            )}

            <OrderSheet detail={detail} onClose={() => setDetail(null)} onChanged={() => { load(); setDetail(null); }} />
            <ComposeSheet open={composing} suppliers={suppliers ?? []} onOpenChange={setComposing} onSaved={load} />
        </AppLayout>
    );
}

function SuppliersPanel({
    suppliers,
    canManage,
    onSaved,
}: {
    suppliers: SupplierRow[] | null;
    canManage: boolean;
    onSaved: () => void;
}) {
    const [name, setName] = useState('');
    const [contact, setContact] = useState('');
    const [phone, setPhone] = useState('');
    const [leadTime, setLeadTime] = useState('7');
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const response = await apiSend<null>('POST', '/purchasing/suppliers', {
                name,
                contact_name: contact || null,
                phone: phone || null,
                lead_time_days: Number(leadTime),
            });
            toast.success(response.message);
            setName('');
            setContact('');
            setPhone('');
            onSaved();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save the supplier.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <ChartCard title="Suppliers" bodyClassName="p-0" empty={(suppliers?.length ?? 0) === 0}>
                <DataTable
                    columns={[
                        { key: 'name', header: 'Supplier', render: (row) => <span className="text-xs font-medium">{row.name}</span> },
                        { key: 'contact_name', header: 'Contact', render: (row) => row.contact_name ?? '—' },
                        { key: 'phone', header: 'Phone', render: (row) => row.phone ?? '—' },
                        { key: 'city', header: 'City', render: (row) => row.city ?? '—' },
                        { key: 'lead_time_days', header: 'Lead time', align: 'right', render: (row) => `${row.lead_time_days}d` },
                        { key: 'skus', header: 'SKUs', align: 'right', render: (row) => formatNumber(row.skus) },
                        { key: 'purchase_orders', header: 'POs', align: 'right', render: (row) => formatNumber(row.purchase_orders) },
                    ]}
                    rows={suppliers ?? []}
                    searchable
                    rowKey={(row) => row.id}
                    dense
                />
            </ChartCard>

            {canManage && (
                <Card className="space-y-2 p-4">
                    <p className="text-sm font-semibold">Add a supplier</p>
                    <div className="space-y-1.5">
                        <Label htmlFor="sup-name">Name</Label>
                        <Input id="sup-name" value={name} onChange={(event) => setName(event.target.value)} placeholder="Jaipur Textiles" />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="sup-contact">Contact person</Label>
                        <Input id="sup-contact" value={contact} onChange={(event) => setContact(event.target.value)} />
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="sup-phone">Phone</Label>
                            <Input id="sup-phone" value={phone} onChange={(event) => setPhone(event.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="sup-lead">Lead time (days)</Label>
                            <Input id="sup-lead" type="number" value={leadTime} onChange={(event) => setLeadTime(event.target.value)} />
                        </div>
                    </div>
                    <Button size="sm" className="w-full" onClick={save} disabled={saving || name.trim() === ''}>
                        {saving && <Loader2 className="size-4 animate-spin" />} Add supplier
                    </Button>
                </Card>
            )}
        </div>
    );
}

function OrderSheet({
    detail,
    onClose,
    onChanged,
}: {
    detail: OrderDetail | null;
    onClose: () => void;
    onChanged: () => void;
}) {
    const { can } = usePermissions();
    const [received, setReceived] = useState<Record<number, string>>({});
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (detail) {
            // Default to receiving everything outstanding — the common case.
            setReceived(Object.fromEntries(detail.items.map((item) => [item.id, String(item.outstanding)])));
        }
    }, [detail]);

    if (!detail) return null;

    const act = async (action: 'send' | 'receive' | 'cancel') => {
        setBusy(true);
        try {
            const payload = action === 'receive'
                ? { received: Object.fromEntries(Object.entries(received).map(([id, value]) => [id, Number(value) || 0])) }
                : {};
            const response = await apiSend<null>('POST', `/purchasing/orders/${detail.order.id}/${action}`, payload);
            toast.success(response.message);
            onChanged();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'That did not work.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle className="flex items-center gap-2">
                        {detail.order.po_number}
                        <Badge variant={(STATUS_TONE[detail.order.status] ?? 'muted') as 'muted'}>{detail.order.status}</Badge>
                    </SheetTitle>
                    <SheetDescription>
                        {detail.order.supplier ?? 'No supplier'} → {detail.order.location ?? 'default location'}
                        {detail.order.expected_at && ` · expected ${formatDate(detail.order.expected_at)}`}
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-3 overflow-y-auto px-4">
                    <div className="space-y-1.5">
                        {detail.items.map((item) => (
                            <div key={item.id} className="rounded-lg border border-border p-2.5">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-xs font-medium">{item.sku_code}</p>
                                        <p className="truncate text-[11px] text-muted-foreground">{item.name}</p>
                                        <p className="mt-1 text-[11px] text-muted-foreground">
                                            {formatNumber(item.quantity_ordered)} ordered · {formatNumber(item.quantity_received)} received ·
                                            landed {formatCurrency(item.landed_unit_cost)}/unit
                                            {item.landed_unit_cost !== item.current_cost && (
                                                <span className="ml-1 text-warn">(current cost {formatCurrency(item.current_cost)})</span>
                                            )}
                                        </p>
                                    </div>
                                    {detail.order.is_receivable && can('catalog.purchase_orders.manage') && (
                                        <div className="w-24 shrink-0">
                                            <Label htmlFor={`recv-${item.id}`} className="text-[10px]">Receive now</Label>
                                            <Input
                                                id={`recv-${item.id}`}
                                                type="number"
                                                min={0}
                                                max={item.outstanding}
                                                value={received[item.id] ?? ''}
                                                onChange={(event) => setReceived((current) => ({ ...current, [item.id]: event.target.value }))}
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    <Card className="space-y-1 p-3 text-xs">
                        <div className="flex justify-between"><span className="text-muted-foreground">Subtotal</span><span className="tnum">{formatCurrency(detail.order.subtotal)}</span></div>
                        <div className="flex justify-between"><span className="text-muted-foreground">Freight & other</span><span className="tnum">{formatCurrency(detail.order.freight_cost)}</span></div>
                        <div className="flex justify-between"><span className="text-muted-foreground">Tax</span><span className="tnum">{formatCurrency(detail.order.tax_amount)}</span></div>
                        <div className="flex justify-between border-t border-border pt-1 font-semibold"><span>Total</span><span className="tnum">{formatCurrency(detail.order.total)}</span></div>
                    </Card>

                    {can('catalog.purchase_orders.manage') && (
                        <div className="flex flex-wrap gap-2">
                            {detail.order.is_editable && (
                                <Button size="sm" onClick={() => act('send')} disabled={busy}>
                                    <Send className="size-3.5" /> Mark as sent
                                </Button>
                            )}
                            {detail.order.is_receivable && (
                                <Button size="sm" onClick={() => act('receive')} disabled={busy}>
                                    {busy ? <Loader2 className="size-4 animate-spin" /> : <PackageCheck className="size-3.5" />} Receive stock
                                </Button>
                            )}
                            {detail.order.status !== 'received' && detail.order.status !== 'cancelled' && (
                                <Button size="sm" variant="ghost" className="text-bad" onClick={() => act('cancel')} disabled={busy}>
                                    <X className="size-3.5" /> Cancel
                                </Button>
                            )}
                        </div>
                    )}

                    <p className="text-[11px] text-muted-foreground">
                        Receiving books the stock in and re-averages each SKU's cost at the landed price — freight included.
                    </p>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function ComposeSheet({
    open,
    suppliers,
    onOpenChange,
    onSaved,
}: {
    open: boolean;
    suppliers: SupplierRow[];
    onOpenChange: (open: boolean) => void;
    onSaved: () => void;
}) {
    const [supplierId, setSupplierId] = useState('');
    const [expected, setExpected] = useState('');
    const [freight, setFreight] = useState('0');
    const [lines, setLines] = useState<{ sku_code: string; quantity: string; unit_cost: string }[]>([
        { sku_code: '', quantity: '', unit_cost: '' },
    ]);
    const [skus, setSkus] = useState<{ sku_id: number; sku_code: string; name: string; cost_price: number }[]>([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            apiGet<{ rows: { sku_id: number; sku_code: string; name: string; cost_price: number }[] }>('/inventory/levels')
                .then((response) => setSkus(response.data.rows))
                .catch(() => setSkus([]));
        }
    }, [open]);

    const save = async () => {
        const items = lines
            .filter((line) => line.sku_code && line.quantity)
            .map((line) => {
                const sku = skus.find((item) => item.sku_code === line.sku_code);
                return {
                    sku_id: sku?.sku_id,
                    quantity: Number(line.quantity),
                    unit_cost: Number(line.unit_cost || 0),
                };
            })
            .filter((item) => item.sku_id);

        if (items.length === 0) {
            toast.error('Add at least one line.');
            return;
        }

        setSaving(true);
        try {
            const response = await apiSend<null>('POST', '/purchasing/orders', {
                supplier_id: supplierId ? Number(supplierId) : null,
                expected_at: expected || null,
                freight_cost: Number(freight || 0),
                items,
            });
            toast.success(response.message);
            setLines([{ sku_code: '', quantity: '', unit_cost: '' }]);
            onSaved();
            onOpenChange(false);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save the order.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>New purchase order</SheetTitle>
                    <SheetDescription>Freight is spread across the lines by value, so landed cost per unit is right.</SheetDescription>
                </SheetHeader>

                <div className="space-y-3 overflow-y-auto px-4">
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1.5">
                            <Label>Supplier</Label>
                            <Select value={supplierId} onValueChange={setSupplierId}>
                                <SelectTrigger><SelectValue placeholder="Pick one" /></SelectTrigger>
                                <SelectContent>
                                    {suppliers.map((supplier) => (
                                        <SelectItem key={supplier.id} value={String(supplier.id)}>{supplier.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="po-expected">Expected on</Label>
                            <Input id="po-expected" type="date" value={expected} onChange={(event) => setExpected(event.target.value)} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="po-freight">Freight & other costs (₹)</Label>
                        <Input id="po-freight" type="number" value={freight} onChange={(event) => setFreight(event.target.value)} />
                    </div>

                    <div className="space-y-2">
                        <Label>Lines</Label>
                        {lines.map((line, index) => (
                            <div key={index} className="flex items-end gap-1.5">
                                <div className="flex-1 space-y-1">
                                    <Input
                                        list="sku-options"
                                        placeholder="SKU code"
                                        value={line.sku_code}
                                        onChange={(event) => setLines((current) =>
                                            current.map((item, position) => (position === index ? { ...item, sku_code: event.target.value } : item)))}
                                    />
                                </div>
                                <div className="w-20 space-y-1">
                                    <Input
                                        type="number"
                                        placeholder="Qty"
                                        value={line.quantity}
                                        onChange={(event) => setLines((current) =>
                                            current.map((item, position) => (position === index ? { ...item, quantity: event.target.value } : item)))}
                                    />
                                </div>
                                <div className="w-24 space-y-1">
                                    <Input
                                        type="number"
                                        placeholder="₹ / unit"
                                        value={line.unit_cost}
                                        onChange={(event) => setLines((current) =>
                                            current.map((item, position) => (position === index ? { ...item, unit_cost: event.target.value } : item)))}
                                    />
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Remove line"
                                    onClick={() => setLines((current) => current.filter((_, position) => position !== index))}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}

                        <datalist id="sku-options">
                            {skus.map((sku) => (
                                <option key={sku.sku_id} value={sku.sku_code}>{sku.name}</option>
                            ))}
                        </datalist>

                        <Button variant="outline" size="sm" onClick={() => setLines((current) => [...current, { sku_code: '', quantity: '', unit_cost: '' }])}>
                            <Plus className="size-3.5" /> Add line
                        </Button>
                    </div>

                    <Button className="w-full" onClick={save} disabled={saving}>
                        {saving ? <Loader2 className="size-4 animate-spin" /> : <Check className="size-3.5" />} Save as draft
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
