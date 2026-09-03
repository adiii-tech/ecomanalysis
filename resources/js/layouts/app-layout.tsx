import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    Command as CommandIcon,
    LogOut,
    Moon,
    PanelLeftClose,
    PanelLeftOpen,
    Sparkles,
    Sun,
    User as UserIcon,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { CommandPalette } from '@/components/app/command-palette';
import { SyncIndicator } from '@/components/app/sync-indicator';
import { DateRangePicker } from '@/components/app/date-range-picker';
import { ChannelFilter } from '@/components/app/channel-filter';
import { SavedViews } from '@/components/app/saved-views';
import { usePermissions } from '@/hooks/use-permissions';
import { ACCENTS, useAppearance, type Accent } from '@/hooks/use-appearance';
import { NAVIGATION } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

function DemoBanner() {
    const { tenant } = usePage<SharedProps>().props;

    if (!tenant?.is_demo) return null;

    return (
        <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-primary/20 bg-primary/8 px-4 py-2 text-center text-xs">
            <span className="text-foreground/85">
                You&rsquo;re exploring sample data. Tell us about your business and we&rsquo;ll set this up with your numbers.
            </span>
            <Link href="/onboarding" className="font-semibold text-primary underline-offset-2 hover:underline">
                Build mine →
            </Link>
        </div>
    );
}

function Sidebar({ collapsed, onToggle }: { collapsed: boolean; onToggle: () => void }) {
    const { url, props } = usePage<SharedProps>();
    const { can } = usePermissions();

    return (
        <aside
            className={cn(
                'hidden shrink-0 flex-col border-r border-sidebar-border bg-sidebar transition-[width] duration-200 lg:flex',
                collapsed ? 'w-[62px]' : 'w-[228px]',
            )}
        >
            <div className={cn('flex h-14 items-center gap-2 px-3', collapsed && 'justify-center px-0')}>
                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                    <span className="text-sm font-bold">{props.tenant?.name?.[0] ?? 'L'}</span>
                </div>
                {!collapsed && (
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold leading-tight">{props.tenant?.name ?? 'Ledgerloop'}</p>
                        <p className="truncate text-[10px] uppercase tracking-wide text-muted-foreground">{props.tenant?.plan} plan</p>
                    </div>
                )}
            </div>

            <nav className="flex-1 space-y-4 overflow-y-auto px-2 py-2 scrollbar-thin">
                {NAVIGATION.map((section) => {
                    const items = section.items.filter((item) => !item.permission || can(item.permission));
                    if (items.length === 0) return null;

                    return (
                        <div key={section.label} className="space-y-0.5">
                            {!collapsed && (
                                <p className="px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground/70">
                                    {section.label}
                                </p>
                            )}
                            {items.map((item) => {
                                const active = url.startsWith(item.href);
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        title={collapsed ? item.label : undefined}
                                        className={cn(
                                            'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-colors',
                                            collapsed && 'justify-center px-0',
                                            active
                                                ? 'bg-sidebar-accent font-medium text-accent-foreground'
                                                : 'text-sidebar-foreground hover:bg-sidebar-accent/60',
                                        )}
                                    >
                                        <item.icon className="size-4 shrink-0" />
                                        {!collapsed && <span className="truncate">{item.label}</span>}
                                    </Link>
                                );
                            })}
                        </div>
                    );
                })}
            </nav>

            <div className="border-t border-sidebar-border p-2">
                <Button variant="ghost" size="sm" onClick={onToggle} className={cn('w-full gap-2 text-xs text-muted-foreground', collapsed && 'justify-center px-0')}>
                    {collapsed ? <PanelLeftOpen className="size-4" /> : <PanelLeftClose className="size-4" />}
                    {!collapsed && 'Collapse'}
                </Button>
            </div>
        </aside>
    );
}

