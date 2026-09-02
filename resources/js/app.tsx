import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ErrorBoundary } from '@/components/app/error-boundary';
import { FilterProvider } from '@/hooks/use-filters';

const appName = import.meta.env.VITE_APP_NAME || 'Ledgerloop';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        createRoot(el).render(
            // These providers wrap the page itself, not just the layout: pages
            // call useWidget/useFilters in their own body, which runs before the
            // layout they return has mounted.
            <ErrorBoundary>
                <TooltipProvider delayDuration={200}>
                    <FilterProvider>
                        <App {...props} />
                        <Toaster position="bottom-right" richColors closeButton />
                    </FilterProvider>
                </TooltipProvider>
            </ErrorBoundary>,
        );
    },
    progress: { color: '#6366f1', showSpinner: false },
});
