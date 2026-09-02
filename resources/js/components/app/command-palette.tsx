import { router } from '@inertiajs/react';
import { Command } from 'cmdk';
import { useEffect, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { NAVIGATION, REPORT_LINKS } from '@/lib/navigation';
import { cn } from '@/lib/utils';

/** ⌘K — jump to any page or report the user is allowed to see. */
export function CommandPalette() {
    const [open, setOpen] = useState(false);
    const { can } = usePermissions();

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                setOpen((value) => !value);
            }
        }

        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    function go(href: string) {
        setOpen(false);
        router.visit(href);
    }

    const pages = NAVIGATION.flatMap((section) => section.items).filter((item) => !item.permission || can(item.permission));
    const reports = REPORT_LINKS.filter((report) => can(`reports.${report.key}.view`));

    return (
        <Dialog.Root open={open} onOpenChange={setOpen}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-black/45 backdrop-blur-[1px] data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <Dialog.Content className="fixed left-1/2 top-[18%] z-50 w-[min(94vw,560px)] -translate-x-1/2 overflow-hidden rounded-xl border border-border bg-popover shadow-2xl data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95">
                    <Dialog.Title className="sr-only">Command palette</Dialog.Title>
                    <Command loop>
                        <Command.Input
                            autoFocus
                            placeholder="Jump to a page, report, SKU or order…"
                            className="w-full border-b border-border bg-transparent px-4 py-3 text-sm outline-none placeholder:text-muted-foreground"
                        />
                        <Command.List className="max-h-80 overflow-auto p-1.5 scrollbar-thin">
                            <Command.Empty className="py-8 text-center text-xs text-muted-foreground">
                                Nothing matches that.
                            </Command.Empty>

                            <Command.Group heading="Pages" className="[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-[11px] [&_[cmdk-group-heading]]:font-semibold [&_[cmdk-group-heading]]:uppercase [&_[cmdk-group-heading]]:tracking-wide [&_[cmdk-group-heading]]:text-muted-foreground">
                                {pages.map((item) => (
                                    <Command.Item
                                        key={item.href}
                                        value={`${item.label} ${item.href}`}
                                        onSelect={() => go(item.href)}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm',
                                            'data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground',
                                        )}
                                    >
                                        <item.icon className="size-4 text-muted-foreground" />
                                        {item.label}
                                    </Command.Item>
                                ))}
                            </Command.Group>

                            {reports.length > 0 && (
                                <Command.Group heading="Reports" className="[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-[11px] [&_[cmdk-group-heading]]:font-semibold [&_[cmdk-group-heading]]:uppercase [&_[cmdk-group-heading]]:tracking-wide [&_[cmdk-group-heading]]:text-muted-foreground">
                                    {reports.map((report) => (
                                        <Command.Item
                                            key={report.key}
                                            value={`report ${report.label}`}
                                            onSelect={() => go(`/reports/${report.slug}`)}
                                            className="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground"
                                        >
                                            <span className="text-[11px] uppercase tracking-wide text-muted-foreground">{report.category}</span>
                                            {report.label}
                                        </Command.Item>
                                    ))}
                                </Command.Group>
                            )}
                        </Command.List>
                    </Command>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
