export type MetricFormat = 'currency' | 'number' | 'percent' | 'ratio' | 'days' | 'seconds';

export interface SparklinePoint {
    date: string;
    value: number;
}

export interface Metric {
    key: string;
    label: string;
    value: number;
    prev_value: number | null;
    delta_pct: number | null;
    direction: 'up' | 'down' | 'flat';
    is_good: boolean | null;
    format: MetricFormat;
    higher_is_better: boolean;
    tooltip: string | null;
    sparkline: SparklinePoint[];
    caveat: string | null;
    drilldown: string | null;
    badge: string | null;
}

export interface Verdict {
    status: 'scale' | 'hold' | 'cut' | 'good' | 'watch' | 'bad' | 'neutral';
    headline: string;
    detail: string | null;
    action: string | null;
    impact_paise: number | null;
}

export interface Caveat {
    message: string;
    level: 'info' | 'warning';
    connector: string | null;
}

export interface PeriodMeta {
    from: string;
    to: string;
    days: number;
    preset: string | null;
}

export interface ApiMeta {
    cached_at: string;
    period: PeriodMeta | null;
    prev_period: PeriodMeta | null;
    channel: string | null;
    returns_basis: string | null;
    verdict: Verdict | null;
    caveat: Caveat | null;
    [key: string]: unknown;
}

export interface ApiEnvelope<T> {
    success: boolean;
    statusCode: number;
    message: string;
    data: T;
    meta: ApiMeta;
}

export interface Channel {
    id: number;
    name: string;
    code: string;
    type: 'd2c' | 'marketplace';
    color: string | null;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: string | null;
    accent_color: string;
    theme: string;
    is_demo: boolean;
    permissions_version: number;
    ai_credits_used: number;
    ai_credit_limit: number;
    ai_credits_remaining: number;
    mfa_enabled: boolean;
}

export interface TenantInfo {
    id: number;
    name: string;
    slug: string;
    currency: string;
    timezone: string;
    plan: string;
    is_demo: boolean;
    onboarding_state: Record<string, unknown> | null;
}

export interface ConnectorHealth {
    id: string;
    label: string;
    status: string;
    dot: 'green' | 'amber' | 'red' | 'grey' | 'blue';
    account_label: string | null;
    last_synced_at: string | null;
    last_synced_human: string | null;
    last_error: string | null;
    is_stale: boolean;
    last_run: { entity: string; status: string; records: number; duration_ms: number } | null;
}

export interface SyncHealth {
    status: 'green' | 'amber' | 'red' | 'grey';
    label: string;
    connectors: ConnectorHealth[];
    stale_count: number;
}

export interface SharedProps {
    auth: { user: AuthUser | null; permissions: string[] };
    tenant: TenantInfo | null;
    channels: Channel[];
    syncHealth: SyncHealth | null;
    flash: { success: string | null; error: string | null };
    ziggy: { location: string; url: string; port: number | null; defaults: Record<string, unknown>; routes: Record<string, unknown> };
    [key: string]: unknown;
}
