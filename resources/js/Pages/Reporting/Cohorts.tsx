import { useLocale } from '@/Contexts/LocaleContext';
import ReportShell from '@/Components/report/ReportShell';

/**
 * Cohorts overview report (SLICE 008, REPORT-03). Students grouped by enrollment
 * year (D-YEAR), each cohort split into the full 9-state GraduationStatus
 * breakdown (in_progress vs graduated), so staff see where each generation
 * stands.
 *
 * Each cohort's `by_status` is EXACTLY 9 rows in step() order, every state
 * present (0 if empty) — shaped server-side. Each row already carries its
 * `status` value string, `step`, `label_key` and semantic `color` token, so this
 * page renders them directly (no value-import of the type-only generated enum)
 * via the familiar dashboard count-tile grid + TILE_ACCENT map.
 *
 * Props snake_case, matching Reporting\CohortsReportController exactly.
 */
interface StatusBreakdownRow {
    status: string;
    step: number;
    label_key: string;
    /** Semantic colour token from GraduationStatus::color(). */
    color: string;
    count: number;
}

interface CohortRow {
    cohort_year: number;
    total: number;
    graduated: number;
    in_progress: number;
    by_status: StatusBreakdownRow[];
}

interface CohortsProps {
    cohorts: CohortRow[];
}

/**
 * Static class map for the four semantic colour tokens GraduationStatus::color()
 * can return — VERBATIM from Graduation/AdminDashboard.tsx. Literal strings keep
 * Tailwind v4 from purging them; colour is paired with the always-present status
 * label so it is a redundant cue (WCAG 2.2 AA), never the only signal.
 */
const TILE_ACCENT: Record<string, string> = {
    primary: 'border-accent/40 bg-accent/5',
    success: 'border-success/40 bg-success/5',
    warning: 'border-warning/40 bg-warning/5',
    danger: 'border-danger/40 bg-danger/5',
};

export default function Cohorts({ cohorts }: CohortsProps) {
    const { t } = useLocale();

    return (
        <ReportShell
            headTitle={t('reports.cohorts.title')}
            title={t('reports.cohorts.title')}
            subtitle={t('reports.cohorts.subtitle')}
            backHref="/admin/reports"
        >
            {cohorts.length === 0 ? (
                <p className="mt-8 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                    {t('reports.cohorts.empty')}
                </p>
            ) : (
                <div className="mt-8 flex flex-col gap-10">
                    {cohorts.map((cohort) => (
                        <section
                            key={cohort.cohort_year}
                            aria-labelledby={`cohort-${cohort.cohort_year}`}
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h2
                                    id={`cohort-${cohort.cohort_year}`}
                                    className="text-lg font-semibold text-fg"
                                >
                                    {t('reports.cohorts.cohort_heading').replace(
                                        '{year}',
                                        String(cohort.cohort_year),
                                    )}
                                </h2>
                                <p className="text-sm text-fg-muted tabular-nums">
                                    {t('reports.cohorts.total')}: {cohort.total} ·{' '}
                                    {t('reports.cohorts.graduated')}: {cohort.graduated} ·{' '}
                                    {t('reports.cohorts.in_progress')}: {cohort.in_progress}
                                </p>
                            </div>

                            <ul className="mt-4 grid gap-3 sm:grid-cols-3">
                                {cohort.by_status.map((row) => (
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
                    ))}
                </div>
            )}
        </ReportShell>
    );
}
