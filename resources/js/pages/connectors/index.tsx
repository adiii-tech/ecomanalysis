import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CheckCircle2, ExternalLink, Loader2, PlugZap, RefreshCw, Settings2, TriangleAlert } from 'lucide-react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable } from '@/components/app/data-table';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useWidget } from '@/hooks/use-widget';
import { usePermissions } from '@/hooks/use-permissions';
import { apiGet, apiSend } from '@/lib/api';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface CredentialField {
    label: string;
    type: string;
    required: boolean;
    help?: string;
}

interface ConnectorRow {
    id: string;
    label: string;
    summary: string;
    auth_type: string;
    auth_label: string;
    entities: string[];
    fields: Record<string, CredentialField>;
    is_stub: boolean;
    status: string;
    status_label: string;
    is_connected: boolean;
    account_label: string | null;
    last_synced_human: string | null;
    last_error: string | null;
    is_oauth: boolean;
    oauth_configured: boolean;
    pre_auth_fields: Record<string, CredentialField>;
    pending_selections: Record<string, string>;
    selected: Record<string, string>;
}

interface ResourceOption {
    id: string;
    label: string;
    meta?: string;
}

const STATUS_DOT: Record<string, string> = {
    connected: 'bg-good',
    syncing: 'bg-primary animate-pulse',
    error: 'bg-bad',
    needs_setup: 'bg-warn',
    disconnected: 'bg-muted-foreground/40',
};

