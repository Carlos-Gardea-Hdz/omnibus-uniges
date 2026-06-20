import { useEffect, useRef } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';

/**
 * Accessible confirm dialog for destructive catalog deletes (SLICE 009,
 * CONTRACT §8). Mirrors the Graduation/Review.tsx reject-modal contract:
 * focus-on-open (the cancel button, the safe default), Escape + backdrop click
 * close, role="dialog" / aria-modal / aria-labelledby.
 *
 * It is presentational: the parent owns the delete request (router.delete) and
 * passes `processing` so the confirm button can disable while the request is in
 * flight. Copy flows through useLocale().t.
 */
interface ConfirmDialogProps {
    /** Already-translated dialog title. */
    title: string;
    /** Already-translated body line (e.g. "Delete «X»? This cannot be undone."). */
    message: string;
    processing: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export default function ConfirmDialog({
    title,
    message,
    processing,
    onConfirm,
    onCancel,
}: ConfirmDialogProps) {
    const { t } = useLocale();
    const cancelRef = useRef<HTMLButtonElement>(null);

    // Focus the safe default (cancel) when the dialog opens.
    useEffect(() => {
        cancelRef.current?.focus();
    }, []);

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
            onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    onCancel();
                }
            }}
        >
            {/* Backdrop click closes; the dialog stops propagation. */}
            <button
                type="button"
                aria-label={t('catalogs.cancel')}
                className="absolute inset-0 h-full w-full cursor-default"
                onClick={onCancel}
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-title"
                className="relative w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-xl"
            >
                <h2 id="confirm-title" className="text-lg font-semibold text-fg">
                    {title}
                </h2>
                <p className="mt-1 text-sm text-fg-muted">{message}</p>

                <div className="mt-6 flex justify-end gap-2">
                    <button
                        ref={cancelRef}
                        type="button"
                        onClick={onCancel}
                        className="inline-flex h-11 items-center rounded-md border border-border px-4 font-medium text-fg transition-colors hover:bg-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    >
                        {t('catalogs.cancel')}
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="inline-flex h-11 items-center rounded-md bg-danger px-6 font-medium text-accent-fg transition-colors hover:opacity-90 disabled:opacity-60"
                    >
                        {t('catalogs.delete')}
                    </button>
                </div>
            </div>
        </div>
    );
}
