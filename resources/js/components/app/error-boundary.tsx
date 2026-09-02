import { Component, type ErrorInfo, type ReactNode } from 'react';

interface State {
    error: Error | null;
}

/**
 * Without this, any exception thrown while rendering unmounts the whole tree
 * and leaves a blank white page with no clue what happened. Show the error
 * instead.
 */
export class ErrorBoundary extends Component<{ children: ReactNode }, State> {
    state: State = { error: null };

    static getDerivedStateFromError(error: Error): State {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error('[app] render error', error, info.componentStack);
    }

    render() {
        const { error } = this.state;

        if (!error) {
            return this.props.children;
        }

        return (
            <div className="flex min-h-screen items-center justify-center bg-background p-6">
                <div className="w-full max-w-lg space-y-3 rounded-xl border border-bad/30 bg-bad-soft/40 p-5">
                    <h1 className="text-sm font-semibold text-bad">This page failed to render</h1>
                    <p className="text-xs text-foreground/80">{error.message}</p>
                    {import.meta.env.DEV && error.stack && (
                        <pre className="max-h-64 overflow-auto rounded-lg bg-card p-3 text-[11px] leading-relaxed text-muted-foreground scrollbar-thin">
                            {error.stack}
                        </pre>
                    )}
                    <button
                        type="button"
                        onClick={() => window.location.reload()}
                        className="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground"
                    >
                        Reload
                    </button>
                </div>
            </div>
        );
    }
}
