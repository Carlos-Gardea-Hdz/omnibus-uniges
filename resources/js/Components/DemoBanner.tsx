import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { PageProps } from '@/types';
import { useLocale } from '@/Contexts/LocaleContext';

/**
 * Demo-mode banner (SLICE 006, SPEC §13). Reads the public-safe shared `demo`
 * prop (`usePage().props.demo`); renders nothing for real users (prop is null).
 *
 * While a demo session is active it shows a persistent `role="status"` bar with
 * a label, the remaining whole minutes until expiry (derived from the shared
 * `expires_at` Unix-seconds timestamp, recomputed once a minute), and an exit
 * control that POSTs to `/logout` — the same state-changing logout the rest of
 * the app uses, which destroys the demo session server-side and returns the
 * visitor to the public landing.
 *
 * The state cue is non-color (an icon glyph + text), it is dark/light- and
 * locale-aware, and it is meant to be mounted ONCE in the authenticated layout
 * chrome so it follows the visitor across every demo screen.
 */
export default function DemoBanner() {
    const { t } = useLocale();
    const { demo } = usePage<PageProps>().props;

    // Re-render once a minute so the remaining-minutes count stays honest while
    // the visitor lingers on a screen. The hook order is stable (the early
    // return below happens AFTER hooks), so React's rules are respected.
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        if (!demo) {
            return;
        }
        const id = window.setInterval(() => setNow(Date.now()), 60_000);
        return () => window.clearInterval(id);
    }, [demo]);

    if (!demo) {
        return null;
    }

    const remaining = Math.max(0, Math.ceil((demo.expires_at * 1000 - now) / 60_000));

    const exit = () => {
        router.post('/logout');
    };

    return (
        <div
            role="status"
            className="flex flex-wrap items-center justify-between gap-3 border-b border-warning/40 bg-warning/10 px-6 py-2 text-sm text-fg"
        >
            <span className="flex items-center gap-2 font-medium">
                <span aria-hidden="true" className="text-base leading-none">
                    ⚑
                </span>
                <span>{t('demo.banner.label')}</span>
                <span className="text-fg-muted">
                    · {t('demo.banner.remaining').replace('{minutes}', String(remaining))}
                </span>
            </span>
            <button
                type="button"
                onClick={exit}
                className="inline-flex h-9 items-center justify-center rounded-md border border-border bg-surface-raised px-3 text-sm font-medium text-fg transition-colors hover:bg-border focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
                {t('demo.banner.exit')}
            </button>
        </div>
    );
}
