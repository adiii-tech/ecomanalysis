import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Copy, KeyRound, Mail, RotateCcw, Search, ShieldCheck, UserPlus } from 'lucide-react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable } from '@/components/app/data-table';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input, Label } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/hooks/use-permissions';
import { apiGet, apiSend } from '@/lib/api';
import { formatDateTime, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string | null;
    is_active: boolean;
    mfa_enabled: boolean;
    must_change_password: boolean;
    last_login_at: string | null;
    has_overrides: boolean;
}

interface InviteRow {
    id: number;
    email: string;
    role: string;
    expires_at: string;
    expired: boolean;
    send_count: number;
}

interface PermissionRow {
    permission: string;
    widget: string;
    action: string;
    label: string;
    from_role: boolean;
    granted_directly: boolean;
    effective: boolean;
}

interface ModuleRow {
    module: string;
    label: string;
    permissions: PermissionRow[];
    granted: number;
    total: number;
}

interface AuditRow {
    id: number;
    log: string;
    description: string;
    causer: string;
    properties: Record<string, unknown>;
    created_at: string;
}

export default function AdminUsers() {
    const { can } = usePermissions();
    const [tab, setTab] = useState<'users' | 'roles' | 'audit' | 'settings'>('users');
    const [users, setUsers] = useState<UserRow[]>([]);
    const [invites, setInvites] = useState<InviteRow[]>([]);
    const [roles, setRoles] = useState<string[]>([]);
    const [seats, setSeats] = useState({ used: 0, limit: 0 });
    const [audit, setAudit] = useState<AuditRow[]>([]);
    const [roleRows, setRoleRows] = useState<{ name: string; description: string; is_builtin: boolean; users_count: number; permission_count: number }[]>([]);
    const [settings, setSettings] = useState<Record<string, Record<string, number | string>> | null>(null);
    const [editing, setEditing] = useState<UserRow | null>(null);
    const [permissions, setPermissions] = useState<ModuleRow[] | null>(null);
    const [inviting, setInviting] = useState(false);
    const [inviteForm, setInviteForm] = useState({ email: '', role: 'ANALYST' });
    const [search, setSearch] = useState('');

    useEffect(() => {
        void load();
    }, [tab]);

    async function load() {
        try {
            if (tab === 'users') {
                const response = await apiGet<{ users: UserRow[]; invitations: InviteRow[]; roles: string[]; seat_limit: number; seats_used: number }>('admin/users');
                setUsers(response.data.users);
                setInvites(response.data.invitations);
                setRoles(response.data.roles);
                setSeats({ used: response.data.seats_used, limit: response.data.seat_limit });
            }
            if (tab === 'audit') setAudit((await apiGet<{ rows: AuditRow[] }>('admin/audit')).data.rows);
            if (tab === 'roles') setRoleRows((await apiGet<{ rows: never[] }>('admin/roles')).data.rows);
            if (tab === 'settings') setSettings((await apiGet<never>('admin/settings')).data);
        } catch {
            /* gated tabs simply stay empty */
        }
    }

    async function openPermissions(user: UserRow) {
        setEditing(user);
        setPermissions(null);
        const response = await apiGet<{ modules: ModuleRow[] }>(`admin/users/${user.id}/permissions`);
        setPermissions(response.data.modules);
    }

    function toggle(permission: string, granted: boolean) {
        setPermissions((current) =>
            current?.map((module) => ({
                ...module,
                permissions: module.permissions.map((row) =>
                    row.permission === permission
                        ? { ...row, granted_directly: granted, effective: granted || row.from_role }
                        : row,
                ),
            })) ?? null,
        );
    }

    async function savePermissions() {
        if (!editing || !permissions) return;
        const grant = permissions.flatMap((m) => m.permissions.filter((p) => p.granted_directly).map((p) => p.permission));

        try {
            await apiSend('PUT', `admin/users/${editing.id}/permissions`, { grant });
            toast.success('Permissions saved.');
            setEditing(null);
            void load();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save.');
        }
    }

    const filtered = useMemo(
        () => users.filter((u) => `${u.name} ${u.email} ${u.role}`.toLowerCase().includes(search.toLowerCase())),
        [users, search],
    );

    return (
        <AppLayout title="Admin" description="Users, roles, audit and settings" showFilters={false}>
            <Head title="Admin" />

            <div className="flex flex-wrap items-center justify-between gap-2">
                <Tabs value={tab} onValueChange={(value) => setTab(value as typeof tab)}>
                    <TabsList>
                        <TabsTrigger value="users">Users</TabsTrigger>
                        <TabsTrigger value="roles">Roles</TabsTrigger>
                        <TabsTrigger value="audit">Audit</TabsTrigger>
                        <TabsTrigger value="settings">Settings</TabsTrigger>
                    </TabsList>
                </Tabs>

                {tab === 'users' && can('admin.users.manage') && (
                    <Button size="sm" onClick={() => setInviting(true)} className="gap-1.5">
                        <UserPlus className="size-3.5" />
                        Invite user
                    </Button>
                )}
            </div>

            {tab === 'users' && (
                <>
                    <PermissionGuard permission="admin.users.view">
                        <ChartCard
                            title="Users"
                            subtitle={`${seats.used} of ${seats.limit} seats used`}
                            widgetKey="admin.users"
                            exportDataset={undefined}
                        >
                            <div className="relative mb-2 max-w-xs">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search users…" className="h-8 pl-8 text-xs" />
                            </div>

                            <DataTable<UserRow>
                                rows={filtered}
                                rowKey={(row) => row.id}
                                columns={[
                                    { key: 'name', header: 'User', value: (r) => r.name, render: (r) => (
                                        <div className="min-w-0">
                                            <p className="flex items-center gap-1.5 truncate font-medium">
                                                {r.name}
                                                {r.mfa_enabled && <ShieldCheck className="size-3 text-good" />}
                                            </p>
                                            <p className="truncate text-[11px] text-muted-foreground">{r.email}</p>
                                        </div>
                                    ) },
                                    { key: 'role', header: 'Role', value: (r) => r.role, render: (r) => (
                                        <span className="flex items-center gap-1.5">
                                            <Badge variant="secondary">{r.role ?? '—'}</Badge>
                                            {r.has_overrides && <Badge variant="warn">overrides</Badge>}
                                        </span>
                                    ) },
                                    { key: 'status', header: 'Status', value: (r) => (r.is_active ? 'active' : 'disabled'), render: (r) => (
                                        <Badge variant={r.is_active ? 'good' : 'muted'}>{r.is_active ? 'Active' : 'Disabled'}</Badge>
                                    ) },
                                    { key: 'last', header: 'Last sign-in', value: (r) => r.last_login_at, render: (r) => (
                                        <span className="text-muted-foreground">{r.last_login_at ? formatDateTime(r.last_login_at) : 'never'}</span>
                                    ) },
                                    { key: 'actions', header: '', align: 'right', render: (r) => (
                                        can('admin.users.manage') && (
                                            <div className="flex justify-end gap-1">
                                                <Button size="xs" variant="ghost" onClick={() => openPermissions(r)}>Permissions</Button>
                                                <Button
                                                    size="xs"
                                                    variant="ghost"
                                                    onClick={async () => {
                                                        const response = await apiSend<{ temporary_password: string }>('POST', `admin/users/${r.id}/password`);
                                                        await navigator.clipboard.writeText(response.data.temporary_password).catch(() => undefined);
                                                        toast.success('Temporary password copied. They must change it at next sign-in.');
                                                    }}
                                                >
                                                    <KeyRound className="size-3" />
                                                </Button>
                                                <Switch
                                                    checked={r.is_active}
                                                    onCheckedChange={async (checked) => {
                                                        try {
                                                            await apiSend('PUT', `admin/users/${r.id}`, { is_active: checked });
                                                            void load();
                                                        } catch (error) {
                                                            toast.error(error instanceof Error ? error.message : 'Could not update.');
                                                        }
                                                    }}
                                                />
                                            </div>
                                        )
                                    ) },
                                ]}
                            />
                        </ChartCard>
                    </PermissionGuard>

                    {invites.length > 0 && (
                        <ChartCard title="Pending invitations" subtitle="Not yet accepted">
                            <DataTable<InviteRow>
                                dense
                                rows={invites}
                                rowKey={(row) => row.id}
                                columns={[
                                    { key: 'email', header: 'Email', value: (r) => r.email, render: (r) => <span className="font-medium">{r.email}</span> },
                                    { key: 'role', header: 'Role', value: (r) => r.role, render: (r) => <Badge variant="secondary">{r.role}</Badge> },
                                    { key: 'expires', header: 'Expires', value: (r) => r.expires_at, render: (r) => (
                                        <span className={r.expired ? 'text-bad' : 'text-muted-foreground'}>
                                            {r.expired ? 'Expired' : formatDateTime(r.expires_at)}
                                        </span>
                                    ) },
                                    { key: 'actions', header: '', align: 'right', render: (r) => (
                                        <div className="flex justify-end gap-1">
                                            <Button size="xs" variant="ghost" onClick={async () => {
                                                const response = await apiSend<{ accept_url: string }>('POST', `admin/invitations/${r.id}/resend`);
                                                await navigator.clipboard.writeText(response.data.accept_url).catch(() => undefined);
                                                toast.success('Invite link refreshed and copied.');
                                                void load();
                                            }}>
                                                <Copy className="size-3" />
                                            </Button>
                                            <Button size="xs" variant="ghost" className="text-bad" onClick={async () => {
                                                await apiSend('DELETE', `admin/invitations/${r.id}`);
                                                void load();
                                            }}>
                                                Revoke
                                            </Button>
                                        </div>
                                    ) },
                                ]}
                            />
                        </ChartCard>
                    )}
                </>
            )}

            {tab === 'roles' && (
                <PermissionGuard permission="admin.roles.view">
                    <ChartCard title="Roles" subtitle="Built-in roles plus anything you have added">
                        <div className="grid gap-2 sm:grid-cols-2">
                            {roleRows.map((role) => (
                                <Card key={role.name} className="p-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="text-sm font-semibold">{role.name}</p>
                                        {role.is_builtin && <Badge variant="muted">built-in</Badge>}
                                    </div>
                                    <p className="mt-1 text-xs leading-snug text-muted-foreground">{role.description}</p>
                                    <p className="mt-2 text-[11px] text-muted-foreground">
                                        {formatNumber(role.permission_count)} permissions · {formatNumber(role.users_count)} user{role.users_count === 1 ? '' : 's'}
                                    </p>
                                </Card>
                            ))}
                        </div>
                    </ChartCard>
                </PermissionGuard>
            )}

            {tab === 'audit' && (
                <PermissionGuard permission="admin.audit.view">
                    <ChartCard title="Audit log" subtitle="Every mutation and export">
                        <DataTable<AuditRow>
                            searchable
                            rows={audit}
                            rowKey={(row) => row.id}
                            columns={[
                                { key: 'when', header: 'When', value: (r) => r.created_at, render: (r) => (
                                    <span className="text-muted-foreground">{formatDateTime(r.created_at)}</span>
                                ) },
                                { key: 'log', header: 'Area', value: (r) => r.log, render: (r) => <Badge variant="muted">{r.log}</Badge> },
                                { key: 'action', header: 'Action', value: (r) => r.description, render: (r) => <span className="font-medium">{r.description}</span> },
                                { key: 'causer', header: 'By', value: (r) => r.causer, render: (r) => r.causer },
                                { key: 'props', header: 'Details', value: (r) => JSON.stringify(r.properties), render: (r) => (
                                    <span className="line-clamp-1 text-[11px] text-muted-foreground">
                                        {Object.entries(r.properties ?? {}).map(([k, v]) => `${k}=${typeof v === 'object' ? JSON.stringify(v) : v}`).join(' · ')}
                                    </span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            )}

            {tab === 'settings' && settings && (
                <PermissionGuard permission="admin.settings.view">
                    <SettingsEditor settings={settings} onSaved={load} canManage={can('admin.settings.manage')} />
                </PermissionGuard>
            )}

            <Sheet open={inviting} onOpenChange={setInviting}>
                <SheetContent side="right" className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>Invite a user</SheetTitle>
                        <SheetDescription>They pick their own password when they accept.</SheetDescription>
                    </SheetHeader>
                    <div className="flex-1 space-y-4 p-5">
                        <div className="space-y-1.5">
                            <Label htmlFor="invite-email">Email</Label>
                            <Input id="invite-email" type="email" value={inviteForm.email} onChange={(e) => setInviteForm((f) => ({ ...f, email: e.target.value }))} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Role</Label>
                            <Select value={inviteForm.role} onValueChange={(value) => setInviteForm((f) => ({ ...f, role: value }))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => <SelectItem key={role} value={role}>{role}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 border-t border-border px-5 py-3">
                        <Button variant="outline" size="sm" onClick={() => setInviting(false)}>Cancel</Button>
                        <Button size="sm" onClick={async () => {
                            try {
                                const response = await apiSend<{ accept_url: string }>('POST', 'admin/users/invite', inviteForm);
                                await navigator.clipboard.writeText(response.data.accept_url).catch(() => undefined);
                                toast.success('Invite created and link copied.');
                                setInviting(false);
                                setInviteForm({ email: '', role: 'ANALYST' });
                                void load();
                            } catch (error) {
                                toast.error(error instanceof Error ? error.message : 'Could not invite.');
                            }
                        }}>
                            <Mail className="size-3.5" />
                            Create invite
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>

            <Sheet open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
                <SheetContent side="right" className="sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Permissions · {editing?.name}</SheetTitle>
                        <SheetDescription>
                            Their {editing?.role} role grants the checked-and-locked items. Anything you tick here is an
                            override on top.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-4 overflow-auto p-5 scrollbar-thin">
                        {permissions === null && <p className="text-xs text-muted-foreground">Loading…</p>}
                        {permissions?.map((module) => (
                            <div key={module.module} className="space-y-1.5">
                                <p className="flex items-baseline justify-between text-xs font-semibold">
                                    {module.label}
                                    <span className="text-[11px] font-normal text-muted-foreground">{module.granted}/{module.total}</span>
                                </p>
                                <div className="grid gap-1 sm:grid-cols-2">
                                    {module.permissions.map((row) => (
                                        <label
                                            key={row.permission}
                                            className={cn(
                                                'flex items-center gap-2 rounded px-1.5 py-1 text-[11px]',
                                                row.from_role && 'opacity-60',
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={row.effective}
                                                disabled={row.from_role}
                                                onChange={(e) => toggle(row.permission, e.target.checked)}
                                                className="size-3 rounded border-input"
                                            />
                                            <span className="truncate">{row.widget.replace(/_/g, ' ')} · {row.action}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-between gap-2 border-t border-border px-5 py-3">
                        <Button
                            variant="outline"
                            size="sm"
                            className="gap-1.5"
                            onClick={async () => {
                                if (!editing) return;
                                await apiSend('POST', `admin/users/${editing.id}/permissions/reset`);
                                toast.success('Reset to role defaults.');
                                setEditing(null);
                                void load();
                            }}
                        >
                            <RotateCcw className="size-3.5" />
                            Reset to role
                        </Button>
                        <div className="flex gap-2">
                            <Button variant="ghost" size="sm" onClick={() => setEditing(null)}>Cancel</Button>
                            <Button size="sm" onClick={savePermissions}>Save</Button>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}

/** Cost settings and benchmarks — the numbers every margin figure depends on. */
function SettingsEditor({
    settings,
    onSaved,
    canManage,
}: {
    settings: Record<string, Record<string, number | string>>;
    onSaved: () => void;
    canManage: boolean;
}) {
    const [costs, setCosts] = useState(settings.cost_settings);
    const [benchmarks, setBenchmarks] = useState(settings.benchmarks);
    const [profile, setProfile] = useState(settings.tenant_profile ?? {});
    const [notifications, setNotifications] = useState<Record<string, unknown>>(settings.notifications ?? {});
    const [whatsappToken, setWhatsappToken] = useState('');
    const [saving, setSaving] = useState(false);

    // Recipient lists are edited as comma-separated text and stored as arrays.
    const list = (key: string): string => ((notifications[key] as string[] | undefined) ?? []).join(', ');
    const setList = (key: string, value: string) =>
        setNotifications((current) => ({
            ...current,
            [key]: value.split(/[,\s]+/).map((item) => item.trim()).filter(Boolean),
        }));

    const save = async () => {
        setSaving(true);
        try {
            const response = await apiSend<null>('PUT', 'admin/settings', {
                cost_settings: costs,
                benchmarks,
                tenant_profile: profile,
                notifications: whatsappToken ? { ...notifications, whatsapp_token: whatsappToken } : notifications,
            });
            toast.success(response.message);
            setWhatsappToken('');
            onSaved();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save.');
        } finally {
            setSaving(false);
        }
    };

    const COST_LABELS: Record<string, string> = {
        packaging_cost: 'Packaging per order (₹)',
        per_order_fixed_cost: 'Fixed handling per order (₹)',
        cod_charge: 'COD collection charge (₹)',
        return_handling_cost: 'Return handling (₹)',
        rto_handling_cost: 'RTO handling (₹)',
        default_shipping_cost: 'Default shipping cost (₹)',
        monthly_fixed_opex: 'Monthly fixed opex (₹)',
        gateway_fee_pct: 'Payment gateway fee (%)',
    };

    const BENCHMARK_LABELS: Record<string, string> = {
        target_roas: 'Target ROAS (×)',
        target_margin_pct: 'Target margin (%)',
        target_repeat_rate: 'Target repeat rate (%)',
        dispatch_sla_days: 'Dispatch SLA (days)',
        delivery_sla_days: 'Delivery SLA (days)',
        rto_threshold_pct: 'RTO alert threshold (%)',
        return_threshold_pct: 'Return alert threshold (%)',
        days_of_cover_threshold: 'Low stock threshold (days)',
        monthly_revenue_target: 'Monthly revenue target (₹)',
    };

    return (
        <div className="grid gap-4 xl:grid-cols-2">
            <ChartCard
                title="Cost settings"
                subtitle="These drive every margin number in the product"
                widgetKey="admin.cost_settings"
            >
                <div className="grid gap-2 sm:grid-cols-2">
                    {Object.entries(COST_LABELS).map(([key, label]) => (
                        <div key={key} className="space-y-1">
                            <Label htmlFor={key}>{label}</Label>
                            <Input
                                id={key}
                                type="number"
                                step="0.01"
                                disabled={!canManage}
                                value={costs[key] ?? 0}
                                onChange={(e) => setCosts((c) => ({ ...c, [key]: Number(e.target.value) }))}
                            />
                        </div>
                    ))}
                </div>
            </ChartCard>

            <ChartCard title="Benchmarks" subtitle="What the verdicts judge against" widgetKey="admin.benchmarks">
                <div className="grid gap-2 sm:grid-cols-2">
                    {Object.entries(BENCHMARK_LABELS).map(([key, label]) => (
                        <div key={key} className="space-y-1">
                            <Label htmlFor={key}>{label}</Label>
                            <Input
                                id={key}
                                type="number"
                                step="0.1"
                                disabled={!canManage}
                                value={benchmarks[key] ?? 0}
                                onChange={(e) => setBenchmarks((b) => ({ ...b, [key]: Number(e.target.value) }))}
                            />
                        </div>
                    ))}
                </div>

            </ChartCard>

            <ChartCard
                title="Tax identity"
                subtitle="Needed to split intra-state supply from inter-state on the GST report"
                widgetKey="admin.cost_settings"
            >
                <div className="grid gap-2 sm:grid-cols-2">
                    <div className="space-y-1">
                        <Label htmlFor="gst_state">Your GST state</Label>
                        <Input
                            id="gst_state"
                            placeholder="Maharashtra"
                            disabled={!canManage}
                            value={(profile.gst_state as string) ?? ''}
                            onChange={(e) => setProfile((current) => ({ ...current, gst_state: e.target.value }))}
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="gstin">GSTIN</Label>
                        <Input
                            id="gstin"
                            placeholder="27AAAAA0000A1Z5"
                            disabled={!canManage}
                            value={(profile.gstin as string) ?? ''}
                            onChange={(e) => setProfile((current) => ({ ...current, gstin: e.target.value }))}
                        />
                    </div>
                </div>
            </ChartCard>

            <ChartCard
                title="Alert delivery"
                subtitle="Where a firing alert actually goes"
                widgetKey="admin.cost_settings"
            >
                <div className="space-y-3">
                    <div className="space-y-1">
                        <Label htmlFor="alert_emails">Email recipients</Label>
                        <Input
                            id="alert_emails"
                            placeholder="founder@brand.com, ops@brand.com"
                            disabled={!canManage}
                            value={list('email_recipients')}
                            onChange={(e) => setList('email_recipients', e.target.value)}
                        />
                        <p className="text-[11px] text-muted-foreground">Leave empty to email every active user.</p>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="slack_webhook">Slack incoming webhook</Label>
                        <Input
                            id="slack_webhook"
                            placeholder="https://hooks.slack.com/services/…"
                            disabled={!canManage}
                            value={(notifications.slack_webhook_url as string) ?? ''}
                            onChange={(e) => setNotifications((c) => ({ ...c, slack_webhook_url: e.target.value }))}
                        />
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor="wa_phone">WhatsApp phone number ID</Label>
                            <Input
                                id="wa_phone"
                                disabled={!canManage}
                                value={(notifications.whatsapp_phone_number_id as string) ?? ''}
                                onChange={(e) => setNotifications((c) => ({ ...c, whatsapp_phone_number_id: e.target.value }))}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="wa_token">
                                WhatsApp token {notifications.whatsapp_token_set ? '(stored)' : ''}
                            </Label>
                            <Input
                                id="wa_token"
                                type="password"
                                placeholder={notifications.whatsapp_token_set ? 'Leave blank to keep' : 'Cloud API token'}
                                disabled={!canManage}
                                value={whatsappToken}
                                onChange={(e) => setWhatsappToken(e.target.value)}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="wa_template">Approved template name</Label>
                            <Input
                                id="wa_template"
                                placeholder="analytics_alert"
                                disabled={!canManage}
                                value={(notifications.whatsapp_template as string) ?? ''}
                                onChange={(e) => setNotifications((c) => ({ ...c, whatsapp_template: e.target.value }))}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="wa_recipients">WhatsApp numbers</Label>
                            <Input
                                id="wa_recipients"
                                placeholder="919812345678"
                                disabled={!canManage}
                                value={list('whatsapp_recipients')}
                                onChange={(e) => setList('whatsapp_recipients', e.target.value)}
                            />
                        </div>
                    </div>
                </div>
            </ChartCard>

            <ChartCard title="Digests" subtitle="Standing emails your team gets without asking" widgetKey="admin.cost_settings">
                <div className="space-y-3">
                    <div className="grid gap-2 sm:grid-cols-[1fr_7rem]">
                        <div className="space-y-1">
                            <Label htmlFor="daily_brief">Morning brief</Label>
                            <Input
                                id="daily_brief"
                                placeholder="founder@brand.com"
                                disabled={!canManage}
                                value={list('daily_brief_recipients')}
                                onChange={(e) => setList('daily_brief_recipients', e.target.value)}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="daily_hour">Hour</Label>
                            <Input
                                id="daily_hour"
                                type="number"
                                min={0}
                                max={23}
                                disabled={!canManage}
                                value={(notifications.daily_brief_hour as number) ?? 8}
                                onChange={(e) => setNotifications((c) => ({ ...c, daily_brief_hour: Number(e.target.value) }))}
                            />
                        </div>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-[1fr_7rem]">
                        <div className="space-y-1">
                            <Label htmlFor="weekly_review">Weekly business review (PDF)</Label>
                            <Input
                                id="weekly_review"
                                disabled={!canManage}
                                value={list('weekly_review_recipients')}
                                onChange={(e) => setList('weekly_review_recipients', e.target.value)}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="weekly_day">Day</Label>
                            <Select
                                value={String((notifications.weekly_review_day as number) ?? 1)}
                                onValueChange={(value) => setNotifications((c) => ({ ...c, weekly_review_day: Number(value) }))}
                            >
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day, index) => (
                                        <SelectItem key={day} value={String(index)}>{day}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="monthly_pnl">Monthly P&amp;L (PDF, sent on the 1st)</Label>
                        <Input
                            id="monthly_pnl"
                            disabled={!canManage}
                            value={list('monthly_pnl_recipients')}
                            onChange={(e) => setList('monthly_pnl_recipients', e.target.value)}
                        />
                    </div>
                </div>

                {canManage && (
                    <Button size="sm" className="mt-3 w-full" disabled={saving} onClick={save}>
                        Save settings
                    </Button>
                )}
            </ChartCard>
        </div>
    );
}
