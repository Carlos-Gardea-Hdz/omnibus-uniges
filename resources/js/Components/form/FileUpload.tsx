import { useId, useRef } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import FormError from '@/Components/form/FormError';

/**
 * Accessible single-file picker for a required document.
 *
 * - explicit <label htmlFor> association + visible focus ring
 * - `accept` hint derived from the document's allowed MIME list
 * - `aria-describedby` wires the size/format hint and the inline error
 * - the chosen file name is surfaced as text (not colour) and announced
 *   politely, so a screen-reader user knows a file is staged
 * - ≥ 44px touch target via h-11 on the styled control
 *
 * The component is fully controlled by the parent (Inertia `useForm`): it
 * reports the picked File up and renders the name the parent holds, so a
 * server validation error (302 + session error → Inertia `errors`) can be
 * shown inline without losing the selection context.
 */
interface FileUploadProps {
    label: string;
    /** Currently staged file name, surfaced as text. Null when none chosen. */
    fileName: string | null;
    onSelect: (file: File | null) => void;
    /** Comma-separated MIME list, used both as the `accept` and the hint. */
    acceptMimes: string;
    maxSizeKb: number;
    error?: string;
    disabled?: boolean;
}

export default function FileUpload({
    label,
    fileName,
    onSelect,
    acceptMimes,
    maxSizeKb,
    error,
    disabled = false,
}: FileUploadProps) {
    const { t } = useLocale();
    const inputId = useId();
    const errorId = `${inputId}-error`;
    const hintId = `${inputId}-hint`;
    const fileId = `${inputId}-file`;
    const inputRef = useRef<HTMLInputElement>(null);

    const describedBy =
        [hintId, fileName ? fileId : null, error ? errorId : null].filter(Boolean).join(' ') ||
        undefined;

    const maxMb = Math.round((maxSizeKb / 1024) * 10) / 10;
    const sizeHint = t('documents.upload.size_hint').replace('{max}', String(maxMb));

    return (
        <div className="flex flex-col">
            <label htmlFor={inputId} className="mb-1 text-sm font-medium text-fg">
                {label}
            </label>

            <span id={hintId} className="mb-1 text-xs text-fg-muted">
                {sizeHint}
            </span>

            <input
                ref={inputRef}
                id={inputId}
                type="file"
                accept={acceptMimes}
                disabled={disabled}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                onChange={(event) => onSelect(event.target.files?.[0] ?? null)}
                className="block w-full cursor-pointer rounded-md border border-border bg-surface-raised text-sm text-fg transition-colors file:mr-3 file:h-11 file:cursor-pointer file:border-0 file:bg-border file:px-4 file:text-sm file:font-medium file:text-fg hover:file:bg-border/70 focus-visible:border-accent disabled:cursor-not-allowed disabled:opacity-60 aria-[invalid=true]:border-danger"
            />

            {fileName ? (
                <span id={fileId} className="mt-1 text-xs text-fg-muted" aria-live="polite">
                    {t('documents.upload.selected')}: <span className="font-medium text-fg">{fileName}</span>
                </span>
            ) : null}

            <FormError id={errorId} message={error} />
        </div>
    );
}
