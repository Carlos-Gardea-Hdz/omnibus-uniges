import { Link, router } from '@inertiajs/react';
import { useLocale } from '@/Contexts/LocaleContext';
import ReportShell from '@/Components/report/ReportShell';
import ReportTable, { type ReportColumn } from '@/Components/report/ReportTable';

/**
 * Judge certificates report (SLICE 008, CERT-01). Per professor who has served
 * on ≥1 jury, the list of jury assignments they sat — the exact data a printable
 * judge certificate draws from. The four JuryAssignment role columns are
 * inverted into per-professor rows server-side (D-INVERT).
 *
 * EXPORT IS DEFERRED (D-EXPORT-DEFERRED): the screen shows the data only. There
 * is intentionally NO working "Generate certificate" button — only an honest
 * "coming soon" note. The future export slice adds the generator without a
 * re-query.
 *
 * Filterable by professor via a plain GET query param (`router.get`, server-side,
 * no DTO, never 422). Props snake_case, matching
 * Reporting\JudgeCertificatesReportController exactly.
 */
interface AssignmentRow {
    jury_assignment_id: number;
    role: string;
    role_label_key: string;
    student_id: number;
    student_control_number: string;
    student_name: string;
    program_name: string;
    student_status: string;
    ceremony_date: string | null;
    graduation_date: string | null;
}

interface ProfessorBlock {
    professor_id: number;
    professor_name: string;
    assignment_count: number;
    assignments: AssignmentRow[];
}

interface ProfessorOption {
    id: number;
    name: string;
}

interface JudgeCertificatesProps {
    professors: ProfessorBlock[];
    filters: {
        professor_id: number | null;
    };
    filter_options: {
        professors: ProfessorOption[];
    };
}

const ROUTE = '/admin/reports/judge-certificates';

export default function JudgeCertificates({
    professors,
    filters,
    filter_options,
}: JudgeCertificatesProps) {
    const { t } = useLocale();

    const applyProfessor = (value: string) => {
        const query: Record<string, string> = {};
        if (value !== '') {
            query.professor_id = value;
        }
        router.get(ROUTE, query, { preserveScroll: true, preserveState: true });
    };

    const columns: ReportColumn<AssignmentRow>[] = [
        {
            key: 'role',
            header: t('reports.judge_certificates.col.role'),
            // role_label_key resolves to "jury_role.<value>" (reused locale keys).
            cell: (row) => t(row.role_label_key),
            cellClassName: 'font-medium',
        },
        {
            key: 'student',
            header: t('reports.judge_certificates.col.student'),
            cell: (row) => (
                <>
                    <span className="font-medium">{row.student_name}</span>
                    <span className="block font-mono text-xs text-fg-muted">
                        {row.student_control_number}
                    </span>
                </>
            ),
        },
        {
            key: 'program',
            header: t('reports.judge_certificates.col.program'),
            cell: (row) => row.program_name,
        },
        {
            key: 'student_status',
            header: t('reports.judge_certificates.col.student_status'),
            // student_status resolves to "status.<value>" (reused locale keys).
            cell: (row) => t(`status.${row.student_status}`),
        },
        {
            key: 'ceremony_date',
            header: t('reports.judge_certificates.col.ceremony_date'),
            cell: (row) => row.ceremony_date ?? '—',
            cellClassName: 'tabular-nums',
        },
        {
            key: 'graduation_date',
            header: t('reports.judge_certificates.col.graduation_date'),
            cell: (row) => row.graduation_date ?? '—',
            cellClassName: 'tabular-nums',
        },
    ];

    const hasFilter = filters.professor_id !== null;

    return (
        <ReportShell
            headTitle={t('reports.judge_certificates.title')}
            title={t('reports.judge_certificates.title')}
            subtitle={t('reports.judge_certificates.subtitle')}
            backHref="/admin/reports"
        >
            {/* Honest export-deferral note — NO working export button this slice. */}
            <p
                role="note"
                className="mt-6 rounded-md border border-border bg-surface-raised px-4 py-3 text-sm text-fg-muted"
            >
                {t('reports.judge_certificates.export_deferred')}
            </p>

            <section aria-labelledby="judge-filters-heading" className="mt-6">
                <h2 id="judge-filters-heading" className="sr-only">
                    {t('reports.judge_certificates.filter.professor')}
                </h2>
                <div className="flex flex-wrap items-end gap-4 rounded-lg border border-border bg-surface-raised px-4 py-4">
                    <div className="flex flex-col">
                        <label
                            htmlFor="filter-professor"
                            className="mb-1 text-xs font-medium text-fg-muted"
                        >
                            {t('reports.judge_certificates.filter.professor')}
                        </label>
                        <select
                            id="filter-professor"
                            value={filters.professor_id ?? ''}
                            onChange={(event) => applyProfessor(event.target.value)}
                            className="h-10 rounded-md border border-border bg-surface px-3 text-sm text-fg transition-colors focus-visible:border-accent"
                        >
                            <option value="">{t('reports.judge_certificates.filter.all')}</option>
                            {filter_options.professors.map((professor) => (
                                <option key={professor.id} value={professor.id}>
                                    {professor.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {hasFilter ? (
                        <Link
                            href={ROUTE}
                            className="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm font-medium text-fg transition-colors hover:bg-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            {t('reports.judge_certificates.filter.clear')}
                        </Link>
                    ) : null}
                </div>
            </section>

            {professors.length === 0 ? (
                <p className="mt-6 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                    {t('reports.judge_certificates.empty')}
                </p>
            ) : (
                <div className="mt-8 flex flex-col gap-10">
                    {professors.map((professor) => (
                        <section
                            key={professor.professor_id}
                            aria-labelledby={`professor-${professor.professor_id}`}
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h2
                                    id={`professor-${professor.professor_id}`}
                                    className="text-lg font-semibold text-fg"
                                >
                                    {professor.professor_name}
                                </h2>
                                <p className="text-sm text-fg-muted tabular-nums">
                                    {t('reports.judge_certificates.assignment_count').replace(
                                        '{count}',
                                        String(professor.assignment_count),
                                    )}
                                </p>
                            </div>

                            <ReportTable
                                caption={professor.professor_name}
                                columns={columns}
                                rows={professor.assignments}
                                rowKey={(row) => row.jury_assignment_id}
                            />
                        </section>
                    ))}
                </div>
            )}
        </ReportShell>
    );
}
