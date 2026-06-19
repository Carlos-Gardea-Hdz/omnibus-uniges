import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import type { DocumentStatus } from '@/types/generated';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import DocumentStatusBadge from '@/Components/DocumentStatusBadge';
import FormError from '@/Components/form/FormError';

/**
 * Staff review queue for student documents (workflow step 5 — Annex III).
 *
 * Each student in `AnnexIiiPending` is shown with the required documents they
 * have submitted. A document can be approved (which advances the student to
 * step 6 once every document is approved) or rejected with a required reason
 * (>= 5 chars, mirroring App\Domain\Graduation\Data\ReviewDocumentData).
 *
 * Approve POSTs directly. Reject opens an accessible dialog whose textarea is
 * focused on open and which closes on Escape / backdrop click. A download link
 * (temporary signed URL) is offered when the file is available.
 *
 * Props are snake_case, matching Graduation\DocumentReviewController::index()
 * exactly (an Inertia contract test enforces the shape).
 */
interface DocumentRow {
    id: number;
    name: string;
    /** DocumentStatus value. */
    status: string;
    original_filename: string | null;
    rejection_reason: string | null;
    download_url: string | null;
}

interface PendingStudent {
    id: number;
    control_number: string;
    full_name: string;
    program_name: string;
    graduation_type_name: string;
    documents: DocumentRow[];
}

/** Laravel length-aware paginator shape (only the fields this page reads). */
interface Paginator<T> {
    data: T[];
}

interface DocumentReviewProps {
    students: Paginator<PendingStudent>;
}

/** Mirrors App\Domain\Graduation\Data\ReviewDocumentData. */
type RejectValues = {
    rejection_reason: string;
};

