import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import { createEcho } from '@/echo';
import type { GraduationStatus } from '@/types/generated';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import GraduationProgress from '@/Components/GraduationProgress';
import DemoBanner from '@/Components/DemoBanner';

/**
 * Live graduation-status screen. Shows the 9-step progress bar and listens on
 * the student's private channel for `graduation.step.completed` (broadcast as
 * `.graduation.step.completed`) so the bar advances in real time without a
 * reload. The Echo subscription is created in an effect and torn down on
 * unmount, leaving the channel cleanly.
 *
 * Props are snake_case, matching StatusController::index() and the backend DTO
 * conventions (single source of truth: the controller render payload).
 */
interface StatusProps {
    student_id: number;
    status: GraduationStatus;
    control_number: string;
    full_name: string;
    form_b_observations: string | null;
}

/**
 * Payload shape from StudentStatusChanged::broadcastWith(). `next_step` is null
 * once the terminal `graduated` state is reached; `progress` is the backend's
 * round(targetStep / 9 * 100) percentage that drives the bar fill directly.
 */
interface StepCompletedPayload {
    completed_step: string;
    next_step: string | null;
    progress: number;
    message: string;
}

export default function Status({ student_id, status, control_number, full_name }: StatusProps) {
    const { t } = useLocale();
    const [currentStatus, setCurrentStatus] = useState<GraduationStatus>(status);
    const [liveProgress, setLiveProgress] = useState<number | null>(null);
    const [lastMessageKey, setLastMessageKey] = useState<string | null>(null);
    const [live, setLive] = useState(false);

    useEffect(() => {
        const echo = createEcho();
        const channel = echo.private(`student.${student_id}`);

        channel
            .subscribed(() => setLive(true))
            .listen('.graduation.step.completed', (payload: StepCompletedPayload) => {
                // A null next_step means the terminal `graduated` state is reached.
                setCurrentStatus(
                    (payload.next_step as GraduationStatus | null) ??
                        ('graduated' as GraduationStatus),
                );
                // Drive the bar from the backend's own percentage — never recompute.
                setLiveProgress(payload.progress);
                setLastMessageKey(payload.message);
            });

        return () => {
            // Stop listening, then leave the private channel entirely.
            channel.stopListening('.graduation.step.completed');
            echo.leave(`student.${student_id}`);
            echo.disconnect();
        };
    }, [student_id]);

    return (
        <>
            <Head title={t('status.page.title')} />
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
                    renders nothing for real students (student.status is the
                    landing target for the sustentante demo presets). */}
                <DemoBanner />

                <main id="main" className="mx-auto w-full max-w-2xl flex-1 px-6 py-8">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-bold tracking-tight">
                            {t('status.page.title')}
                        </h1>
                        <span
                            className="inline-flex items-center gap-1.5 text-xs text-fg-muted"
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

                    <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                        <div className="flex flex-col rounded-md border border-border bg-surface-raised px-4 py-3">
                            <dt className="text-fg-muted">{t('status.field.name')}</dt>
                            <dd className="font-medium text-fg">{full_name}</dd>
                        </div>
                        <div className="flex flex-col rounded-md border border-border bg-surface-raised px-4 py-3">
                            <dt className="text-fg-muted">{t('status.field.control_number')}</dt>
                            <dd className="font-mono font-medium text-fg">{control_number}</dd>
                        </div>
                    </dl>

                    <section className="mt-8 rounded-lg border border-border bg-surface-raised p-6">
                        <GraduationProgress
                            status={currentStatus}
                            progress={liveProgress ?? undefined}
                        />
                    </section>

                    {/* Terminal celebration once the 9-state pipeline completes.
                        Compared as a raw string cast to the type per the type-only
                        generated-enum rule (no runtime value import). */}
                    {currentStatus === ('graduated' as GraduationStatus) ? (
                        <section
                            aria-labelledby="graduated-heading"
                            className="mt-6 rounded-lg border border-success/40 bg-success/10 px-6 py-5 text-center"
                        >
                            <h2
                                id="graduated-heading"
                                className="text-lg font-semibold text-success"
                            >
                                {t('status.graduated.title')}
                            </h2>
                            <p className="mt-1 text-sm text-fg-muted">
                                {t('status.graduated.body')}
                            </p>
                        </section>
                    ) : null}

                    {/* Polite live region: announces each pushed transition. */}
                    <p className="sr-only" aria-live="polite">
                        {lastMessageKey ? t(lastMessageKey) : ''}
                    </p>

                    {lastMessageKey ? (
                        <p
                            role="status"
                            className="mt-4 rounded-md border border-success/40 bg-success/10 px-4 py-3 text-sm font-medium text-success"
                        >
                            {t('status.updated')} — {t(lastMessageKey)}
                        </p>
                    ) : null}
                </main>
            </div>
        </>
    );
}
