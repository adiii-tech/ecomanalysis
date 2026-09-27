import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { apiGet } from '@/lib/api';
import { formatCurrency, formatDateTime, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface OrderDetail {
    order: {
        id: number;
        order_number: string;
        placed_at: string | null;
        status: string;
        payment_mode: string;
        payment_instrument: string | null;
        payment_gateway: string | null;
        payment_method: string | null;
        channel: string | null;
        customer: string | null;
        state: string | null;
        city: string | null;
        gross_amount: number;
        discount_amount: number;
        net_amount: number;
        cogs_amount: number;
        fees_amount: number;
        logistics_amount: number;
        contribution_margin: number;
    };
    items: {
        sku_code: string | null;
        qty: number;
        unit_price: number;
        discount: number;
        tax: number;
        line_net: number;
        cogs_unit: number;
    }[];
    shipments: {
        awb: string | null;
        courier: string | null;
        status: string;
        attempts: number;
        transit_days: number | null;
    }[];
}

/** Tone for the label the API already sends — {@see OrderStatus::label()}. */
const STATUS_TONE: Record<string, 'default' | 'secondary' | 'good' | 'bad' | 'muted'> = {
    Placed: 'muted',
    Confirmed: 'secondary',
    Shipped: 'default',
    Delivered: 'good',
    Cancelled: 'muted',
    Returned: 'bad',
    RTO: 'bad',
};

const SHIPMENT_TONE: Record<string, 'default' | 'secondary' | 'good' | 'bad' | 'warn' | 'muted'> = {
    pending: 'muted',
    manifested: 'secondary',
    in_transit: 'default',
    out_for_delivery: 'warn',
    delivered: 'good',
    ndr: 'bad',
    rto: 'bad',
    rto_delivered: 'muted',
    cancelled: 'muted',
    lost: 'bad',
};

/**
 * One order, in full — the same detail every "view orders" drilldown across
 * the product already opens, reached here by clicking a row on the Orders
 * page instead of a dashboard number.
 */
export function OrderDetailDrawer({ orderId, onClose }: { orderId: number | null; onClose: () => void }) {
    const [detail, setDetail] = useState<OrderDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (orderId === null) return;

        const controller = new AbortController();
        setLoading(true);
        setFailed(false);
        setDetail(null);

        apiGet<OrderDetail>(`/drilldown/orders/${orderId}`, {}, controller.signal)
            .then((response) => setDetail(response.data))
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) setFailed(true);
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [orderId]);

    if (orderId === null) return null;

    const order = detail?.order;

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent side="right" className="w-full sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle className="flex flex-wrap items-center gap-1.5 text-sm">
                        {order ? order.order_number : <Skeleton className="h-4 w-24" />}
                        {order && <Badge variant={STATUS_TONE[order.status] ?? 'muted'}>{order.status}</Badge>}
                        {order && (
                            <Badge variant={order.payment_mode === 'COD' ? 'warn' : 'muted'}>
                                {order.payment_mode}
                                {order.payment_instrument && ` · ${order.payment_instrument}`}
                            </Badge>
                        )}
                    </SheetTitle>
                    <SheetDescription>
                        {order
                            ? [order.channel, order.customer, order.placed_at ? formatDateTime(order.placed_at) : null].filter(Boolean).join(' · ')
                            : 'Loading this order…'}
                    </SheetDescription>
                    {/* The raw gateway + method behind the instrument label, so a
                        questioned "UPI" or "Cards" call can be checked against
                        what the checkout actually reported. */}
                    {order?.payment_gateway && (
                        <p className="text-[11px] text-muted-foreground">
                            Paid via {order.payment_gateway}
                            {order.payment_method ? ` · ${order.payment_method}` : ''}
                        </p>
                    )}
                    {order?.payment_mode !== 'COD' && order?.payment_instrument === 'Not attributed' && (
                        <p className="text-[11px] text-warn">
                            No successful transaction is recorded against this order — the payment gateway may not have synced yet.
                        </p>
                    )}
                </SheetHeader>

                <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    {loading && (
                        <div className="space-y-2">
                            <Skeleton className="h-24 w-full" />
                            <Skeleton className="h-32 w-full" />
                        </div>
                    )}

                    {failed && <p className="text-xs text-muted-foreground">Could not load this order.</p>}

                    {order && (
                        <>
                            <section>
                                <h3 className="text-xs font-semibold">Where the money went</h3>
                                <div className="mt-2 space-y-1 rounded-lg border border-border p-3 text-xs">
                                    {(
                                        [
                                            ['Gross', order.gross_amount],
                                            ['Discount', -order.discount_amount],
                                            ['Net sales', order.net_amount],
                                            ['COGS', -order.cogs_amount],
                                            ['Fees', -order.fees_amount],
                                            ['Logistics', -order.logistics_amount],
                                        ] as const
                                    ).map(([label, value]) => (
                                        <div key={label} className="flex justify-between">
                                            <span className="text-muted-foreground">{label}</span>
                                            <span className="tnum">{formatCurrency(value)}</span>
                                        </div>
                                    ))}
                                    <div className="flex justify-between border-t border-border pt-1.5 font-semibold">
                                        <span>Contribution margin</span>
                                        <span className={cn('tnum', order.contribution_margin < 0 && 'text-bad')}>{formatCurrency(order.contribution_margin)}</span>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <div className="flex items-baseline justify-between">
                                    <h3 className="text-xs font-semibold">Line items</h3>
                                    <span className="text-[11px] text-muted-foreground">{formatNumber(detail!.items.length)} SKU{detail!.items.length === 1 ? '' : 's'}</span>
                                </div>
                                <div className="mt-2 divide-y divide-border/60 rounded-md border border-border/60">
                                    {detail!.items.map((item, index) => (
                                        <div key={`${item.sku_code}-${index}`} className="flex items-center justify-between gap-2 px-2.5 py-1.5 text-xs">
                                            <div className="min-w-0">
                                                <span className="font-medium">{item.sku_code ?? '—'}</span>
                                                <span className="ml-1.5 text-muted-foreground">× {formatNumber(item.qty)}</span>
                                            </div>
                                            <span className="tnum shrink-0 text-muted-foreground">{formatCurrency(item.line_net)}</span>
                                        </div>
                                    ))}
                                    {detail!.items.length === 0 && <p className="px-2.5 py-3 text-center text-[11px] text-muted-foreground">No line items on this order.</p>}
                                </div>
                            </section>

                            <section>
                                <h3 className="text-xs font-semibold">Shipment</h3>
                                {detail!.shipments.length === 0 ? (
                                    <p className="mt-2 text-[11px] text-muted-foreground">Not shipped yet.</p>
                                ) : (
                                    <div className="mt-2 space-y-1.5">
                                        {detail!.shipments.map((shipment, index) => (
                                            <div key={`${shipment.awb}-${index}`} className="rounded-lg border border-border p-2.5 text-xs">
                                                <div className="flex items-center justify-between">
                                                    <span className="font-medium">{shipment.awb ?? 'No AWB yet'}</span>
                                                    <Badge variant={SHIPMENT_TONE[shipment.status] ?? 'muted'}>{shipment.status.replaceAll('_', ' ')}</Badge>
                                                </div>
                                                <p className="mt-1 text-[11px] text-muted-foreground">
                                                    {shipment.courier ?? 'Courier not set'}
                                                    {shipment.attempts > 0 && ` · ${formatNumber(shipment.attempts)} attempt${shipment.attempts === 1 ? '' : 's'}`}
                                                    {shipment.transit_days !== null && ` · ${formatNumber(shipment.transit_days)}d in transit`}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </section>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
