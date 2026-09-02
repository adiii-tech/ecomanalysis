import { Download } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useExport, type ExportFormat } from '@/hooks/use-export';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { DataTable, type Column } from '@/components/app/data-table';
import { CaveatNote } from '@/components/app/caveat-note';
import { VerdictNote } from '@/components/app/verdict-note';
import type { Caveat, Verdict } from '@/types';

/**
 * Every widget drills down to row-level truth. The drawer is that truth:
 * searchable, sortable, exportable.
 */
export function DrilldownDrawer<T>({
    open,
    onOpenChange,
    title,
    description,
    columns,
    rows,
    loading,
    rowKey,
    verdict,
    caveat,
    exportDataset,
    onExport,
    header,
    emptyTitle = 'No rows for this selection',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    columns: Column<T>[];
    rows: T[] | null;
    loading?: boolean;
    rowKey: (row: T, index: number) => string | number;
    verdict?: Verdict | null;
    caveat?: Caveat | string | null;
    /** Dataset key; the drawer builds the download itself. */
    exportDataset?: string;
    onExport?: (format: ExportFormat) => void;
    header?: ReactNode;
    emptyTitle?: string;
}) {
    const download = useExport();
    const exportHandler = onExport ?? (exportDataset ? (format: ExportFormat) => download(exportDataset, format) : undefined);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full sm:max-w-4xl">
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    {description && <SheetDescription>{description}</SheetDescription>}
                </SheetHeader>

                <div className="flex-1 space-y-3 overflow-auto p-5 scrollbar-thin">
                    {header}
                    {verdict && <VerdictNote verdict={verdict} />}

                    <DataTable
                        columns={columns}
                        rows={rows}
                        loading={loading}
                        searchable
                        rowKey={rowKey}
                        emptyTitle={emptyTitle}
                    />

                    {caveat && <CaveatNote caveat={caveat} />}
                </div>

                {exportHandler && (
                    <div className="flex items-center justify-end gap-2 border-t border-border px-5 py-3">
                        {(['csv', 'xlsx', 'pdf'] as const).map((format) => (
                            <Button key={format} variant="outline" size="sm" onClick={() => exportHandler(format)} className="gap-1.5">
                                <Download className="size-3.5" />
                                {format.toUpperCase()}
                            </Button>
                        ))}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
