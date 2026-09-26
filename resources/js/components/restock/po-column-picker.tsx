import { Columns3 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { PO_COLUMNS, vendorSafeColumns } from '@/lib/restock';
import { cn } from '@/lib/utils';

/**
 * Chooses what the purchase order carries.
 *
 * The internal columns — cost, stock on hand, velocity — are off by default and
 * marked, because the PO is a file that gets sent to a supplier and those are
 * our numbers, not theirs.
 */
export function PoColumnPicker({ selected, onChange }: { selected: string[]; onChange: (keys: string[]) => void }) {
    // Selection is kept in registry order, so the file's columns never depend on
    // the order the buyer happened to tick them in.
    const toggle = (key: string) => {
        const next = selected.includes(key) ? selected.filter((k) => k !== key) : [...selected, key];
        onChange(PO_COLUMNS.filter((column) => next.includes(column.key)).map((column) => column.key));
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button size="xs" variant="outline">
                    <Columns3 className="size-3" />
                    PO columns ({selected.length})
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-64 p-2">
                <p className="px-1 pb-2 text-xs font-semibold">
                    Columns in the PO
                    <span className="mt-0.5 block font-normal text-[10px] text-muted-foreground">Unchecked stays out of the file you send the supplier.</span>
                </p>

                <div className="flex gap-1.5 px-1 pb-2">
                    <Button size="xs" variant="outline" className="flex-1" onClick={() => onChange(vendorSafeColumns())}>
                        Supplier-safe
                    </Button>
                    <Button size="xs" variant="outline" className="flex-1" onClick={() => onChange(PO_COLUMNS.map((column) => column.key))}>
                        Full detail
                    </Button>
                </div>

                <div className="max-h-[50vh] overflow-y-auto">
                    {PO_COLUMNS.map((column) => (
                        <label key={column.key} className="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-xs hover:bg-accent">
                            <input
                                type="checkbox"
                                checked={selected.includes(column.key)}
                                onChange={() => toggle(column.key)}
                                className="size-3.5 accent-[var(--good)]"
                            />
                            <span className={cn(column.internal && 'text-warn')}>{column.label}</span>
                            {column.internal && <span className="ml-auto rounded bg-warn-soft px-1.5 py-px text-[9px] font-medium text-warn">internal</span>}
                        </label>
                    ))}
                </div>

                <p className="px-1 pt-2 text-[10px] leading-snug text-muted-foreground">
                    Amber columns are your own numbers — cost, stock, sales pace. Off by default so they never reach a supplier by accident.
                </p>
            </PopoverContent>
        </Popover>
    );
}
