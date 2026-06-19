import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import type { GraduationStatus } from '@/types/generated';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import FormError from '@/Components/form/FormError';

/**
 * Admin review queue for Form B (statuses FormBReview). Each pending student
 * can be approved (advances to AnnexesPending) or rejected with required
 * observations (>= 5 chars, mirroring App\Domain\Graduation\Data\ReviewFormBData).
 *
 * Approve POSTs directly. Reject opens an accessible modal whose textarea is
 * focused on open and which traps Escape-to-close; the form is typed by the
 * Review DTO shape.
 */
interface PendingStudent {
    id: number;
    control_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    program_name: string;
    graduation_type_name: string;
    gpa: string;
    status: GraduationStatus;
    form_b_submitted_at: string | null;
}

/** Laravel length-aware paginator shape (only the fields this page reads). */
interface Paginator<T> {
    data: T[];
}

interface ReviewProps {
    students: Paginator<PendingStudent>;
}

/** Mirrors App\Domain\Graduation\Data\ReviewFormBData. */
type ReviewValues = {
    observations: string;
};

export default function Review({ students }: ReviewProps) {
    const { t } = useLocale();
    const rows = students.data;
    const [rejecting, setRejecting] = useState<PendingStudent | null>(null);
    const [approvingId, setApprovingId] = useState<number | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<ReviewValues>({
        observations: '',
    });

    // Focus the reason field when the reject dialog opens.
    useEffect(() => {
        if (rejecting) {
            textareaRef.current?.focus();
        }
    }, [rejecting]);

    const openReject = (student: PendingStudent) => {
        reset();
        clearErrors();
        setRejecting(student);
    };

    const closeReject = () => {
        setRejecting(null);
        reset();
        clearErrors();
    };

    const approve = (student: PendingStudent) => {
        setApprovingId(student.id);
        router.post(
            `/admin/graduation/${student.id}/approve`,
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
        post(`/admin/graduation/${rejecting.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => closeReject(),
        });
    };

    return (
        <>
            <Head title={t('admin.review.title')} />
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
                    <h1 className="text-2xl font-bold tracking-tight">{t('admin.review.title')}</h1>
                    <p className="mt-2 text-fg-muted">{t('admin.review.subtitle')}</p>

                    {rows.length === 0 ? (
                        <p className="mt-8 rounded-md border border-border bg-surface-raised px-4 py-8 text-center text-fg-muted">
                            {t('admin.review.empty')}
                        </p>
                    ) : (
                        <div className="mt-6 overflow-x-auto rounded-lg border border-border">
                            <table className="w-full border-collapse text-left text-sm">
                                <caption className="sr-only">{t('admin.review.title')}</caption>
                                <thead className="bg-surface-raised text-fg-muted">
                                    <tr>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            {t('admin.review.col.control_number')}
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            {t('admin.review.col.name')}
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            {t('admin.review.col.program')}
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            {t('admin.review.col.gpa')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right font-medium"
                                        >
                                            {t('admin.review.col.actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((student) => (
                                        <tr key={student.id} className="border-t border-border">
                                            <td className="px-4 py-3 font-mono">
                                                {student.control_number}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {student.full_name}
                                                </span>
                                                <span className="block text-xs text-fg-muted">
                                                    {student.graduation_type_name}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">{student.program_name}</td>
                                            <td className="px-4 py-3 font-mono">{student.gpa}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => approve(student)}
                                                        disabled={approvingId === student.id}
                                                        className="inline-flex h-9 items-center rounded-md bg-success px-4 text-sm font-medium text-accent-fg transition-colors hover:opacity-90 disabled:opacity-60"
                                                    >
                                                        {t('admin.review.approve')}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => openReject(student)}
                                                        className="inline-flex h-9 items-center rounded-md border border-danger px-4 text-sm font-medium text-danger transition-colors hover:bg-danger/10"
                                                    >
                                                        {t('admin.review.reject')}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
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
                        aria-label={t('admin.review.cancel')}
                        className="absolute inset-0 h-full w-full cursor-default"
                        onClick={closeReject}
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="reject-title"
                        className="relative w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-xl"
                    >
                        <h2 id="reject-title" className="text-lg font-semibold text-fg">
                            {t('admin.review.reject.title')}
                        </h2>
                        <p className="mt-1 text-sm text-fg-muted">
                            {t('admin.review.reject.subtitle')} {rejecting.full_name}
                        </p>

                        <form onSubmit={submitReject} noValidate className="mt-4">
                            <label
                                htmlFor="observations"
                                className="mb-1 block text-sm font-medium text-fg"
                            >
                                {t('admin.review.observations')}
                                <span aria-hidden="true" className="ml-0.5 text-danger">
                                    *
                                </span>
                            </label>
                            <textarea
                                id="observations"
                                ref={textareaRef}
                                value={data.observations}
                                onChange={(event) => setData('observations', event.target.value)}
                                rows={4}
                                required
                                aria-required
                                aria-invalid={errors.observations ? true : undefined}
                                aria-describedby={
                                    errors.observations ? 'observations-error' : undefined
                                }
                                className="w-full rounded-md border border-border bg-surface-raised px-3 py-2 text-fg transition-colors focus-visible:border-accent aria-[invalid=true]:border-danger"
                            />
                            <FormError id="observations-error" message={errors.observations} />

                            <div className="mt-4 flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={closeReject}
                                    className="inline-flex h-11 items-center rounded-md border border-border px-4 font-medium text-fg transition-colors hover:bg-border"
                                >
                                    {t('admin.review.cancel')}
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex h-11 items-center rounded-md bg-danger px-6 font-medium text-accent-fg transition-colors hover:opacity-90 disabled:opacity-60"
                                >
                                    {t('admin.review.reject.confirm')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </>
    );
}
