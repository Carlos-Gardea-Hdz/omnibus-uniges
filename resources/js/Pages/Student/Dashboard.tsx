import { Head, Link } from '@inertiajs/react';
import { useLocale } from '@/Contexts/LocaleContext';
import type { GraduationStatus } from '@/types/generated';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import GraduationProgress from '@/Components/GraduationProgress';
import DemoBanner from '@/Components/DemoBanner';

/**
 * Read-only student overview home (SLICE 007, SPEC §7). It restates the 9-step
 * progress for the signed-in student and surfaces a single prominent call to
 * action that points at whatever screen is actionable for the current state —
 * or a graduation celebration once the pipeline is complete.
 *
 * Every value is computed server-side by Student\DashboardController::index();
 * this page only renders. Props are snake_case, matching that controller's
 * render payload exactly (single source of truth). The `status` value is the
 * enum *value string* (never the enum object), so it is compared against raw
 * string literals cast to the type-only generated `GraduationStatus`.
 */
interface DashboardCta {
    /** Resolved URL string (the controller already called route()). */
    route: string;
    /** i18n key for the button label. */
    label_key: string;
}

interface DashboardProps {
    student_id: number | null;
    control_number: string | null;
    full_name: string | null;
    status: string | null;
    step: number | null;
    total_steps: number;
    is_graduated: boolean;
    cta: DashboardCta | null;
    form_b_observations: string | null;
}

export default function Dashboard({
    control_number,
    full_name,
    status,
    step,
    total_steps,
    is_graduated,
    cta,
    form_b_observations,
}: DashboardProps) {
    const { t } = useLocale();

    // Compared as a raw string cast to the type per the type-only generated-enum
    // rule (no runtime value import that would break the vite build).
    const rejected = status === ('form_b_rejected' as GraduationStatus);
    const currentStep = step ?? 0;

    return (
        <>
            <Head title={t('dashboard.student.title')} />
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
                    renders nothing for real students. */}
                <DemoBanner />

                <main id="main" className="mx-auto w-full max-w-2xl flex-1 px-6 py-8">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {t('dashboard.student.title')}
                    </h1>
                    <p className="mt-2 text-fg-muted">{t('dashboard.student.subtitle')}</p>

                    {full_name || control_number ? (
                        <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                            <div className="flex flex-col rounded-md border border-border bg-surface-raised px-4 py-3">
                                <dt className="text-fg-muted">{t('status.field.name')}</dt>
                                <dd className="font-medium text-fg">{full_name ?? '—'}</dd>
                            </div>
                            <div className="flex flex-col rounded-md border border-border bg-surface-raised px-4 py-3">
                                <dt className="text-fg-muted">
                                    {t('status.field.control_number')}
                                </dt>
                                <dd className="font-mono font-medium text-fg">
                                    {control_number ?? '—'}
                                </dd>
                            </div>
                        </dl>
                    ) : null}

                    {status ? (
                        <section
                            aria-labelledby="progress-heading"
                            className="mt-8 rounded-lg border border-border bg-surface-raised p-6"
                        >
                            <h2
                                id="progress-heading"
                                className="mb-4 text-sm font-semibold text-fg"
                            >
                                {t('dashboard.student.progress_heading')}
                            </h2>
                            <GraduationProgress status={status as GraduationStatus} />

                            {/* Semantic 9-step list. Done / current / pending state is
                                carried by text (not colour alone) for WCAG 2.2 AA.
                                The current row also names the live status. */}
                            <ol className="mt-6 grid gap-1.5">
                                {Array.from({ length: total_steps }, (_, index) => {
                                    const stepNumber = index + 1;
                                    const done = stepNumber < currentStep;
                                    const current = stepNumber === currentStep;
                                    const stateKey = done
                                        ? 'dashboard.student.step.done'
                                        : current
                                          ? 'dashboard.student.step.current'
                                          : 'dashboard.student.step.pending';
                                    return (
                                        <li
                                            key={stepNumber}
                                            aria-current={current ? 'step' : undefined}
                                            className={`flex items-center justify-between rounded-md border px-3 py-2 text-sm ${
                                                current
                                                    ? 'border-accent bg-accent/10 font-medium text-fg'
                                                    : 'border-border bg-surface text-fg-muted'
                                            }`}
                                        >
                                            <span>
                                                <span className="font-mono">{stepNumber}.</span>
                                                {current && status ? (
                                                    <span className="ml-1.5">
                                                        {t(`status.${status}`)}
                                                    </span>
                                                ) : null}
                                            </span>
                                            <span className="ml-3 shrink-0 text-xs uppercase tracking-wide">
                                                {t(stateKey)}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ol>
                        </section>
                    ) : null}

                    {/* Form B rejection feedback, surfaced only in the rejected state. */}
                    {rejected && form_b_observations ? (
                        <section
                            aria-labelledby="observations-heading"
                            className="mt-6 rounded-lg border border-danger/40 bg-danger/10 px-6 py-5"
                        >
                            <h2
                                id="observations-heading"
                                className="text-sm font-semibold text-danger"
                            >
                                {t('dashboard.student.observations_heading')}
                            </h2>
                            <p className="mt-2 whitespace-pre-line text-sm text-fg">
                                {form_b_observations}
                            </p>
                        </section>
                    ) : null}

                    {/* Next-step CTA, or a terminal celebration when cta === null. */}
                    {is_graduated || cta === null ? (
                        <section
                            aria-labelledby="graduated-heading"
                            className="mt-8 rounded-lg border border-success/40 bg-success/10 px-6 py-6 text-center"
                        >
                            <h2
                                id="graduated-heading"
                                className="text-lg font-semibold text-success"
                            >
                                {t('dashboard.student.graduated.title')}
                            </h2>
                            <p className="mt-1 text-sm text-fg-muted">
                                {t('dashboard.student.graduated.body')}
                            </p>
                        </section>
                    ) : (
                        <section
                            aria-labelledby="next-step-heading"
                            className="mt-8 rounded-lg border border-border bg-surface-raised p-6"
                        >
                            <h2 id="next-step-heading" className="text-sm font-semibold text-fg">
                                {t('dashboard.student.next_step_heading')}
                            </h2>
                            <Link
                                href={cta.route}
                                className="mt-4 inline-flex h-11 items-center rounded-md bg-accent px-6 text-sm font-medium text-accent-fg transition-colors hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            >
                                {t(cta.label_key)}
                            </Link>
                        </section>
                    )}
                </main>
            </div>
        </>
    );
}
