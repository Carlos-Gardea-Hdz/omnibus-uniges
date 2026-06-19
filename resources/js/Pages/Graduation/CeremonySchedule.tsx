import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import type { PageProps } from '@/types';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import CeremonyScheduleForm from '@/Components/CeremonyScheduleForm';

/**
 * Staff ceremony queue (workflow steps 7→8 and 8→9 — completing the 9-state
 * pipeline). Two queues are shown:
 *
 *   - `scheduling`: students in JuryAssigned awaiting a ceremony date. Each row
 *     embeds a CeremonyScheduleForm that performs the 7→8 transition server-side
 *     (ScheduleCeremonyAction) and drops the student from this queue on reload.
 *   - `graduating`: students in CeremonyScheduled awaiting graduation. The
 *     "Mark as graduated" button performs the terminal 8→9 transition
 *     (MarkAsGraduatedAction, which mints the diploma folio). It is disabled
 *     until the server-computed `can_graduate` is true (the ceremony date has
 *     passed); the server re-checks regardless (CERE-02 guard).
 *
 * Props are snake_case, matching Graduation\CeremonyController::index() exactly
 * (the controller render payload is the single source of truth; an Inertia
 * contract test enforces the shape). The generated GraduationStatus enum is
 * imported *type-only* — values are compared as raw strings cast to the type,
 * never value-imported (that would break the Vite build).
 */
interface SchedulingRow {
    id: number;
    control_number: string;
    full_name: string;
    program_name: string;
    graduation_type_name: string;
}

interface GraduatingRow {
    id: number;
    control_number: string;
    full_name: string;
    /** ISO-8601 timestamp, or null until a ceremony is scheduled. */
    ceremony_date: string | null;
    ceremony_location: string | null;
    /** Server-computed: ceremony_date set AND not in the future. */
    can_graduate: boolean;
}

/** Laravel length-aware paginator shape (only the fields this page reads). */
interface Paginator<T> {
    data: T[];
}

interface CeremonyScheduleProps {
    scheduling: Paginator<SchedulingRow>;
    graduating: Paginator<GraduatingRow>;
}

export default function CeremonySchedule({ scheduling, graduating }: CeremonyScheduleProps) {
    const { t, locale } = useLocale();
    const { flash } = usePage<PageProps>().props;
    const schedulingRows = scheduling.data;
    const graduatingRows = graduating.data;
    const [graduatingId, setGraduatingId] = useState<number | null>(null);

    const graduate = (student: GraduatingRow) => {
        if (!student.can_graduate) {
            return;
        }
        setGraduatingId(student.id);
        router.post(
            `/admin/graduation/ceremony/${student.id}/graduate`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setGraduatingId(null),
            },
        );
    };

    const formatDate = (iso: string | null): string | null =>
        iso !== null
            ? new Date(iso).toLocaleString(locale === 'en' ? 'en-US' : 'es-MX', {
                  dateStyle: 'long',
                  timeStyle: 'short',
              })
            : null;

    return (
        <>
            <Head title={t('admin.ceremony.title')} />
            <div className="flex min-h-dvh flex-col bg-surface text-fg">
                <header className="flex items-center justify-between border-b border-border px-6 py-4">
                    <span className="text-lg font-semibold text-gradient-primary">
                        {t('app.name')}
                    </span>
                    <nav className="flex items-center gap-2" aria-label="utilities">
                        <LanguageSwitcher />
                    </nav>
                </header>

                <main id="main" className="mx-auto w-full max-w-4xl flex-1 px-6 py-8">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {t('admin.ceremony.title')}
                    </h1>
                    <p className="mt-2 text-fg-muted">{t('admin.ceremony.subtitle')}</p>

                    {flash.success ? (
                        <p
                            role="status"
                            className="mt-6 rounded-md border border-success/40 bg-success/10 px-4 py-3 text-sm font-medium text-success"
                        >
                            {flash.success}
                        </p>
                    ) : null}

                    {/* Queue 1 — schedule the ceremony (7→8). */}
                    <section aria-labelledby="scheduling-heading" className="mt-8">
                        <h2
                            id="scheduling-heading"
                            className="text-lg font-semibold tracking-tight"
                        >
                            {t('admin.ceremony.scheduling.title')}
                        </h2>

                        {schedulingRows.length === 0 ? (
                            <p className="mt-4 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                                {t('admin.ceremony.scheduling.empty')}
                            </p>
                        ) : (
                            <ul className="mt-4 flex flex-col gap-6">
                                {schedulingRows.map((student) => (
                                    <li
                                        key={student.id}
                                        className="rounded-lg border border-border bg-surface-raised p-5"
                                    >
                                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                                            <div>
                                                <h3 className="text-lg font-semibold text-fg">
                                                    {student.full_name}
                                                </h3>
                                                <p className="text-sm text-fg-muted">
                                                    {student.program_name} ·{' '}
                                                    {student.graduation_type_name}
                                                </p>
                                            </div>
                                            <span className="font-mono text-sm text-fg-muted">
                                                {student.control_number}
                                            </span>
                                        </div>

                                        <CeremonyScheduleForm studentId={student.id} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    {/* Queue 2 — graduate (8→9, terminal). */}
                    <section aria-labelledby="graduating-heading" className="mt-12">
                        <h2
                            id="graduating-heading"
                            className="text-lg font-semibold tracking-tight"
                        >
                            {t('admin.ceremony.graduating.title')}
                        </h2>

                        {graduatingRows.length === 0 ? (
                            <p className="mt-4 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                                {t('admin.ceremony.graduating.empty')}
                            </p>
                        ) : (
                            <ul className="mt-4 flex flex-col gap-4">
                                {graduatingRows.map((student) => {
                                    const ceremonyAt = formatDate(student.ceremony_date);

                                    return (
                                        <li
                                            key={student.id}
                                            className="rounded-lg border border-border bg-surface-raised p-5"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <h3 className="text-lg font-semibold text-fg">
                                                        {student.full_name}
                                                    </h3>
                                                    <p className="font-mono text-sm text-fg-muted">
                                                        {student.control_number}
                                                    </p>
                                                    {ceremonyAt ? (
                                                        <p className="mt-2 text-sm text-fg-muted">
                                                            {t('admin.ceremony.col.ceremony_date')}:{' '}
                                                            <span className="font-medium text-fg">
                                                                {ceremonyAt}
                                                            </span>
                                                        </p>
                                                    ) : null}
                                                    {student.ceremony_location ? (
                                                        <p className="mt-0.5 text-sm text-fg-muted">
                                                            {t('admin.ceremony.field.location')}:{' '}
                                                            <span className="font-medium text-fg">
                                                                {student.ceremony_location}
                                                            </span>
                                                        </p>
                                                    ) : null}
                                                </div>

                                                <div className="flex flex-col items-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => graduate(student)}
                                                        disabled={
                                                            !student.can_graduate ||
                                                            graduatingId === student.id
                                                        }
                                                        className="inline-flex h-11 items-center rounded-md bg-accent px-6 font-medium text-accent-fg transition-colors hover:bg-primary-dark disabled:opacity-60"
                                                    >
                                                        {graduatingId === student.id
                                                            ? t('form.sending')
                                                            : t('admin.ceremony.graduate')}
                                                    </button>
                                                    {!student.can_graduate ? (
                                                        <p className="text-xs text-fg-muted">
                                                            {t('admin.ceremony.guard.not_passed')}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </section>
                </main>
            </div>
        </>
    );
}
