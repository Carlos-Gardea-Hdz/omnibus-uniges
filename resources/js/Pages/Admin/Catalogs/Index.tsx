import { Link } from '@inertiajs/react';
import { useLocale } from '@/Contexts/LocaleContext';
import ReportShell from '@/Components/report/ReportShell';

/**
 * Academic catalog hub (SLICE 009, CONTRACT §4/§7). A pure navigation index:
 * six catalog cards, each a real Inertia <Link> to its CRUD index route (no
 * dead-end), with a live row count badge the controller computed.
 *
 * Reuses the Reporting chrome (ReportShell): header + LanguageSwitcher, the
 * shared <DemoBanner /> (so demo-mode isolation is visible — though a demo
 * session can never reach this super_admin-only page), one #main, one <h1>.
 *
 * `title_key` / `description_key` are dotted locale keys resolved here via
 * useLocale().t. Props are snake_case, matching Admin\Catalog\CatalogHubController
 * exactly: { catalogs: {key, title_key, description_key, count, route}[6] }.
 */
interface CatalogCard {
    key: string;
    title_key: string;
    description_key: string;
    count: number;
    /** Resolved URL string (the controller already called route()). */
    route: string;
}

interface CatalogIndexProps {
    catalogs: CatalogCard[];
}

export default function CatalogIndex({ catalogs }: CatalogIndexProps) {
    const { t } = useLocale();

    return (
        <ReportShell
            headTitle={t('catalogs.hub.title')}
            title={t('catalogs.hub.title')}
            subtitle={t('catalogs.hub.subtitle')}
        >
            <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                {catalogs.map((catalog) => (
                    <li key={catalog.key}>
                        <Link
                            href={catalog.route}
                            className="flex h-full flex-col rounded-lg border border-border bg-surface-raised px-5 py-5 transition-colors hover:border-accent focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            <span className="flex items-baseline justify-between gap-2">
                                <span className="font-semibold text-fg">
                                    {t(catalog.title_key)}
                                </span>
                                <span
                                    className="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-accent px-2 text-sm font-bold tabular-nums text-accent-fg"
                                    aria-label={String(catalog.count)}
                                >
                                    {catalog.count}
                                </span>
                            </span>
                            <span className="mt-1 text-sm text-fg-muted">
                                {t(catalog.description_key)}
                            </span>
                            <span className="mt-4 text-xs font-medium text-accent">
                                {t('catalogs.hub.open')}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </ReportShell>
    );
}
