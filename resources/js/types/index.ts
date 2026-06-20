import type { PageProps as InertiaPageProps } from '@inertiajs/react';

export interface Auth {
    user: {
        id: string;
        name: string;
        email: string;
        role: string;
    } | null;
}

export interface Flash {
    success?: string;
    error?: string;
}

/**
 * Public-safe demo-session descriptor shared on every Inertia response while a
 * demo session is active (see HandleInertiaRequests::share). It is `null` for
 * real users. SECURITY: it deliberately carries NO `demo_session_id` — that
 * isolation token is server-only and must never reach the client.
 */
export interface DemoState {
    active: true;
    preset: string;
    /** Unix timestamp (seconds) at which the demo session expires. */
    expires_at: number;
}

/** Shared props injected on every Inertia response (see HandleInertiaRequests). */
export interface SharedProps {
    auth: Auth;
    flash: Flash;
    locale: 'es' | 'en';
    demo: DemoState | null;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
    InertiaPageProps & SharedProps & T;

declare module '@inertiajs/react' {
    // Merge our shared props into Inertia's global PageProps.
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface PageProps extends SharedProps {}
}