function UserMenu() {
    const { auth } = usePage<SharedProps>().props;
    const { theme, setTheme, accent, setAccent } = useAppearance(
        (auth.user?.theme as 'light' | 'dark' | 'system') ?? 'system',
        (auth.user?.accent_color as Accent) ?? 'indigo',
    );

    if (!auth.user) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="gap-2 px-2">
                    <span className="flex size-6 items-center justify-center rounded-full bg-primary/12 text-[11px] font-semibold text-primary">
                        {auth.user.name.slice(0, 2).toUpperCase()}
                    </span>
                    <span className="hidden text-xs sm:inline">{auth.user.name}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>
                    <p className="text-xs font-semibold normal-case tracking-normal text-foreground">{auth.user.name}</p>
                    <p className="text-[11px] font-normal normal-case tracking-normal text-muted-foreground">{auth.user.role}</p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                <div className="px-2.5 py-1.5">
                    <p className="mb-1.5 text-[11px] font-medium text-muted-foreground">Theme</p>
                    <div className="flex gap-1">
                        {(['light', 'dark', 'system'] as const).map((option) => (
                            <button
                                key={option}
                                type="button"
                                onClick={() => setTheme(option)}
                                className={cn(
                                    'flex-1 rounded-md border px-1.5 py-1 text-[11px] capitalize transition-colors',
                                    theme === option ? 'border-primary bg-primary/10 text-primary' : 'border-border hover:bg-accent',
                                )}
                            >
                                {option === 'light' ? <Sun className="mx-auto size-3" /> : option === 'dark' ? <Moon className="mx-auto size-3" /> : option}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="px-2.5 py-1.5">
                    <p className="mb-1.5 text-[11px] font-medium text-muted-foreground">Accent</p>
                    <div className="flex gap-1.5">
                        {ACCENTS.map((option) => (
                            <button
                                key={option}
                                type="button"
                                aria-label={`Use ${option} accent`}
                                onClick={() => setAccent(option)}
                                data-accent={option === 'indigo' ? undefined : option}
                                className={cn(
                                    'size-5 rounded-full border-2 bg-primary transition',
                                    accent === option ? 'border-foreground' : 'border-transparent',
                                )}
                            />
                        ))}
                    </div>
                </div>

                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/settings/profile">
                        <UserIcon />
                        Profile & security
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => router.post('/logout')}>
                    <LogOut />
                    Sign out
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AppLayout({
    title,
    description,
    actions,
    showFilters = true,
    surface,
    filterExtras,
    breadcrumb,
    children,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    showFilters?: boolean;
    /** Saved views are scoped to the screen they were saved on. */
    surface?: string;
    filterExtras?: ReactNode;
    breadcrumb?: { label: string; href: string };
    children: ReactNode;
}) {
    const { auth } = usePage<SharedProps>().props;
    const currentUrl = usePage().url;
    const [collapsed, setCollapsed] = useState(() => localStorage.getItem('sidebar_collapsed') === '1');
    const { can } = usePermissions();

    useEffect(() => {
        localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0');
    }, [collapsed]);

    return (
        <>
            <div className="flex min-h-screen bg-background">
                    <Sidebar collapsed={collapsed} onToggle={() => setCollapsed((value) => !value)} />

                    <div className="flex min-w-0 flex-1 flex-col">
                        <DemoBanner />

                        <header className="sticky top-0 z-30 border-b border-border bg-background/85 backdrop-blur-md">
                            <div className="flex h-14 items-center gap-3 px-4 sm:px-6">
                                <div className="min-w-0 flex-1">
                                    {breadcrumb && (
                                        <Link href={breadcrumb.href} className="mb-0.5 inline-flex items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground">
                                            <ChevronLeft className="size-3" />
                                            {breadcrumb.label}
                                        </Link>
                                    )}
                                    <h1 className="truncate text-base font-semibold tracking-tight">{title}</h1>
                                    {description && <p className="truncate text-xs text-muted-foreground">{description}</p>}
                                </div>

                                <div className="flex items-center gap-1.5">
                                    {can('ai.chat.view') && auth.user && (
                                        <div className="hidden items-center gap-1.5 rounded-full bg-primary/8 px-2.5 py-1 text-[11px] text-primary md:flex">
                                            <Sparkles className="size-3" />
                                            {auth.user.ai_credits_remaining} credits left
                                        </div>
                                    )}
                                    <SyncIndicator />
                                    <Separator orientation="vertical" className="mx-0.5 h-5" />
                                    <UserMenu />
                                </div>
                            </div>

                            {/* A page with no date filters still has actions, so this row
                                renders whenever there is anything to put in it. */}
                            {(showFilters || actions) && (
                                <div className="flex flex-wrap items-center gap-2 border-t border-border/70 px-4 py-2 sm:px-6">
                                    {showFilters && (
                                        <>
                                            <DateRangePicker />
                                            <ChannelFilter />
                                            <SavedViews surface={surface ?? surfaceFromUrl(currentUrl)} />
                                            {filterExtras}
                                        </>
                                    )}
                                    <div className="ml-auto flex items-center gap-1.5">
                                        {actions}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="hidden gap-1.5 text-xs text-muted-foreground sm:inline-flex"
                                            onClick={() =>
                                                document.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true, bubbles: true }))
                                            }
                                        >
                                            <CommandIcon className="size-3" />K
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </header>

                        <main className="flex-1 space-y-4 p-4 sm:p-6">{children}</main>
                    </div>
                </div>

            <CommandPalette />
        </>
    );
}

/**
 * Saved views belong to a screen, so the surface key is the path without its
 * query string or trailing ids: /reports/channel-scorecard, /operations.
 */
function surfaceFromUrl(url: string): string {
    const path = url.split('?')[0].replace(/\/$/, '');

    return path === '' ? '/dashboard' : path;
}
