import { ArrowDown, ArrowUp, ChevronsUpDown, Search } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { SkeletonTable } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { EmptyState } from '@/components/app/empty-state';
import { cn } from '@/lib/utils';

export interface Column<T> {
    key: string;
    header: string;
    tooltip?: string;
    align?: 'left' | 'right' | 'center';
    width?: string;
    sortable?: boolean;
    /** Value used for client-side sorting and search. */
    value?: (row: T) => string | number | null;
    render: (row: T, index: number) => ReactNode;
    className?: string;
}

export function DataTable<T>({
    columns,
    rows,
    loading = false,
    searchable = false,
    searchPlaceholder = 'Search…',
    emptyTitle = 'Nothing to show',
    emptyDescription,
    stickyHeader = true,
    rowKey,
    onRowClick,
    initialSort,
    maxHeight,
    dense = false,
    footer,
}: {
    columns: Column<T>[];
    rows: T[] | null;
    loading?: boolean;
    searchable?: boolean;
    searchPlaceholder?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    stickyHeader?: boolean;
    rowKey: (row: T, index: number) => string | number;
    onRowClick?: (row: T) => void;
    initialSort?: { key: string; direction: 'asc' | 'desc' };
    maxHeight?: string;
    dense?: boolean;
    footer?: ReactNode;
}) {
    const [sort, setSort] = useState(initialSort ?? null);
    const [query, setQuery] = useState('');

    const processed = useMemo(() => {
        let result = rows ?? [];

        if (query.trim() !== '') {
            const needle = query.trim().toLowerCase();
            result = result.filter((row) =>
                columns.some((column) => {
                    const value = column.value?.(row);
                    return value !== null && value !== undefined && String(value).toLowerCase().includes(needle);
                }),
            );
        }

        if (sort) {
            const column = columns.find((c) => c.key === sort.key);
            if (column?.value) {
                result = [...result].sort((a, b) => {
                    const left = column.value!(a);
                    const right = column.value!(b);
                    if (left === right) return 0;
                    if (left === null || left === undefined) return 1;
                    if (right === null || right === undefined) return -1;
                    const compare = typeof left === 'number' && typeof right === 'number' ? left - right : String(left).localeCompare(String(right));
                    return sort.direction === 'asc' ? compare : -compare;
                });
            }
        }

        return result;
    }, [rows, columns, sort, query]);

    function toggleSort(key: string) {
        setSort((current) =>
            current?.key === key
                ? { key, direction: current.direction === 'asc' ? 'desc' : 'asc' }
                : { key, direction: 'desc' },
        );
    }

    if (loading) {
        return <SkeletonTable rows={6} cols={Math.min(columns.length, 5)} />;
    }

    return (
        <div className="space-y-2">
            {searchable && (
                <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={searchPlaceholder}
                        className="h-8 pl-8 text-xs"
                    />
                </div>
            )}

            {processed.length === 0 ? (
                <EmptyState compact title={emptyTitle} description={emptyDescription} />
            ) : (
                <div className={cn('overflow-auto scrollbar-thin', maxHeight)} style={maxHeight ? undefined : undefined}>
                    <table className="w-full border-collapse text-sm">
                        <thead className={cn(stickyHeader && 'sticky top-0 z-10 bg-card')}>
                            <tr className="border-b border-border">
                                {columns.map((column) => (
                                    <th
                                        key={column.key}
                                        style={column.width ? { width: column.width } : undefined}
                                        className={cn(
                                            'whitespace-nowrap px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground',
                                            column.align === 'right' && 'text-right',
                                            column.align === 'center' && 'text-center',
                                            !column.align && 'text-left',
                                        )}
                                    >
                                        <span className={cn('inline-flex items-center gap-1', column.align === 'right' && 'flex-row-reverse')}>
                                            {column.tooltip ? (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <span className="cursor-help border-b border-dotted border-muted-foreground/40">{column.header}</span>
                                                    </TooltipTrigger>
                                                    <TooltipContent>{column.tooltip}</TooltipContent>
                                                </Tooltip>
                                            ) : (
                                                column.header
                                            )}
                                            {column.sortable && (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleSort(column.key)}
                                                    className="text-muted-foreground/50 transition hover:text-foreground"
                                                    aria-label={`Sort by ${column.header}`}
                                                >
                                                    {sort?.key === column.key ? (
                                                        sort.direction === 'asc' ? (
                                                            <ArrowUp className="size-3" />
                                                        ) : (
                                                            <ArrowDown className="size-3" />
                                                        )
                                                    ) : (
                                                        <ChevronsUpDown className="size-3" />
                                                    )}
                                                </button>
                                            )}
                                        </span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {processed.map((row, index) => (
                                <tr
                                    key={rowKey(row, index)}
                                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                                    className={cn(
                                        'border-b border-border/60 transition-colors last:border-0',
                                        onRowClick && 'cursor-pointer hover:bg-accent/50',
                                    )}
                                >
                                    {columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className={cn(
                                                'px-2.5 align-middle',
                                                dense ? 'py-1.5' : 'py-2.5',
                                                column.align === 'right' && 'text-right tnum',
                                                column.align === 'center' && 'text-center',
                                                column.className,
                                            )}
                                        >
                                            {column.render(row, index)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                        {footer && <tfoot className="border-t-2 border-border bg-muted/40">{footer}</tfoot>}
                    </table>
                </div>
            )}
        </div>
    );
}
