import { useLocale } from '@/Contexts/LocaleContext';
import ReportShell from '@/Components/report/ReportShell';
import ReportTable, { type ReportColumn } from '@/Components/report/ReportTable';
import PercentageBar from '@/Components/report/PercentageBar';

/**
 * Terminal-efficiency report (SLICE 008, REPORT-01). The institution's headline
 * KPI: graduates / total as an INTEGER percent (D-RATE-INT), by enrollment-year
 * cohort and by program, plus an overall figure.
 *
 * Every `rate` is a server-computed int 0..100 (round(graduates/total*100),
 * total==0 → 0) — rendered as `N%` text + a redundant PercentageBar (colour is
 * never the only cue). `graduates`/`total` ship alongside so the page shows
 * "4 / 10 (40%)" without re-deriving. Props snake_case, matching
 * Reporting\TerminalEfficiencyReportController exactly.
 */
interface CohortRate {
    cohort_year: number;
    total: number;
    graduates: number;
    rate: number;
}

interface ProgramRate {
    program_id: number;
    program_name: string;
    total: number;
    graduates: number;
    rate: number;
}

interface OverallRate {
    total: number;
    graduates: number;
    rate: number;
}

interface TerminalEfficiencyProps {
    by_cohort: CohortRate[];
    by_program: ProgramRate[];
    overall: OverallRate;
}

export default function TerminalEfficiency({
    by_cohort,
    by_program,
    overall,
}: TerminalEfficiencyProps) {
    const { t } = useLocale();

    /** "graduates / total" ratio text, shared by both tables. */
    const ratio = (graduates: number, total: number): string => `${graduates} / ${total}`;

    const cohortColumns: ReportColumn<CohortRate>[] = [
        {
            key: 'cohort',
            header: t('reports.terminal_efficiency.col.cohort'),
            cell: (row) => row.cohort_year,
            cellClassName: 'font-medium tabular-nums',
        },
        {
            key: 'ratio',
            header: t('reports.terminal_efficiency.col.graduates'),
            cell: (row) => ratio(row.graduates, row.total),
            align: 'right',
            cellClassName: 'tabular-nums',
        },
        {
            key: 'rate',
            header: t('reports.terminal_efficiency.col.rate'),
            cell: (row) => (
                <PercentageBar rate={row.rate} label={String(row.cohort_year)} />
            ),
            cellClassName: 'min-w-48',
        },
    ];

    const programColumns: ReportColumn<ProgramRate>[] = [
        {
            key: 'program',
            header: t('reports.terminal_efficiency.col.program'),
            cell: (row) => <span className="font-medium">{row.program_name}</span>,
        },
        {
            key: 'ratio',
            header: t('reports.terminal_efficiency.col.graduates'),
            cell: (row) => ratio(row.graduates, row.total),
            align: 'right',
            cellClassName: 'tabular-nums',
        },
        {
            key: 'rate',
            header: t('reports.terminal_efficiency.col.rate'),
            cell: (row) => <PercentageBar rate={row.rate} label={row.program_name} />,
            cellClassName: 'min-w-48',
        },
    ];

    const empty = overall.total === 0;

    return (
        <ReportShell
            headTitle={t('reports.terminal_efficiency.title')}
            title={t('reports.terminal_efficiency.title')}
            subtitle={t('reports.terminal_efficiency.subtitle')}
            backHref="/admin/reports"
        >
            {empty ? (
                <p className="mt-8 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                    {t('reports.terminal_efficiency.empty')}
                </p>
            ) : (
                <>
                    {/* Overall headline KPI. */}
                    <section aria-labelledby="overall-heading" className="mt-8">
                        <h2 id="overall-heading" className="text-sm font-semibold text-fg">
                            {t('reports.terminal_efficiency.overall_heading')}
                        </h2>
                        <div className="mt-3 flex flex-col gap-4 rounded-lg border border-accent/40 bg-accent/5 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-col">
                                <span className="text-4xl font-bold tabular-nums text-fg">
                                    {overall.rate}%
                                </span>
                                <span className="mt-1 text-sm text-fg-muted">
                                    {t('reports.terminal_efficiency.col.graduates')}:{' '}
                                    <span className="tabular-nums">
                                        {ratio(overall.graduates, overall.total)}
                                    </span>
                                </span>
                            </div>
                            <div className="sm:w-64">
                                <PercentageBar
                                    rate={overall.rate}
                                    label={t('reports.terminal_efficiency.overall_heading')}
                                />
                            </div>
                        </div>
                    </section>

                    {/* By enrollment-year cohort. */}
                    <section aria-labelledby="by-cohort-heading" className="mt-10">
                        <h2 id="by-cohort-heading" className="text-sm font-semibold text-fg">
                            {t('reports.terminal_efficiency.by_cohort_heading')}
                        </h2>
                        {by_cohort.length === 0 ? (
                            <p className="mt-3 text-sm text-fg-muted">
                                {t('reports.terminal_efficiency.empty')}
                            </p>
                        ) : (
                            <ReportTable
                                caption={t('reports.terminal_efficiency.by_cohort_heading')}
                                columns={cohortColumns}
                                rows={by_cohort}
                                rowKey={(row) => row.cohort_year}
                            />
                        )}
                    </section>

                    {/* By program. */}
                    <section aria-labelledby="by-program-heading" className="mt-10">
                        <h2 id="by-program-heading" className="text-sm font-semibold text-fg">
                            {t('reports.terminal_efficiency.by_program_heading')}
                        </h2>
                        {by_program.length === 0 ? (
                            <p className="mt-3 text-sm text-fg-muted">
                                {t('reports.terminal_efficiency.empty')}
                            </p>
                        ) : (
                            <ReportTable
                                caption={t('reports.terminal_efficiency.by_program_heading')}
                                columns={programColumns}
                                rows={by_program}
                                rowKey={(row) => row.program_id}
                            />
                        )}
                    </section>
                </>
            )}
        </ReportShell>
    );
}
