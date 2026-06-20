import { Link } from '@inertiajs/react';
import { useLocale } from '@/Contexts/LocaleContext';
import ReportShell from '@/Components/report/ReportShell';

/**
 * Reporting hub (SLICE 008, SPEC §3.7). A pure navigation index: exactly four
 * report cards, each a real Inertia <Link> to its report route (no dead-end).
 *
 * Query-free (Open question E): each report owns its own data; the hub renders
 * only the resolved route URLs and i18n key references the controller already
 * shaped. `title_key` / `description_key` are dotted locale keys resolved here
 * via useLocale().t. Props are snake_case, matching
 * Reporting\ReportingHubController exactly.
 */
interface ReportCard {
    key: string;
    title_key: string;
    description_key: string;
    /** Resolved URL string (the controller already called route()). */
    route: string;
}

interface ReportingIndexProps {
    reports: ReportCard[];
}

export default function ReportingIndex({ reports }: ReportingIndexProps) {
    const { t } = useLocale();

    return (
        <ReportShell
            headTitle={t('reports.hub.title')}
            title={t('reports.hub.title')}
            subtitle={t('reports.hub.subtitle')}
        >
            <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                {reports.map((report) => (
                    <li key={report.key}>
                        <Link
                            href={report.route}
                            className="flex h-full flex-col rounded-lg border border-border bg-surface-raised px-5 py-5 transition-colors hover:border-accent focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            <span className="font-semibold text-fg">{t(report.title_key)}</span>
                            <span className="mt-1 text-sm text-fg-muted">
                                {t(report.description_key)}
                            </span>
                            <span className="mt-4 text-xs font-medium text-accent">
                                {t('reports.hub.open')}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </ReportShell>
    );
}
