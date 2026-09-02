import { useCallback } from 'react';
import { toast } from 'sonner';
import { useFilters } from '@/hooks/use-filters';

export type ExportFormat = 'csv' | 'xlsx' | 'pdf';

/**
 * Downloads an export with the current global filters attached, so the file
 * always matches what is on screen.
 *
 * The browser is navigated to the URL rather than fetched, because the response
 * is a file attachment — fetch would buffer a 20,000-row export in memory for
 * no reason.
 */
export function useExport() {
    const { queryParams } = useFilters();

    return useCallback(
        (dataset: string, format: ExportFormat) => {
            const query = new URLSearchParams(queryParams).toString();
            const url = `/api/export/${dataset}/${format}${query ? `?${query}` : ''}`;

            toast.info(`Preparing ${format.toUpperCase()}…`, { duration: 2000 });

            const frame = document.createElement('iframe');
            frame.style.display = 'none';
            frame.src = url;
            document.body.appendChild(frame);

            // The download is detached from the frame once it starts; clean up.
            window.setTimeout(() => frame.remove(), 60_000);
        },
        [queryParams],
    );
}
