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

/** Shared props injected on every Inertia response (see HandleInertiaRequests). */
export interface SharedProps {
    auth: Auth;
    flash: Flash;
    locale: 'es' | 'en';
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
    InertiaPageProps & SharedProps & T;

declare module '@inertiajs/react' {
    // Merge our shared props into Inertia's global PageProps.
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface PageProps extends SharedProps {}
}