export default function DocumentReview({ students }: DocumentReviewProps) {
    const { t } = useLocale();
    const rows = students.data;
    const [rejecting, setRejecting] = useState<DocumentRow | null>(null);
    const [approvingId, setApprovingId] = useState<number | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<RejectValues>({
        rejection_reason: '',
    });

    // Focus the reason field when the reject dialog opens.
    useEffect(() => {
        if (rejecting) {
            textareaRef.current?.focus();
        }
    }, [rejecting]);

    const openReject = (document: DocumentRow) => {
        reset();
        clearErrors();
        setRejecting(document);
    };

    const closeReject = () => {
        setRejecting(null);
        reset();
        clearErrors();
    };

    const approve = (document: DocumentRow) => {
        setApprovingId(document.id);
        router.post(
            `/admin/graduation/documents/${document.id}/approve`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setApprovingId(null),
            },
        );
    };

    const submitReject = (event: React.FormEvent) => {
        event.preventDefault();
        if (!rejecting) {
            return;
        }
        post(`/admin/graduation/documents/${rejecting.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => closeReject(),
        });
    };

    return (
        <>
            <Head title={t('admin.documents.title')} />
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
                        {t('admin.documents.title')}
                    </h1>
                    <p className="mt-2 text-fg-muted">{t('admin.documents.subtitle')}</p>

                    {rows.length === 0 ? (
                        <p className="mt-8 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                            {t('admin.documents.empty')}
                        </p>
                    ) : (
                        <ul className="mt-6 flex flex-col gap-6">
                            {rows.map((student) => (
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

                                    <div className="mt-4 overflow-x-auto rounded-md border border-border">
                                        <table className="w-full border-collapse text-left text-sm">
                                            <caption className="sr-only">
                                                {t('admin.documents.col.document')} —{' '}
                                                {student.full_name}
                                            </caption>
                                            <thead className="bg-surface text-fg-muted">
                                                <tr>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-2 font-medium"
                                                    >
                                                        {t('admin.documents.col.document')}
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-2 font-medium"
                                                    >
                                                        {t('admin.documents.col.status')}
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-2 text-right font-medium"
                                                    >
                                                        {t('admin.documents.col.actions')}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {student.documents.map((document) => {
                                                    const isApproved =
                                                        document.status ===
                                                        ('approved' as DocumentStatus);
                                                    const isPending =
                                                        document.status ===
                                                        ('pending' as DocumentStatus);
                                                    // Only an uploaded/rejected doc can be acted on;
                                                    // pending has no file yet, approved is locked.
                                                    const canReview = !isApproved && !isPending;

                                                    return (
                                                        <tr
                                                            key={document.id}
                                                            className="border-t border-border align-top"
                                                        >
                                                            <td className="px-4 py-3">
                                                                <span className="font-medium text-fg">
                                                                    {document.name}
                                                                </span>
                                                                {document.original_filename ? (
                                                                    <span className="block text-xs text-fg-muted">
                                                                        {
                                                                            document.original_filename
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                                {document.rejection_reason ? (
                                                                    <span className="mt-1 block text-xs text-danger">
                                                                        {document.rejection_reason}
                                                                    </span>
                                                                ) : null}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <DocumentStatusBadge
                                                                    status={document.status}
                                                                />
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <div className="flex flex-wrap justify-end gap-2">
                                                                    {document.download_url ? (
                                                                        <a
                                                                            href={
                                                                                document.download_url
                                                                            }
                                                                            className="inline-flex h-9 items-center rounded-md border border-border px-3 text-xs font-medium text-fg transition-colors hover:bg-border focus-visible:border-accent"
                                                                        >
                                                                            {t(
                                                                                'admin.documents.download',
                                                                            )}
                                                                        </a>
                                                                    ) : null}
                                                                    {canReview ? (
                                                                        <>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    approve(
                                                                                        document,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    approvingId ===
                                                                                    document.id
                                                                                }
                                                                                className="inline-flex h-9 items-center rounded-md bg-success px-3 text-xs font-medium text-accent-fg transition-colors hover:opacity-90 disabled:opacity-60"
                                                                            >
                                                                                {t(
                                                                                    'admin.documents.approve',
                                                                                )}
                                                                            </button>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    openReject(
                                                                                        document,
                                                                                    )
                                                                                }
                                                                                className="inline-flex h-9 items-center rounded-md border border-danger px-3 text-xs font-medium text-danger transition-colors hover:bg-danger/10"
                                                                            >
                                                                                {t(
                                                                                    'admin.documents.reject',
                                                                                )}
                                                                            </button>
                                                                        </>
                                                                    ) : null}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </main>
            </div>

            {rejecting ? (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            closeReject();
                        }
                    }}
                >
                    {/* Backdrop click closes; the dialog stops propagation. */}
                    <button
                        type="button"
                        aria-label={t('admin.documents.cancel')}
                        className="absolute inset-0 h-full w-full cursor-default"
                        onClick={closeReject}
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="reject-doc-title"
                        className="relative w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-xl"
                    >
                        <h2 id="reject-doc-title" className="text-lg font-semibold text-fg">
                            {t('admin.documents.reject.title')}
                        </h2>
                        <p className="mt-1 text-sm text-fg-muted">
                            {t('admin.documents.reject.subtitle')} {rejecting.name}
                        </p>

                        <form onSubmit={submitReject} noValidate className="mt-4">
                            <label
                                htmlFor="rejection_reason"
                                className="mb-1 block text-sm font-medium text-fg"
                            >
                                {t('admin.documents.reason')}
                                <span aria-hidden="true" className="ml-0.5 text-danger">
                                    *
                                </span>
                            </label>
                            <textarea
                                id="rejection_reason"
                                ref={textareaRef}
                                value={data.rejection_reason}
                                onChange={(event) =>
                                    setData('rejection_reason', event.target.value)
                                }
                                rows={4}
                                required
                                aria-required
                                aria-invalid={errors.rejection_reason ? true : undefined}
                                aria-describedby={
                                    errors.rejection_reason ? 'rejection_reason-error' : undefined
                                }
                                className="w-full rounded-md border border-border bg-surface-raised px-3 py-2 text-fg transition-colors focus-visible:border-accent aria-[invalid=true]:border-danger"
                            />
                            <FormError
                                id="rejection_reason-error"
                                message={errors.rejection_reason}
                            />

                            <div className="mt-4 flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={closeReject}
                                    className="inline-flex h-11 items-center rounded-md border border-border px-4 font-medium text-fg transition-colors hover:bg-border"
                                >
                                    {t('admin.documents.cancel')}
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex h-11 items-center rounded-md bg-danger px-6 font-medium text-accent-fg transition-colors hover:opacity-90 disabled:opacity-60"
                                >
                                    {t('admin.documents.reject.confirm')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </>
    );
}
