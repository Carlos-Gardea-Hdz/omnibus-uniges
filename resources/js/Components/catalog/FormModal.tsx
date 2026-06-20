import { useEffect, useRef, type ReactNode } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';

/**
 * Accessible create/edit modal shell for catalog forms (SLICE 009, CONTRACT §8).
 * Mirrors the Graduation/Review.tsx reject-modal contract: the first focusable
 * field is focused on open, Escape + backdrop click close, role="dialog" /
 * aria-modal / aria-labelledby. The parent owns the <form> and its useForm state
 * and passes the labelled fields as children plus the submit handler.
 *
 * `mode` only switches the title + submit label (create vs edit); the fields are
 * identical (one DTO serves store + update). Copy flows through useLocale().t.
 */
interface FormModalProps {
    mode: 'create' | 'edit';
    /** Already-translated dialog title (the catalog name). */
    title: string;
    processing: boolean;
    onSubmit: (event: React.FormEvent) => void;
    onCancel: () => void;
    children: ReactNode;
}

export default function FormModal({
    mode,
    title,
    processing,
    onSubmit,
    onCancel,
    children,
}: FormModalProps) {
    const { t } = useLocale();
    const dialogRef = useRef<HTMLDivElement>(null);

    // Focus the first focusable form control when the dialog opens.
    useEffect(() => {
        const focusable = dialogRef.current?.querySelector<HTMLElement>(
            'input, select, textarea, button',
        );
        focusable?.focus();
    }, []);

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-8"
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
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby="catalog-form-title"
                className="relative max-h-full w-full max-w-lg overflow-y-auto rounded-lg border border-border bg-surface p-6 shadow-xl"
            >
                <h2 id="catalog-form-title" className="text-lg font-semibold text-fg">
                    {mode === 'create' ? t('catalogs.new') : t('catalogs.edit')}
                    <span className="ml-1 text-fg-muted">· {title}</span>
                </h2>

                <form onSubmit={onSubmit} noValidate className="mt-4 flex flex-col gap-4">
                    {children}

                    <div className="mt-2 flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={onCancel}
                            className="inline-flex h-11 items-center rounded-md border border-border px-4 font-medium text-fg transition-colors hover:bg-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            {t('catalogs.cancel')}
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex h-11 items-center rounded-md bg-accent px-6 font-medium text-accent-fg transition-colors hover:opacity-90 disabled:opacity-60"
                        >
                            {t('catalogs.save')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
