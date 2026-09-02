import { Head } from '@inertiajs/react';
import { KpiCard } from '@/components/app/kpi-card';
import { VerdictNote } from '@/components/app/verdict-note';
import { CaveatNote } from '@/components/app/caveat-note';
import { EmptyState } from '@/components/app/empty-state';
import { ReportSectionView } from '@/components/reports/report-section';
import type { ReportMeta, ReportSection } from '@/components/reports/types';
import { formatLongDate } from '@/lib/format';
import type { Caveat, Metric, Verdict } from '@/types';

interface SharedProps {
    expired: boolean;
    report: ReportMeta | null;
    payload: { kpis: Metric[]; sections: ReportSection[]; verdict: Verdict | null; caveats: Caveat[] } | null;
    tenant: { name: string; logo_url: string | null } | null;
    filters: { from: string; to: string; channel: string; returns_basis: string } | null;
    shared_at?: string | null;
    expires_at?: string | null;
}

/**
 * The public face of a shared report. No navigation, no filters, no way into
 * the rest of the tenant — just the snapshot the sender froze into the link.
 */
export default function SharedReport({ expired, report, payload, tenant, filters, expires_at }: SharedProps) {
    if (expired || !report || !payload) {
        return (
            <div className="mx-auto flex min-h-dvh max-w-lg items-center justify-center p-6">
                <Head title="Link expired" />
                <EmptyState
                    title="This link is no longer active"
                    description="Shared report links expire, and the person who created it can revoke it at any time. Ask them for a fresh link."
                />
            </div>
        );
    }

    return (
        <div className="min-h-dvh bg-background">
            <Head title={`${report.label} · ${tenant?.name ?? 'Shared report'}`} />

            <header className="border-b border-border bg-card">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <div>
                        <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            {tenant?.name} · shared report
                        </p>
                        <h1 className="mt-0.5 text-lg font-semibold">{report.label}</h1>
                        <p className="text-xs text-muted-foreground">{report.description}</p>
                    </div>
                    <div className="text-right text-xs text-muted-foreground">
                        {filters && (
                            <p>
                                {formatLongDate(filters.from)} — {formatLongDate(filters.to)}
                            </p>
                        )}
                        <p className="mt-0.5">
                            {filters?.channel === 'all' ? 'All channels' : filters?.channel}
                            {filters?.returns_basis === 'return_date' ? ' · return-date basis' : ' · order-date basis'}
                        </p>
                        {expires_at && <p className="mt-0.5">Link expires {formatLongDate(expires_at)}</p>}
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-6xl space-y-4 px-5 py-6">
                <VerdictNote verdict={payload.verdict} />

                {payload.kpis.length > 0 && (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {payload.kpis.map((metric) => (
                            <KpiCard key={metric.key} metric={metric} />
                        ))}
                    </div>
                )}

                {payload.caveats.map((caveat, index) => (
                    <CaveatNote key={index} caveat={caveat} />
                ))}

                {payload.sections.map((section, index) => (
                    <ReportSectionView key={`${section.type}-${index}`} section={section} readOnly />
                ))}

                <p className="pt-4 text-center text-[11px] text-muted-foreground">
                    A read-only snapshot. Numbers reflect the data at the time this page was opened.
                </p>
            </main>
        </div>
    );
}
