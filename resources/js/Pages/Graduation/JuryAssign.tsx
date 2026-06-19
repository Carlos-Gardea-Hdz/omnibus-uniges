import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import JuryAssignForm, { type ProfessorOption } from '@/Components/JuryAssignForm';

/**
 * Staff jury-assignment queue (workflow step 6 — PaymentPending → JuryAssigned).
 *
 * Each student in PaymentPending is shown with their registered payment. Staff
 * first verify the payment (which flips `payment_verified`), then assign the
 * jury via the embedded per-row form. Assigning a jury performs the 6→7
 * transition server-side (AssignJuryAction) and removes the student from this
 * PaymentPending queue on the next load.
 *
 * Props are snake_case, matching Graduation\JuryController::index() exactly
 * (the controller render payload is the single source of truth; an Inertia
 * contract test enforces the shape).
 */
interface StudentRow {
    id: number;
    control_number: string;
    full_name: string;
    program_name: string;
    graduation_type_name: string;
    payment_reference: string | null;
    /** ISO-8601 timestamp, or null until the student registers a reference. */
    paid_at: string | null;
    payment_verified: boolean;
}

/** Laravel length-aware paginator shape (only the fields this page reads). */
interface Paginator<T> {
    data: T[];
}

interface JuryAssignProps {
    students: Paginator<StudentRow>;
    professors: ProfessorOption[];
    /** JuryRole values: ['president','secretary','vocal','substitute']. */
    roles: string[];
}

export default function JuryAssign({ students, professors }: JuryAssignProps) {
    const { t, locale } = useLocale();
    const rows = students.data;
    const [verifyingId, setVerifyingId] = useState<number | null>(null);

    const verifyPayment = (student: StudentRow) => {
        setVerifyingId(student.id);
        router.post(
            `/admin/graduation/jury/${student.id}/verify-payment`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setVerifyingId(null),
            },
        );
    };

    const formatPaidAt = (iso: string | null): string | null =>
        iso !== null
            ? new Date(iso).toLocaleString(locale === 'en' ? 'en-US' : 'es-MX', {
                  dateStyle: 'long',
                  timeStyle: 'short',
              })
            : null;

    return (
        <>
            <Head title={t('admin.jury.title')} />
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
                    <h1 className="text-2xl font-bold tracking-tight">{t('admin.jury.title')}</h1>
                    <p className="mt-2 text-fg-muted">{t('admin.jury.subtitle')}</p>

                    {rows.length === 0 ? (
                        <p className="mt-8 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                            {t('admin.jury.empty')}
                        </p>
                    ) : (
                        <ul className="mt-6 flex flex-col gap-6">
                            {rows.map((student) => {
                                const paidAt = formatPaidAt(student.paid_at);

                                return (
                                    <li
                                        key={student.id}
                                        className="rounded-lg border border-border bg-surface-raised p-5"
                                    >
                                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                                            <div>
                                                <h2 className="text-lg font-semibold text-fg">
                                                    {student.full_name}
                                                </h2>
                                                <p className="text-sm text-fg-muted">
                                                    {student.program_name} ·{' '}
                                                    {student.graduation_type_name}
                                                </p>
                                            </div>
                                            <span className="font-mono text-sm text-fg-muted">
                                                {student.control_number}
                                            </span>
                                        </div>

                                        {/* Payment summary + verification action. */}
                                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-surface px-4 py-3">
                                            <div className="min-w-0 text-sm">
                                                {student.payment_reference ? (
                                                    <>
                                                        <p className="text-fg-muted">
                                                            {t('admin.jury.payment.reference')}:{' '}
                                                            <span className="font-medium text-fg">
                                                                {student.payment_reference}
                                                            </span>
                                                        </p>
                                                        {paidAt ? (
                                                            <p className="mt-0.5 text-fg-muted">
                                                                {t('admin.jury.payment.paid_at')}:{' '}
                                                                <span className="text-fg">
                                                                    {paidAt}
                                                                </span>
                                                            </p>
                                                        ) : null}
                                                    </>
                                                ) : (
                                                    <p className="text-fg-muted">
                                                        {t('admin.jury.payment.pending')}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="flex items-center gap-3">
                                                {/* Status pill — colour paired with text (WCAG 1.4.1). */}
                                                <span
                                                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium ${
                                                        student.payment_verified
                                                            ? 'border-success/40 bg-success/10 text-success'
                                                            : 'border-warning/40 bg-warning/10 text-warning'
                                                    }`}
                                                >
                                                    <span
                                                        aria-hidden="true"
                                                        className={`h-1.5 w-1.5 rounded-full ${
                                                            student.payment_verified
                                                                ? 'bg-success'
                                                                : 'bg-warning'
                                                        }`}
                                                    />
                                                    {student.payment_verified
                                                        ? t('admin.jury.payment.verified')
                                                        : t('admin.jury.payment.awaiting')}
                                                </span>

                                                {!student.payment_verified &&
                                                student.payment_reference ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => verifyPayment(student)}
                                                        disabled={verifyingId === student.id}
                                                        className="inline-flex h-9 items-center rounded-md bg-success px-3 text-xs font-medium text-accent-fg transition-colors hover:opacity-90 disabled:opacity-60"
                                                    >
                                                        {verifyingId === student.id
                                                            ? t('admin.jury.verifying')
                                                            : t('admin.jury.verify')}
                                                    </button>
                                                ) : null}
                                            </div>
                                        </div>

                                        <JuryAssignForm
                                            studentId={student.id}
                                            professors={professors}
                                            paymentVerified={student.payment_verified}
                                        />
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </main>
            </div>
        </>
    );
}
