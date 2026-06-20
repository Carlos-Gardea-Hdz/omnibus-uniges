import { Link, router } from '@inertiajs/react';
import { useLocale } from '@/Contexts/LocaleContext';
import ReportShell from '@/Components/report/ReportShell';
import ReportTable, { type ReportColumn } from '@/Components/report/ReportTable';

/**
 * Graduates report (SLICE 008, REPORT-02). Lists every Graduated student
 * (DemoScope-scoped server-side) with the full registrar record, filterable by
 * graduation year + program (D-YEAR: the year filter is the GRADUATION year).
 *
 * Filters are plain GET query params: each <select> drives a server-side
 * `router.get` reload that re-runs the scoped query — no client-side filtering,
 * no DTO, never a 422 (D-FILTER). `filters` reflects the applied values;
 * `filter_options` carries the selectable years + the program catalog.
 *
 * Props are snake_case, matching Reporting\GraduatesReportController exactly.
 */
interface GraduateRow {
    id: number;
    control_number: string;
    full_name: string;
    program_name: string;
    graduation_type_name: string;
    diploma_folio: string | null;
    record_book: string | null;
    record_sheet: string | null;
    graduation_date: string | null;
}

interface ProgramOption {
    id: number;
    name: string;
}

interface GraduatesProps {
    graduates: GraduateRow[];
    filters: {
        year: number | null;
        program_id: number | null;
    };
    filter_options: {
        years: number[];
        programs: ProgramOption[];
    };
    total: number;
}

const ROUTE = '/admin/reports/graduates';

export default function Graduates({ graduates, filters, filter_options, total }: GraduatesProps) {
    const { t } = useLocale();

    /**
     * Re-fetch the report with the next filter set, dropping any param the user
     * cleared back to "all" (empty string). Preserves scroll + state so the
     * filter bar keeps focus context.
     */
    const applyFilters = (next: { year?: string; program_id?: string }) => {
        const query: Record<string, string> = {};
        const year = next.year ?? (filters.year !== null ? String(filters.year) : '');
        const programId =
            next.program_id ?? (filters.program_id !== null ? String(filters.program_id) : '');
        if (year !== '') {
            query.year = year;
        }
        if (programId !== '') {
            query.program_id = programId;
        }
        router.get(ROUTE, query, { preserveScroll: true, preserveState: true });
    };

    const hasFilters = filters.year !== null || filters.program_id !== null;

    const columns: ReportColumn<GraduateRow>[] = [
        {
            key: 'control_number',
            header: t('reports.graduates.col.control_number'),
            cell: (row) => row.control_number,
            cellClassName: 'font-mono',
        },
        {
            key: 'name',
            header: t('reports.graduates.col.name'),
            cell: (row) => <span className="font-medium">{row.full_name}</span>,
        },
        {
            key: 'program',
            header: t('reports.graduates.col.program'),
            cell: (row) => row.program_name,
        },
        {
            key: 'type',
            header: t('reports.graduates.col.type'),
            cell: (row) => row.graduation_type_name,
        },
        {
            key: 'folio',
            header: t('reports.graduates.col.folio'),
            cell: (row) => row.diploma_folio ?? '—',
            cellClassName: 'font-mono',
        },
        {
            key: 'book',
            header: t('reports.graduates.col.book'),
            cell: (row) => row.record_book ?? '—',
            cellClassName: 'font-mono',
        },
        {
            key: 'sheet',
            header: t('reports.graduates.col.sheet'),
            cell: (row) => row.record_sheet ?? '—',
            cellClassName: 'font-mono',
        },
        {
            key: 'graduation_date',
            header: t('reports.graduates.col.graduation_date'),
            cell: (row) => row.graduation_date ?? '—',
            cellClassName: 'tabular-nums',
        },
    ];

    return (
        <ReportShell
            headTitle={t('reports.graduates.title')}
            title={t('reports.graduates.title')}
            subtitle={t('reports.graduates.subtitle')}
            backHref="/admin/reports"
        >
            <section aria-labelledby="filters-heading" className="mt-6">
                <h2 id="filters-heading" className="sr-only">
                    {t('reports.graduates.filters_heading')}
                </h2>
                <div className="flex flex-wrap items-end gap-4 rounded-lg border border-border bg-surface-raised px-4 py-4">
                    <div className="flex flex-col">
                        <label
                            htmlFor="filter-year"
                            className="mb-1 text-xs font-medium text-fg-muted"
                        >
                            {t('reports.graduates.filter.year')}
                        </label>
                        <select
                            id="filter-year"
                            value={filters.year ?? ''}
                            onChange={(event) => applyFilters({ year: event.target.value })}
                            className="h-10 rounded-md border border-border bg-surface px-3 text-sm text-fg transition-colors focus-visible:border-accent"
                        >
                            <option value="">{t('reports.graduates.filter.all')}</option>
                            {filter_options.years.map((year) => (
                                <option key={year} value={year}>
                                    {year}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex flex-col">
                        <label
                            htmlFor="filter-program"
                            className="mb-1 text-xs font-medium text-fg-muted"
                        >
                            {t('reports.graduates.filter.program')}
                        </label>
                        <select
                            id="filter-program"
                            value={filters.program_id ?? ''}
                            onChange={(event) => applyFilters({ program_id: event.target.value })}
                            className="h-10 rounded-md border border-border bg-surface px-3 text-sm text-fg transition-colors focus-visible:border-accent"
                        >
                            <option value="">{t('reports.graduates.filter.all')}</option>
                            {filter_options.programs.map((program) => (
                                <option key={program.id} value={program.id}>
                                    {program.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {hasFilters ? (
                        <Link
                            href={ROUTE}
                            className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm font-medium text-fg transition-colors hover:bg-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            {t('reports.graduates.filter.clear')}
                        </Link>
                    ) : null}
                </div>
            </section>

            <p className="mt-6 text-sm text-fg-muted" aria-live="polite">
                {t('reports.graduates.count').replace('{count}', String(total))}
            </p>

            {graduates.length === 0 ? (
                <p className="mt-4 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                    {t('reports.graduates.empty')}
                </p>
            ) : (
                <ReportTable
                    caption={t('reports.graduates.title')}
                    columns={columns}
                    rows={graduates}
                    rowKey={(row) => row.id}
                />
            )}
        </ReportShell>
    );
}
