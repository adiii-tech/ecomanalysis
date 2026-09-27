import { Link } from '@inertiajs/react';
import { ArrowRight, Loader2, Send, TriangleAlert } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { apiGet, apiSend } from '@/lib/api';
import { formatCurrency, formatNumber } from '@/lib/format';
import { groupPoLinesBySupplier, type PoLine } from '@/lib/restock';
import { cn } from '@/lib/utils';

interface SupplierOption {
    id: number;
    name: string;
    lead_time_days: number;
}

interface IssuedOrder {
    id: number;
    po_number: string;
    supplier_id: number;
    total: number;
    items: number;
}

const todayPlus = (days: number): string => new Date(Date.now() + days * 86400000).toISOString().slice(0, 10);

/**
 * Turns the buyer's selection on Restock into real purchase orders — one per
 * supplier, sent immediately. A SKU with no supplier assigned gets its own
 * group and needs one picked here before it can go out.
 */
export function IssuePoDialog({
    open,
    lines,
    onOpenChange,
    onIssued,
}: {
    open: boolean;
    lines: PoLine[];
    onOpenChange: (open: boolean) => void;
    onIssued: () => void;
}) {
    const [suppliers, setSuppliers] = useState<SupplierOption[] | null>(null);
    const [chosen, setChosen] = useState<Record<number, string>>({});
    const [expected, setExpected] = useState<Record<number, string>>({});
    const [issuing, setIssuing] = useState(false);
    const [issued, setIssued] = useState<IssuedOrder[] | null>(null);

    const groups = useMemo(() => groupPoLinesBySupplier(lines), [lines]);
    const supplierById = useMemo(() => new Map((suppliers ?? []).map((supplier) => [supplier.id, supplier])), [suppliers]);

    useEffect(() => {
        if (!open) return;

        setIssued(null);
        apiGet<{ rows: SupplierOption[] }>('/purchasing/suppliers')
            .then((response) => setSuppliers(response.data.rows))
            .catch(() => setSuppliers([]));
    }, [open]);

    // Every group starts pre-filled with the supplier its SKUs already carry,
    // and with an expected date from that supplier's own lead time — a buyer
    // only has to type something for the groups that actually need a decision.
    useEffect(() => {
        if (!open || suppliers === null) return;

        setChosen((current) => {
            const next = { ...current };
            groups.forEach((group, index) => {
                if (next[index] !== undefined) return;
                next[index] = group.supplierId !== null && supplierById.has(group.supplierId) ? String(group.supplierId) : '';
            });
            return next;
        });

        setExpected((current) => {
            const next = { ...current };
            groups.forEach((group, index) => {
                if (next[index] !== undefined) return;
                const supplier = group.supplierId !== null ? supplierById.get(group.supplierId) : undefined;
                next[index] = supplier ? todayPlus(supplier.lead_time_days) : '';
            });
            return next;
        });
    }, [open, suppliers, groups, supplierById]);

    if (!open) return null;

    const readyToIssue = groups.length > 0 && groups.every((_, index) => chosen[index] && chosen[index] !== '');

    const issue = async () => {
        setIssuing(true);
        try {
            const response = await apiSend<{ orders: IssuedOrder[] }>('POST', '/purchasing/orders/issue', {
                groups: groups.map((group, index) => ({
                    supplier_id: Number(chosen[index]),
                    expected_at: expected[index] || null,
                    items: group.lines.map((line) => ({
                        sku_id: line.row.sku_id,
                        quantity: line.qty,
                        unit_cost: line.row.unit_cost / 100,
                    })),
                })),
            });
            toast.success(response.message);
            setIssued(response.data.orders);
            onIssued();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not issue the purchase order.');
        } finally {
            setIssuing(false);
        }
    };

    return (
        <Sheet open onOpenChange={(next) => !next && onOpenChange(false)}>
            <SheetContent side="right" className="w-full sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>Issue purchase order{groups.length > 1 ? 's' : ''}</SheetTitle>
                    <SheetDescription>
                        {issued
                            ? 'Sent to the supplier — Restock will show these as incoming.'
                            : groups.length > 1
                              ? `${groups.length} suppliers in this selection — one purchase order goes out per supplier.`
                              : 'Sent immediately; there is no draft step here.'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                    {issued ? (
                        <div className="space-y-2">
                            {issued.map((order) => (
                                <div key={order.id} className="flex items-center justify-between rounded-lg border border-border bg-good-soft/40 px-3 py-2.5">
                                    <div>
                                        <p className="text-sm font-semibold">{order.po_number}</p>
                                        <p className="text-[11px] text-muted-foreground">
                                            {supplierById.get(order.supplier_id)?.name ?? 'Supplier'} · {order.items} line{order.items === 1 ? '' : 's'}
                                        </p>
                                    </div>
                                    <p className="tnum text-sm font-semibold">{formatCurrency(order.total)}</p>
                                </div>
                            ))}
                            <Link
                                href="/inventory/purchasing"
                                className="flex items-center justify-center gap-1.5 rounded-lg border border-border px-3 py-2 text-xs font-medium hover:bg-accent"
                            >
                                Track these in Purchasing
                                <ArrowRight className="size-3.5" />
                            </Link>
                        </div>
                    ) : (
                        <>
                            {groups.map((group, index) => {
                                const matchedName = group.supplierId !== null ? supplierById.get(group.supplierId)?.name : undefined;
                                const needsPick = !chosen[index];

                                return (
                                    <div key={group.supplierId ?? 'unassigned'} className={cn('rounded-lg border p-3', needsPick ? 'border-warn' : 'border-border')}>
                                        <div className="flex flex-wrap items-end gap-2">
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <Label htmlFor={`po-supplier-${index}`}>
                                                    {matchedName ? 'Supplier' : 'Pick a supplier — these SKUs have none assigned'}
                                                </Label>
                                                <Select value={chosen[index] ?? ''} onValueChange={(value) => setChosen((current) => ({ ...current, [index]: value }))}>
                                                    <SelectTrigger id={`po-supplier-${index}`}><SelectValue placeholder="Choose a supplier" /></SelectTrigger>
                                                    <SelectContent>
                                                        {(suppliers ?? []).map((supplier) => (
                                                            <SelectItem key={supplier.id} value={String(supplier.id)}>{supplier.name}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="w-36 space-y-1">
                                                <Label htmlFor={`po-expected-${index}`}>Expected on</Label>
                                                <Input
                                                    id={`po-expected-${index}`}
                                                    type="date"
                                                    className="h-8"
                                                    value={expected[index] ?? ''}
                                                    onChange={(event) => setExpected((current) => ({ ...current, [index]: event.target.value }))}
                                                />
                                            </div>
                                        </div>

                                        {needsPick && (
                                            <p className="mt-2 flex items-center gap-1.5 text-[11px] text-warn">
                                                <TriangleAlert className="size-3.5 shrink-0" />
                                                Every SKU in this group came in with no supplier set on its Catalog entry.
                                            </p>
                                        )}

                                        <div className="mt-2.5 divide-y divide-border/60 rounded-md border border-border/60">
                                            {group.lines.map((line) => (
                                                <div key={line.row.sku_id} className="flex items-center justify-between gap-2 px-2.5 py-1.5 text-xs">
                                                    <div className="min-w-0">
                                                        <span className="font-medium">{line.row.sku_code}</span>
                                                        <span className="ml-1.5 text-muted-foreground">{formatNumber(line.qty)} units</span>
                                                    </div>
                                                    <span className="tnum shrink-0 text-muted-foreground">{formatCurrency(line.value)}</span>
                                                </div>
                                            ))}
                                        </div>
                                        <p className="mt-1.5 text-right text-[11px] font-medium text-muted-foreground">
                                            {formatNumber(group.lines.length)} line{group.lines.length === 1 ? '' : 's'} · {formatCurrency(group.value)}
                                        </p>
                                    </div>
                                );
                            })}

                            <Button className="w-full" onClick={issue} disabled={issuing || !readyToIssue}>
                                {issuing ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-3.5" />}
                                Issue {groups.length > 1 ? `${groups.length} purchase orders` : 'purchase order'}
                            </Button>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
