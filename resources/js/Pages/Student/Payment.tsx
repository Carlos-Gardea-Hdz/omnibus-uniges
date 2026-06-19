import { Head, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import { createEcho } from '@/echo';
import type { GraduationStatus } from '@/types/generated';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import TextField from '@/Components/form/TextField';

/**
 * Student payment stage (workflow step 6 — PaymentPending).
 *
 * The student registers a payment reference (mirroring
 * App\Domain\Jury\Data\SubmitPaymentData — a string, max 50). The page then
 * shows whether staff have verified that payment yet; verification and the
 * subsequent 6→7 jury assignment are staff actions, so this screen only
 * submits the reference and reflects state.
 *
 * It subscribes to the student's own private channel and listens for:
 *   - `.graduation.step.completed` → the 6→7 advance, so the page reflects that
 *     the workflow has moved on (registration is now closed)
 *   - `.jury.assigned` → the JuryAssigned broadcast that accompanies the same
 *     transition, used as a redundant live signal that the stage is complete
 *   - `.ceremony.scheduled` / `.student.graduated` → the later 7→8 and terminal
 *     8→9 transitions; redundant live signals that keep the page reflecting an
 *     advanced workflow even if the student lingers on this screen
 *
 * Props are snake_case, matching Student\PaymentController::index() exactly
 * (the controller render payload is the single source of truth; an Inertia
 * contract test enforces the shape).
 */
interface PaymentProps {
    student_id: number | null;
    /** GraduationStatus value (e.g. 'payment_pending', 'jury_assigned'). */
    status: string;
    payment_reference: string | null;
    /** ISO-8601 timestamp, or null until a reference is registered. */
    paid_at: string | null;
    payment_verified: boolean;
}

/** Mirrors App\Domain\Jury\Data\SubmitPaymentData. */
type PaymentValues = {
    payment_reference: string;
};

export default function Payment({
    student_id,
    status,
    payment_reference,
    paid_at,
    payment_verified,
}: PaymentProps) {
    const { t, locale } = useLocale();
    const [currentStatus, setCurrentStatus] = useState<string>(status);
    const [advanced, setAdvanced] = useState(false);
    const [live, setLive] = useState(false);

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm<PaymentValues>({
        payment_reference: payment_reference ?? '',
    });

    // Keep the live status in sync when Inertia replaces the page props.
    useEffect(() => {
        setCurrentStatus(status);
    }, [status]);

    const onStepCompleted = useCallback(() => {
        // The 6→7 advance moves the workflow off the payment stage.
        setCurrentStatus('jury_assigned' as GraduationStatus);
        setAdvanced(true);
    }, []);

    useEffect(() => {
        if (student_id === null) {
            return;
        }
        const echo = createEcho();
        const channel = echo.private(`student.${student_id}`);

        channel
            .subscribed(() => setLive(true))
            // The 6→7 advance: jury assigned. Reflect that registration is closed.
            .listen('.graduation.step.completed', onStepCompleted)
            // Redundant signal carried by the JuryAssigned broadcast.
            .listen('.jury.assigned', onStepCompleted)
            // Later advances (7→8, terminal 8→9): the workflow has moved well
            // past payment, so reflect the advanced state regardless.
            .listen('.ceremony.scheduled', onStepCompleted)
            .listen('.student.graduated', onStepCompleted);

        return () => {
            channel.stopListening('.graduation.step.completed');
            channel.stopListening('.jury.assigned');
            channel.stopListening('.ceremony.scheduled');
            channel.stopListening('.student.graduated');
            echo.leave(`student.${student_id}`);
            echo.disconnect();
        };
    }, [student_id, onStepCompleted]);

    // The payment stage is only active while the workflow sits at step 6. The
    // string comparison casts the generated value type per the type-only enum
    // rule (generated.d.ts carries types, not runtime values).
    const stageActive = currentStatus === ('payment_pending' as GraduationStatus);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post('/student/payment', { preserveScroll: true });
    };

    const formattedPaidAt =
        paid_at !== null
            ? new Date(paid_at).toLocaleString(locale === 'en' ? 'en-US' : 'es-MX', {
                  dateStyle: 'long',
                  timeStyle: 'short',
              })
            : null;

    return (
        <>
            <Head title={t('payment.page.title')} />
            <div className="flex min-h-dvh flex-col bg-surface text-fg">
                <header className="flex items-center justify-between border-b border-border px-6 py-4">
                    <span className="text-lg font-semibold text-gradient-primary">
                        {t('app.name')}
                    </span>
                    <nav className="flex items-center gap-2" aria-label="utilities">
                        <LanguageSwitcher />
                    </nav>
                </header>

                <main id="main" className="mx-auto w-full max-w-2xl flex-1 px-6 py-8">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                {t('payment.page.title')}
                            </h1>
                            <p className="mt-2 text-fg-muted">{t('payment.page.subtitle')}</p>
                        </div>
                        <span
                            className="inline-flex shrink-0 items-center gap-1.5 text-xs text-fg-muted"
                            aria-live="polite"
                        >
                            <span
                                aria-hidden="true"
                                className={`h-2 w-2 rounded-full ${
                                    live ? 'animate-pulse-status bg-success' : 'bg-border'
                                }`}
                            />
                            {live ? t('status.live.on') : t('status.live.off')}
                        </span>
                    </div>

                    {advanced || !stageActive ? (
                        <p
                            role="status"
                            className="mt-6 rounded-md border border-success/40 bg-success/10 px-4 py-3 text-sm font-medium text-success"
                        >
                            {t('payment.status.advanced')}
                        </p>
                    ) : null}

                    {recentlySuccessful ? (
                        <p
                            role="status"
                            className="mt-6 rounded-md border border-success/40 bg-success/10 px-4 py-3 text-sm font-medium text-success"
                        >
                            {t('payment.status.registered')}
                        </p>
                    ) : null}

                    {/* Verification state — colour is paired with text (WCAG 1.4.1). */}
                    <div
                        role="status"
                        className={`mt-6 flex items-center gap-2 rounded-md border px-4 py-3 text-sm font-medium ${
                            payment_verified
                                ? 'border-success/40 bg-success/10 text-success'
                                : 'border-warning/40 bg-warning/10 text-warning'
                        }`}
                    >
                        <span
                            aria-hidden="true"
                            className={`h-2 w-2 rounded-full ${
                                payment_verified ? 'bg-success' : 'bg-warning'
                            }`}
                        />
                        {payment_verified
                            ? t('payment.status.verified')
                            : t('payment.status.awaiting')}
                    </div>

                    {formattedPaidAt ? (
                        <p className="mt-3 text-sm text-fg-muted">
                            {t('payment.paid_at')}:{' '}
                            <span className="font-medium text-fg">{formattedPaidAt}</span>
                        </p>
                    ) : null}

                    {stageActive && !advanced ? (
                        <form onSubmit={submit} noValidate className="mt-8 flex flex-col gap-6">
                            <TextField
                                label={t('payment.field.reference')}
                                value={data.payment_reference}
                                onChange={(value) => setData('payment_reference', value)}
                                error={errors.payment_reference}
                                required
                                maxLength={50}
                                hint={t('payment.field.reference.hint')}
                                autoComplete="off"
                            />

                            <div className="flex items-center gap-3">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex h-11 items-center rounded-md bg-accent px-6 font-medium text-accent-fg transition-colors hover:bg-primary-dark disabled:opacity-60"
                                >
                                    {t('payment.submit')}
                                </button>
                                {processing ? (
                                    <span className="text-sm text-fg-muted" role="status">
                                        {t('payment.submit.sending')}
                                    </span>
                                ) : null}
                            </div>
                        </form>
                    ) : null}
                </main>
            </div>
        </>
    );
}
