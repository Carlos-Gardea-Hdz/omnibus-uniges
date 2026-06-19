import { useForm } from '@inertiajs/react';
import { useLocale } from '@/Contexts/LocaleContext';
import FileUpload from '@/Components/form/FileUpload';

/**
 * Inline upload form for a single required document. Each row owns its own
 * Inertia `useForm`, so the staged file and any per-field server error stay
 * scoped to that document — multiple rows can be open at once without their
 * state bleeding together.
 *
 * The body is multipart (`forceFormData`); validation failures come back as a
 * 302 + session errors (Inertia surfaces them on `errors`), never a 422, so
 * the inline error renders via FileUpload's `aria-describedby` wiring.
 *
 * Mirrors App\Domain\Graduation\Data\UploadDocumentData
 * (`required_document_id`, `file`). Per-row MIME/size limits are enforced
 * server-side in UploadDocumentAction; here they drive only the input hints.
 */
interface DocumentUploadFormProps {
    requiredDocumentId: number;
    /** Label for the file control — "Upload" vs "Replace" copy decided upstream. */
    label: string;
    submitLabel: string;
    acceptMimes: string;
    maxSizeKb: number;
}

type UploadValues = {
    required_document_id: number;
    file: File | null;
};

export default function DocumentUploadForm({
    requiredDocumentId,
    label,
    submitLabel,
    acceptMimes,
    maxSizeKb,
}: DocumentUploadFormProps) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm<UploadValues>({
        required_document_id: requiredDocumentId,
        file: null,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post('/student/documents/upload', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => reset('file'),
        });
    };

    return (
        <form onSubmit={submit} noValidate className="mt-4 border-t border-border pt-4">
            <FileUpload
                label={label}
                fileName={data.file?.name ?? null}
                onSelect={(file) => setData('file', file)}
                acceptMimes={acceptMimes}
                maxSizeKb={maxSizeKb}
                error={errors.file}
                disabled={processing}
            />
            <div className="mt-3 flex items-center gap-2">
                <button
                    type="submit"
                    disabled={processing || !data.file}
                    className="inline-flex h-11 items-center rounded-md bg-accent px-6 font-medium text-accent-fg transition-colors hover:bg-primary-dark disabled:opacity-60"
                >
                    {submitLabel}
                </button>
                {processing ? (
                    <span className="text-sm text-fg-muted" role="status">
                        {t('documents.upload.sending')}
                    </span>
                ) : null}
            </div>
        </form>
    );
}
