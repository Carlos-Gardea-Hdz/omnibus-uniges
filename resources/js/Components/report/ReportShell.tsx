import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import DemoBanner from '@/Components/DemoBanner';

/**
 * Shared page chrome for every Reporting screen (SLICE 008). Mirrors the
 * Graduation/AdminDashboard.tsx chrome VERBATIM — header with the app name +
 * LanguageSwitcher, the shared <DemoBanner /> (so demo-mode isolation is visible
 * across every report), a single #main landmark, one <h1>, and a "back to hub"
 * <Link> so no report screen is a dead-end (WCAG 2.2 AA: real links, one main,
 * one h1).
 *
 * The hub itself renders without the back link (it IS the hub) by omitting
 * `backHref`. All copy flows through useLocale().t — the title/subtitle are
 * already-resolved strings (the caller passes t(...) values), so this component
 * stays purely structural.
 */
interface ReportShellProps {
    /** Document <title> (already translated by the caller). */
    headTitle: string;
    /** The <h1> text (already translated). */
    title: string;
    /** The lead paragraph under the <h1> (already translated). */
    subtitle: string;
    /** Resolved URL of the reporting hub; when set, a "back to reports" link renders. */
    backHref?: string;
    children: ReactNode;
}

export default function ReportShell({
    headTitle,
    title,
    subtitle,
    backHref,
    children,
}: ReportShellProps) {
    const { t } = useLocale();

    return (
        <>
            <Head title={headTitle} />
            <div className="flex min-h-dvh flex-col bg-surface text-fg">
                <header className="flex items-center justify-between border-b border-border px-6 py-4">
                    <span className="text-lg font-semibold text-gradient-primary">
                        {t('app.name')}
                    </span>
                    <nav className="flex items-center gap-2" aria-label="utilities">
                        <LanguageSwitcher />
                    </nav>
                </header>

                {/* Demo bar + exit control while a demo session is active;
                    renders nothing for real staff. */}
                <DemoBanner />

                <main id="main" className="mx-auto w-full max-w-5xl flex-1 px-6 py-8">
                    {backHref ? (
                        <Link
                            href={backHref}
                            className="inline-flex items-center gap-1 text-sm text-accent transition-colors hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            <span aria-hidden="true">&larr;</span>
                            {t('reports.back_to_hub')}
                        </Link>
                    ) : null}

                    <h1 className={`text-2xl font-bold tracking-tight ${backHref ? 'mt-3' : ''}`}>
                        {title}
                    </h1>
                    <p className="mt-2 text-fg-muted">{subtitle}</p>

                    {children}
                </main>
            </div>
        </>
    );
}