export default function Connectors() {
    const { flash } = usePage<SharedProps>().props;
    const [tab, setTab] = useState<'live' | 'phase_two'>('live');
    const [authorizing, setAuthorizing] = useState<ConnectorRow | null>(null);
    const [configuring, setConfiguring] = useState<ConnectorRow | null>(null);
    const [tokenForm, setTokenForm] = useState<ConnectorRow | null>(null);
    const [values, setValues] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState<string | null>(null);
    const { can } = usePermissions();

    const connectors = useWidget<{ live: ConnectorRow[]; phase_two: ConnectorRow[] }>('connectors');
    const runs = useWidget<{ rows: { id: number; connector_id: string; entity: string; status: string; trigger: string; records_upserted: number; duration_ms: number; error: string | null }[] }>('connectors/sync-runs');

    // The OAuth callback redirects back here; surface the outcome and, if the
    // connector still needs an account chosen, open that step straight away.
    useEffect(() => {
        if (flash.error) toast.error(flash.error);
        if (flash.success) toast.success(flash.success);

        const connected = new URLSearchParams(window.location.search).get('connected');
        if (!connected || !connectors.data) return;

        const row = [...connectors.data.live, ...connectors.data.phase_two].find((c) => c.id === connected);
        if (row && Object.keys(row.pending_selections).length > 0) setConfiguring(row);

        window.history.replaceState({}, '', window.location.pathname);
    }, [flash.error, flash.success, connectors.data]);

    function startOAuth(connector: ConnectorRow, preAuth: Record<string, string> = {}) {
        const query = new URLSearchParams(preAuth).toString();
        window.location.href = `/connectors/oauth/${connector.id}/redirect${query ? `?${query}` : ''}`;
    }

    async function act(connector: string, action: 'test' | 'sync' | 'disconnect') {
        setBusy(`${connector}:${action}`);
        try {
            const response = await apiSend<{ message?: string }>('POST', `connectors/${connector}/${action}`);
            toast.success(response.data?.message ?? response.message);
            connectors.reload();
            runs.reload();
            if (action === 'disconnect') router.reload();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Something went wrong.');
        } finally {
            setBusy(null);
        }
    }

    async function submitTokens() {
        if (!tokenForm) return;
        setBusy(`${tokenForm.id}:connect`);
        try {
            await apiSend('POST', `connectors/${tokenForm.id}/connect`, values);
            toast.success(`${tokenForm.label} connected.`);
            setTokenForm(null);
            setValues({});
            connectors.reload();
            router.reload();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not connect.');
        } finally {
            setBusy(null);
        }
    }

    const rows = tab === 'live' ? (connectors.data?.live ?? []) : (connectors.data?.phase_two ?? []);

    return (
        <AppLayout title="Connectors" description="Where your numbers come from" showFilters={false}>
            <Head title="Connectors" />

            <Tabs value={tab} onValueChange={(value) => setTab(value as typeof tab)}>
                <TabsList>
                    <TabsTrigger value="live">Available now ({connectors.data?.live.length ?? 0})</TabsTrigger>
                    <TabsTrigger value="phase_two">Phase 2 ({connectors.data?.phase_two.length ?? 0})</TabsTrigger>
                </TabsList>
            </Tabs>

            {tab === 'phase_two' && (
                <p className="rounded-lg bg-warn-soft/50 px-3 py-2 text-xs text-warn">
                    These implement the same connector interface but their drivers are not written yet. Connecting one would
                    not pull any data, so it is disabled.
                </p>
            )}

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {connectors.loading
                    ? Array.from({ length: 6 }).map((_, index) => <Card key={index} className="h-48 animate-pulse bg-muted/40" />)
                    : rows.map((connector) => {
                          const needsSetup = connector.status === 'needs_setup';
                          // Syncing, or a failed last sync, is still a live connection — only the server knows the credentials hold.
                          const isConnected = connector.is_connected;

                          return (
                              <Card key={connector.id} className="flex flex-col p-4">
                                  <div className="flex items-start justify-between gap-2">
                                      <div className="min-w-0">
                                          <p className="flex items-center gap-1.5 text-sm font-semibold">
                                              <span className={cn('size-2 shrink-0 rounded-full', STATUS_DOT[connector.status])} />
                                              <span className="truncate">{connector.label}</span>
                                          </p>
                                          <p className="mt-0.5 text-[11px] uppercase tracking-wide text-muted-foreground">
                                              {connector.auth_label}
                                          </p>
                                      </div>
                                      {connector.is_stub ? (
                                          <Badge variant="warn">phase 2</Badge>
                                      ) : isConnected ? (
                                          connector.status === 'error' ? (
                                              <Badge variant="bad">sync error</Badge>
                                          ) : (
                                              <Badge variant="good">{connector.status === 'syncing' ? 'syncing' : 'connected'}</Badge>
                                          )
                                      ) : needsSetup ? (
                                          <Badge variant="warn">needs setup</Badge>
                                      ) : connector.status === 'error' ? (
                                          <Badge variant="bad">error</Badge>
                                      ) : null}
                                  </div>

                                  <p className="mt-2 flex-1 text-xs leading-snug text-muted-foreground">{connector.summary}</p>

                                  <div className="mt-2 flex flex-wrap gap-1">
                                      {connector.entities.slice(0, 4).map((entity) => (
                                          <span key={entity} className="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                              {entity.replace(/_/g, ' ')}
                                          </span>
                                      ))}
                                      {connector.entities.length > 4 && (
                                          <span className="px-1 py-0.5 text-[10px] text-muted-foreground">
                                              +{connector.entities.length - 4}
                                          </span>
                                      )}
                                  </div>

                                  {needsSetup && (
                                      <p className="mt-2 text-[11px] text-warn">
                                          Authorised, but still needs: {Object.values(connector.pending_selections).join(', ')}.
                                      </p>
                                  )}

                                  {connector.last_error && (
                                      <p className="mt-2 flex items-start gap-1 text-[11px] text-bad">
                                          <TriangleAlert className="mt-px size-3 shrink-0" />
                                          <span className="line-clamp-2">{connector.last_error}</span>
                                      </p>
                                  )}

                                  {isConnected && (
                                      <p className="mt-2 flex items-center gap-1 text-[11px] text-muted-foreground">
                                          <CheckCircle2 className="size-3 text-good" />
                                          {connector.account_label} · synced {connector.last_synced_human ?? 'never'}
                                      </p>
                                  )}

                                  {connector.is_oauth && !connector.oauth_configured && !connector.is_stub && (
                                      <p className="mt-2 text-[11px] text-muted-foreground">
                                          Not configured on this server — an admin needs to add its OAuth client id and secret.
                                      </p>
                                  )}

                                  <div className="mt-3 flex flex-wrap gap-1.5">
                                      {isConnected || needsSetup ? (
                                          <>
                                              {needsSetup && (
                                                  <Button size="xs" onClick={() => setConfiguring(connector)} disabled={!can('connectors.credentials.manage')}>
                                                      <Settings2 />
                                                      Finish setup
                                                  </Button>
                                              )}
                                              {isConnected && (
                                                  <>
                                                      <Button
                                                          size="xs"
                                                          variant="outline"
                                                          disabled={!can('connectors.sync_health.manage') || busy !== null}
                                                          onClick={() => act(connector.id, 'sync')}
                                                      >
                                                          {busy === `${connector.id}:sync` ? <Loader2 className="animate-spin" /> : <RefreshCw />}
                                                          Sync
                                                      </Button>
                                                      <Button size="xs" variant="ghost" disabled={busy !== null} onClick={() => act(connector.id, 'test')}>
                                                          Test
                                                      </Button>
                                                      {Object.keys(connector.pending_selections).length === 0 &&
                                                          Object.keys(connector.selected).length > 0 && (
                                                              <Button size="xs" variant="ghost" onClick={() => setConfiguring(connector)}>
                                                                  <Settings2 />
                                                                  Accounts
                                                              </Button>
                                                          )}
                                                  </>
                                              )}
                                              <Button
                                                  size="xs"
                                                  variant="ghost"
                                                  className="text-bad"
                                                  disabled={!can('connectors.credentials.manage') || busy !== null}
                                                  onClick={() => act(connector.id, 'disconnect')}
                                              >
                                                  Disconnect
                                              </Button>
                                          </>
                                      ) : connector.is_oauth ? (
                                          <Button
                                              size="xs"
                                              disabled={connector.is_stub || !connector.oauth_configured || !can('connectors.credentials.manage')}
                                              onClick={() =>
                                                  Object.keys(connector.pre_auth_fields).length > 0
                                                      ? (setAuthorizing(connector), setValues({}))
                                                      : startOAuth(connector)
                                              }
                                          >
                                              <ExternalLink />
                                              Connect with {connector.label.split(' ')[0]}
                                          </Button>
                                      ) : (
                                          <Button
                                              size="xs"
                                              disabled={connector.is_stub || !can('connectors.credentials.manage')}
                                              onClick={() => {
                                                  setTokenForm(connector);
                                                  setValues({});
                                              }}
                                          >
                                              <PlugZap />
                                              {connector.is_stub ? 'Not available yet' : 'Connect'}
                                          </Button>
                                      )}
                                  </div>
                              </Card>
                          );
                      })}
            </div>

            <PermissionGuard permission="connectors.sync_health.view">
                <ChartCard
                    title="Sync history"
                    subtitle="Every run, successful or not"
                    widgetKey="connectors.sync_health"
                    loading={runs.loading}
                    error={runs.error}
                    onRetry={runs.reload}
                    empty={(runs.data?.rows.length ?? 0) === 0}
                    emptyState={<p className="py-8 text-center text-xs text-muted-foreground">Nothing has synced yet — connect a source above.</p>}
                >
                    <DataTable
                        rows={runs.data?.rows ?? []}
                        rowKey={(row) => row.id}
                        columns={[
                            { key: 'connector', header: 'Connector', value: (r) => r.connector_id, render: (r) => <span className="font-medium">{r.connector_id}</span> },
                            { key: 'entity', header: 'Entity', value: (r) => r.entity, render: (r) => r.entity.replace(/_/g, ' ') },
                            { key: 'trigger', header: 'Trigger', value: (r) => r.trigger, render: (r) => r.trigger },
                            { key: 'status', header: 'Status', value: (r) => r.status, render: (r) => (
                                <Badge variant={r.status === 'success' ? 'good' : r.status === 'failed' ? 'bad' : 'muted'}>{r.status}</Badge>
                            ) },
                            { key: 'records', header: 'Records', align: 'right', sortable: true, value: (r) => r.records_upserted, render: (r) => formatNumber(r.records_upserted) },
                            { key: 'duration', header: 'Duration', align: 'right', sortable: true, value: (r) => r.duration_ms, render: (r) => `${formatNumber(r.duration_ms)}ms` },
                            { key: 'error', header: 'Error', value: (r) => r.error, render: (r) => (r.error ? <span className="line-clamp-1 text-bad">{r.error}</span> : <span className="text-muted-foreground">—</span>) },
                        ]}
                    />
                </ChartCard>
            </PermissionGuard>

            {/* Pre-auth: Shopify authorises on the merchant's own domain, so we need it first. */}
            <Sheet open={authorizing !== null} onOpenChange={(open) => !open && setAuthorizing(null)}>
                <SheetContent side="right" className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>Connect {authorizing?.label}</SheetTitle>
                        <SheetDescription>You&rsquo;ll be sent to {authorizing?.label} to approve access.</SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-4 overflow-auto p-5">
                        {Object.entries(authorizing?.pre_auth_fields ?? {}).map(([name, field]) => (
                            <div key={name} className="space-y-1.5">
                                <Label htmlFor={`pre-${name}`}>
                                    {field.label}
                                    {field.required && <span className="ml-0.5 text-bad">*</span>}
                                </Label>
                                <Input
                                    id={`pre-${name}`}
                                    value={values[name] ?? ''}
                                    onChange={(event) => setValues((current) => ({ ...current, [name]: event.target.value }))}
                                    placeholder={field.help}
                                />
                                {field.help && <p className="text-[11px] text-muted-foreground">{field.help}</p>}
                            </div>
                        ))}
                        <p className="rounded-lg bg-muted px-3 py-2 text-[11px] text-muted-foreground">
                            We never see your password. {authorizing?.label} issues a token scoped to the data listed on this card.
                        </p>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-border px-5 py-3">
                        <Button variant="outline" size="sm" onClick={() => setAuthorizing(null)}>Cancel</Button>
                        <Button
                            size="sm"
                            disabled={Object.entries(authorizing?.pre_auth_fields ?? {}).some(
                                ([name, field]) => field.required && !values[name],
                            )}
                            onClick={() => authorizing && startOAuth(authorizing, values)}
                        >
                            <ExternalLink />
                            Continue
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>

            <SelectionSheet
                connector={configuring}
                onClose={() => setConfiguring(null)}
                onSaved={() => {
                    connectors.reload();
                    router.reload();
                }}
            />

            {/* Token connectors keep the plain credential form. */}
            <Sheet open={tokenForm !== null} onOpenChange={(open) => !open && setTokenForm(null)}>
                <SheetContent side="right" className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>Connect {tokenForm?.label}</SheetTitle>
                        <SheetDescription>{tokenForm?.summary}</SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-4 overflow-auto p-5">
                        {Object.entries(tokenForm?.fields ?? {}).map(([name, field]) => (
                            <div key={name} className="space-y-1.5">
                                <Label htmlFor={name}>
                                    {field.label}
                                    {field.required && <span className="ml-0.5 text-bad">*</span>}
                                </Label>
                                <Input
                                    id={name}
                                    type={field.type === 'password' ? 'password' : 'text'}
                                    value={values[name] ?? ''}
                                    onChange={(event) => setValues((current) => ({ ...current, [name]: event.target.value }))}
                                />
                                {field.help && <p className="text-[11px] text-muted-foreground">{field.help}</p>}
                            </div>
                        ))}
                        <p className="rounded-lg bg-muted px-3 py-2 text-[11px] text-muted-foreground">
                            Credentials are encrypted at rest and never written to logs.
                        </p>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-border px-5 py-3">
                        <Button variant="outline" size="sm" onClick={() => setTokenForm(null)}>Cancel</Button>
                        <Button size="sm" onClick={submitTokens} disabled={busy !== null}>
                            {busy?.endsWith(':connect') && <Loader2 className="animate-spin" />}
                            Connect
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}

/**
 * The step after authorisation: pick which ad account, property or page this
 * tenant actually is. Options are read live from the provider.
 */
function SelectionSheet({
    connector,
    onClose,
    onSaved,
}: {
    connector: ConnectorRow | null;
    onClose: () => void;
    onSaved: () => void;
}) {
    const [options, setOptions] = useState<Record<string, ResourceOption[]>>({});
    const [chosen, setChosen] = useState<Record<string, string>>({});
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const selections = { ...(connector?.pending_selections ?? {}) };
    Object.keys(connector?.selected ?? {}).forEach((key) => {
        if (!selections[key]) selections[key] = key.replace(/_/g, ' ');
    });

    useEffect(() => {
        if (!connector) return;

        setChosen(connector.selected ?? {});
        setErrors({});
        setLoading(true);

        Promise.all(
            Object.keys(selections).map(async (key) => {
                try {
                    const response = await apiGet<{ options: ResourceOption[] }>(`connectors/${connector.id}/resources/${key}`);
                    return [key, response.data.options] as const;
                } catch (error) {
                    setErrors((current) => ({
                        ...current,
                        [key]: error instanceof Error ? error.message : 'Could not load options.',
                    }));
                    return [key, []] as const;
                }
            }),
        )
            .then((entries) => setOptions(Object.fromEntries(entries)))
            .finally(() => setLoading(false));
    }, [connector?.id]);

    async function save() {
        if (!connector) return;
        setSaving(true);
        try {
            const response = await apiSend<{ status: string }>('POST', `connectors/${connector.id}/select`, chosen);
            toast.success(response.message);
            onSaved();
            if (response.data.status === 'connected') onClose();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Sheet open={connector !== null} onOpenChange={(open) => !open && onClose()}>
            <SheetContent side="right" className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Set up {connector?.label}</SheetTitle>
                    <SheetDescription>
                        Your account is authorised. Choose which accounts this brand&rsquo;s numbers should come from.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 space-y-4 overflow-auto p-5">
                    {loading && (
                        <p className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Loader2 className="size-3.5 animate-spin" />
                            Reading available accounts…
                        </p>
                    )}

                    {Object.entries(selections).map(([key, label]) => (
                        <div key={key} className="space-y-1.5">
                            <Label>{label}</Label>
                            {errors[key] ? (
                                <p className="text-[11px] text-bad">{errors[key]}</p>
                            ) : (options[key] ?? []).length === 0 && !loading ? (
                                <p className="text-[11px] text-muted-foreground">
                                    Nothing available — the authorising account may not have access to any {label.toLowerCase()}.
                                </p>
                            ) : (
                                <Select value={chosen[key] ?? ''} onValueChange={(value) => setChosen((c) => ({ ...c, [key]: value }))}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={`Choose a ${label.toLowerCase()}…`} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(options[key] ?? []).map((option) => (
                                            <SelectItem key={option.id} value={option.id}>
                                                {option.label}
                                                {option.meta && <span className="ml-1.5 text-muted-foreground">{option.meta}</span>}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>
                    ))}
                </div>

                <div className="flex justify-end gap-2 border-t border-border px-5 py-3">
                    <Button variant="outline" size="sm" onClick={onClose}>Close</Button>
                    <Button size="sm" onClick={save} disabled={saving || Object.keys(chosen).length === 0}>
                        {saving && <Loader2 className="animate-spin" />}
                        Save
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
