import { Head, Link, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { useLocale } from '@/Contexts/LocaleContext';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import DemoBanner from '@/Components/DemoBanner';

/**
 * Read-only staff overview home (SLICE 007, SPEC §7). A snapshot of the whole
 * pipeline: aggregate totals, a 9-state count breakdown (step order), and quick
 * links into the five staff work queues.
 *
 * Every number is computed server-side by Graduation\AdminDashboardController
 * from a single grouped-count query, DemoScope-scoped (a demo admin sees only
 * its own session's counts, a real admin only real counts). This page renders
 * a static snapshot — no Reverb, no charts. Props are snake_case, matching the
 * controller render payload exactly.
 *
 * Each breakdown row already carries its `status` value string, `step`,
 * `label_key` and semantic `color` token, so this page renders them directly —
 * no value-import of the (non-emitting, type-only) generated enum is needed.
 */
interface StatusBreakdownRow {
    status: string;
    step: number;
    label_key: string;
    /** Semantic colour token from GraduationStatus::color(). */
    color: string;
    count: number;
}

interface QueueRow {
    key: string;
    label_key: string;
    count: number;
    /** Resolved URL string (the controller already called route()). */
    route: string;
}

interface Totals {
    students: number;
    graduates: number;
    in_progress: number;
}

interface AdminDashboardProps {
    status_breakdown: StatusBreakdownRow[];
    queues: QueueRow[];
    totals: Totals;
}

/**
 * Static class map for the four semantic colour tokens GraduationStatus::color()
 * can return. Literal strings are required so Tailwind v4 keeps them in the
 * build (no dynamic concatenation). Colour is paired with the always-present
 * textual status label, so it is a redundant cue (WCAG 2.2 AA), never the only
 * signal. Unknown tokens fall back to the neutral border.
 */
const TILE_ACCENT: Record<string, string> = {
    primary: 'border-accent/40 bg-accent/5',
    success: 'border-success/40 bg-success/5',
    warning: 'border-warning/40 bg-warning/5',
    danger: 'border-danger/40 bg-danger/5',
};

export default function AdminDashboard({ status_breakdown, queues, totals }: AdminDashboardProps) {
    const { t } = useLocale();
    const { auth } = usePage<PageProps>().props;
    const empty = totals.students === 0;
    // The academic catalog hub is super_admin-only (SLICE 009); only that role
    // sees the entry point, mirroring the server-side role:super_admin gate.
    const isSuperAdmin = auth?.user?.role === 'super_admin';

    return (
        <>
            <Head title={t('dashboard.admin.title')} />
            <div className="flex min-h-dvh flex-col bg-surface text-fg">
                <header className="flex items-center justify-between border-b border-border px-6 py-4">
                    <span className="text-lg font-semibold text-gradient-primary">
                        {t('app.name')}
                    </span>
                    <nav className="flex items-center gap-2" aria-label="utilities">
                        {isSuperAdmin ? (
                            <Link
                                href="/admin/catalogs"
                                className="inline-flex h-9 items-center rounded-md border border-border px-3 text-sm font-medium text-fg transition-colors hover:bg-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            >
                                {t('catalogs.nav.link')}
                            </Link>
                        ) : null}
                        <LanguageSwitcher />
                    </nav>
                </header>

                {/* Demo bar + exit control while a demo session is active;
                    renders nothing for real staff. */}
                <DemoBanner />

                <main id="main" className="mx-auto w-full max-w-4xl flex-1 px-6 py-8">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {t('dashboard.admin.title')}
                    </h1>
                    <p className="mt-2 text-fg-muted">{t('dashboard.admin.subtitle')}</p>

                    {empty ? (
                        <p className="mt-8 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                            {t('dashboard.admin.empty')}
                        </p>
                    ) : (
                        <>
                            {/* Aggregate totals. */}
                            <dl className="mt-6 grid gap-3 sm:grid-cols-3">
                                <div className="flex flex-col rounded-lg border border-border bg-surface-raised px-4 py-4">
                                    <dt className="text-sm text-fg-muted">
                                        {t('dashboard.admin.totals.students')}
                                    </dt>
                                    <dd className="mt-1 text-2xl font-bold tabular-nums text-fg">
                                        {totals.students}
                                    </dd>
                                </div>
                                <div className="flex flex-col rounded-lg border border-border bg-surface-raised px-4 py-4">
                                    <dt className="text-sm text-fg-muted">
                                        {t('dashboard.admin.totals.in_progress')}
                                    </dt>
                                    <dd className="mt-1 text-2xl font-bold tabular-nums text-fg">
                                        {totals.in_progress}
                                    </dd>
                                </div>
                                <div className="flex flex-col rounded-lg border border-success/40 bg-success/5 px-4 py-4">
                                    <dt className="text-sm text-fg-muted">
                                        {t('dashboard.admin.totals.graduates')}
                                    </dt>
                                    <dd className="mt-1 text-2xl font-bold tabular-nums text-success">
                                        {totals.graduates}
                                    </dd>
                                </div>
                            </dl>

                            {/* 9-state count breakdown, in step() order. */}
                            <section aria-labelledby="breakdown-heading" className="mt-10">
                                <h2
                                    id="breakdown-heading"
                                    className="text-sm font-semibold text-fg"
                                >
                                    {t('dashboard.admin.breakdown_heading')}
                                </h2>
                                <ul className="mt-4 grid gap-3 sm:grid-cols-3">
                                    {status_breakdown.map((row) => (
                                        <li
                                            key={row.status}
                                            className={`flex flex-col rounded-lg border px-4 py-3 ${
                                                TILE_ACCENT[row.color] ??
                                                'border-border bg-surface-raised'
                                            }`}
                                        >
                                            <span className="flex items-baseline justify-between gap-2">
                                                <span className="text-sm font-medium text-fg">
                                                    {t(row.label_key)}
                                                </span>
                                                <span className="text-2xl font-bold tabular-nums text-fg">
                                                    {row.count}
                                                </span>
                                            </span>
                                            <span className="mt-1 text-xs text-fg-muted">
                                                {t('progress.step')
                                                    .replace('{current}', String(row.step))
                                                    .replace('{total}', '9')}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>

                            {/* Quick links into the five staff work queues. */}
                            <section aria-labelledby="queues-heading" className="mt-10">
                                <h2 id="queues-heading" className="text-sm font-semibold text-fg">
                                    {t('dashboard.admin.queues_heading')}
                                </h2>
                                <ul className="mt-4 grid gap-3 sm:grid-cols-2">
                                    {queues.map((queue) => (
                                        <li key={queue.key}>
                                            <Link
                                                href={queue.route}
                                                className="flex items-center justify-between rounded-lg border border-border bg-surface-raised px-5 py-4 transition-colors hover:border-accent focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                            >
                                                <span className="flex flex-col">
                                                    <span className="font-medium text-fg">
                                                        {t(queue.label_key)}
                                                    </span>
                                                    <span className="text-xs text-accent">
                                                        {t('dashboard.admin.queue.open')}
                                                    </span>
                                                </span>
                                                <span
                                                    className="inline-flex h-9 min-w-9 items-center justify-center rounded-full bg-accent px-2.5 text-sm font-bold tabular-nums text-accent-fg"
                                                    aria-label={String(queue.count)}
                                                >
                                                    {queue.count}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        </>
                    )}
                </main>
            </div>
        </>
    );
}
