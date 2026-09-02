import { cn } from '@/lib/utils';

/** Skeletons everywhere — never spinners. */
export function Skeleton({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('skeleton rounded-md', className)} {...props} />;
}

export function SkeletonText({ lines = 3, className }: { lines?: number; className?: string }) {
    return (
        <div className={cn('space-y-2', className)}>
            {Array.from({ length: lines }).map((_, index) => (
                <Skeleton key={index} className={cn('h-3', index === lines - 1 ? 'w-2/3' : 'w-full')} />
            ))}
        </div>
    );
}

export function SkeletonChart({ className }: { className?: string }) {
    return (
        <div className={cn('flex h-[220px] items-end gap-1.5', className)}>
            {Array.from({ length: 24 }).map((_, index) => (
                <Skeleton key={index} className="flex-1 rounded-sm" style={{ height: `${28 + ((index * 37) % 62)}%` }} />
            ))}
        </div>
    );
}

export function SkeletonTable({ rows = 5, cols = 4 }: { rows?: number; cols?: number }) {
    return (
        <div className="space-y-2">
            <Skeleton className="h-8 w-full" />
            {Array.from({ length: rows }).map((_, rowIndex) => (
                <div key={rowIndex} className="flex gap-3">
                    {Array.from({ length: cols }).map((_, colIndex) => (
                        <Skeleton key={colIndex} className={cn('h-6', colIndex === 0 ? 'flex-[2]' : 'flex-1')} />
                    ))}
                </div>
            ))}
        </div>
    );
}
