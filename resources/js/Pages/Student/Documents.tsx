import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import { createEcho } from '@/echo';
import type { DocumentStatus, GraduationStatus } from '@/types/generated';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import DocumentStatusBadge from '@/Components/DocumentStatusBadge';
import DocumentUploadForm from '@/Components/DocumentUploadForm';

/**
 * Student document stage (workflow step 5 — Annex III / required documents).
 *
 * Lists every required document for the student's graduation type with its
 * per-document status, lets the student upload (or replace) a file, download a
 * stored copy via a temporary signed URL, and read any rejection reason. Each
 * uploadable row carries its own inline upload form (own Inertia useForm), so
 * several can be in flight at once without their state colliding.
 *
 * The page subscribes to the student's own private channel and listens for:
 *   - `.document.status.changed` → a single document's status moved (or another
 *     was approved), so we reload `documents` to reflect the new state
 *   - `.graduation.step.completed` → the 5→6 advance once every document is
 *     approved, so the workflow `status` updates live and the page guides the
 *     student onward
 *
 * Props are snake_case, matching Student\DocumentController::index() exactly
 * (the controller render payload is the single source of truth; an Inertia
 * contract test enforces the shape).
 */
interface DocumentRow {
    id: number;
    required_document_id: number;
    name: string;
    description: string | null;
    /** DocumentStatus value ('pending' | 'uploaded' | 'approved' | 'rejected'). */
    status: string;
    original_filename: string | null;
    rejection_reason: string | null;
    uploaded_at: string | null;
    /** Temporary signed download URL, or null while the row is still pending. */
    download_url: string | null;
    allowed_mimes: string;
    max_size_kb: number;
}

interface DocumentsProps {
    student_id: number | null;
    /** GraduationStatus value (e.g. 'annexes_pending', 'annex_iii_pending'). */
    status: string;
    documents: DocumentRow[];
}

/** Payload from StudentStatusChanged::broadcastWith() (the 5→6 advance). */
interface StepCompletedPayload {
    completed_step: string;
    next_step: string | null;
    progress: number;
    message: string;
}

export default function Documents({ student_id, status, documents }: DocumentsProps) {
    const { t } = useLocale();
    const [currentStatus, setCurrentStatus] = useState<string>(status);
    const [live, setLive] = useState(false);

    const refresh = useCallback(() => {
        // Pull only the live-changing props; preserve scroll + form state.
        router.reload({ only: ['documents', 'status'] });
    }, []);

    // Keep the live status in sync when Inertia replaces the page props.
    useEffect(() => {
        setCurrentStatus(status);
    }, [status]);

    useEffect(() => {
        if (student_id === null) {
            return;
        }
        const echo = createEcho();
        const channel = echo.private(`student.${student_id}`);

        channel
            .subscribed(() => setLive(true))
            .listen('.document.status.changed', () => {
                // A document was uploaded/approved/rejected — reload to reflect it.
                refresh();
            })
            .listen('.graduation.step.completed', (payload: StepCompletedPayload) => {
                // The 5→6 advance: every document approved. Update the workflow
                // status live and pull fresh document rows.
                setCurrentStatus(
                    (payload.next_step as GraduationStatus | null) ??
                        ('payment_pending' as GraduationStatus),
                );
                refresh();
            });

        return () => {
            channel.stopListening('.document.status.changed');
            channel.stopListening('.graduation.step.completed');
            echo.leave(`student.${student_id}`);
            echo.disconnect();
        };
    }, [student_id, refresh]);

    // The 5→6 advance moves the workflow off the document stage. The string
    // comparison casts the generated value type per the type-only enum rule.
    const stageActive = currentStatus === ('annex_iii_pending' as GraduationStatus);

    return (
        <>
            <Head title={t('documents.page.title')} />
            <div className="flex min-h-dvh flex-col bg-surface text-fg">
                <header className="flex items-center justify-between border-b border-border px-6 py-4">
                    <span className="text-lg font-semibold text-gradient-primary">
                        {t('app.name')}
                    </span>
                    <nav className="flex items-center gap-2" aria-label="utilities">
                        <LanguageSwitcher />
                    </nav>
                </header>

                <main id="main" className="mx-auto w-full max-w-3xl flex-1 px-6 py-8">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                {t('documents.page.title')}
                            </h1>
                            <p className="mt-2 text-fg-muted">{t('documents.page.subtitle')}</p>
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

                    {!stageActive ? (
                        <p
                            role="status"
                            className="mt-6 rounded-md border border-success/40 bg-success/10 px-4 py-3 text-sm font-medium text-success"
                        >
                            {t('documents.stage.complete')}
                        </p>
                    ) : null}

                    {documents.length === 0 ? (
                        <p className="mt-8 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                            {t('documents.empty')}
                        </p>
                    ) : (
                        <ul className="mt-6 flex flex-col gap-4">
                            {documents.map((row) => {
                                const isRejected = row.status === ('rejected' as DocumentStatus);
                                const isApproved = row.status === ('approved' as DocumentStatus);
                                // Approved rows are locked; everything else can (re)upload
                                // while the stage is active.
                                const canUpload = stageActive && !isApproved;

                                return (
                                    <li
                                        key={row.id}
                                        className="rounded-lg border border-border bg-surface-raised p-5"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <h2 className="font-semibold text-fg">
                                                    {row.name}
                                                </h2>
                                                {row.description ? (
                                                    <p className="mt-0.5 text-sm text-fg-muted">
                                                        {row.description}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <DocumentStatusBadge status={row.status} />
                                        </div>

                                        {row.original_filename ? (
                                            <p className="mt-3 text-sm text-fg-muted">
                                                {t('documents.current_file')}:{' '}
                                                <span className="font-medium text-fg">
                                                    {row.original_filename}
                                                </span>
                                            </p>
                                        ) : null}

                                        {isRejected && row.rejection_reason ? (
                                            <div
                                                role="alert"
                                                className="mt-3 rounded-md border border-danger/40 bg-danger/10 px-3 py-2"
                                            >
                                                <p className="text-xs font-semibold text-danger">
                                                    {t('documents.rejected.title')}
                                                </p>
                                                <p className="mt-0.5 text-sm text-fg">
                                                    {row.rejection_reason}
                                                </p>
                                            </div>
                                        ) : null}

                                        <div className="mt-4 flex flex-wrap items-center gap-2">
                                            {row.download_url ? (
                                                <a
                                                    href={row.download_url}
                                                    className="inline-flex h-9 items-center rounded-md border border-border px-4 text-sm font-medium text-fg transition-colors hover:bg-border focus-visible:border-accent"
                                                >
                                                    {t('documents.download')}
                                                </a>
                                            ) : null}

                                            {isApproved ? (
                                                <span className="text-sm font-medium text-success">
                                                    {t('documents.locked')}
                                                </span>
                                            ) : null}
                                        </div>

                                        {canUpload ? (
                                            <DocumentUploadForm
                                                requiredDocumentId={row.required_document_id}
                                                label={t('documents.upload.field')}
                                                submitLabel={
                                                    row.original_filename
                                                        ? t('documents.replace')
                                                        : t('documents.upload.confirm')
                                                }
                                                acceptMimes={row.allowed_mimes}
                                                maxSizeKb={row.max_size_kb}
                                            />
                                        ) : null}
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
