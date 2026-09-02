import type { ApiEnvelope } from '@/types';

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly requiredPermission?: string[],
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export async function apiGet<T>(path: string, params: Record<string, unknown> = {}, signal?: AbortSignal): Promise<ApiEnvelope<T>> {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, String(value));
        }
    });

    const url = `/api/${path.replace(/^\//, '')}${query.toString() ? `?${query}` : ''}`;
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal,
    });

    const body = (await response.json().catch(() => null)) as ApiEnvelope<T> | null;

    if (!response.ok || !body?.success) {
        throw new ApiError(
            body?.message ?? `Request failed (${response.status})`,
            response.status,
            (body?.meta?.required_permission as string[]) ?? undefined,
        );
    }

    return body;
}

export async function apiSend<T>(method: 'POST' | 'PUT' | 'PATCH' | 'DELETE', path: string, payload: unknown = {}): Promise<ApiEnvelope<T>> {
    const response = await fetch(`/api/${path.replace(/^\//, '')}`, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const body = (await response.json().catch(() => null)) as ApiEnvelope<T> | null;

    if (!response.ok || !body?.success) {
        throw new ApiError(body?.message ?? `Request failed (${response.status})`, response.status);
    }

    return body;
}
