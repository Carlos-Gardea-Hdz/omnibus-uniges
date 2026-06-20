import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import CatalogShell from '@/Components/catalog/CatalogShell';
import FormModal from '@/Components/catalog/FormModal';
import TextField from '@/Components/form/TextField';
import FormError from '@/Components/form/FormError';
import type { ReportColumn } from '@/Components/report/ReportTable';

/**
 * Required documents catalog CRUD (SLICE 009, CONTRACT §4/§7). super_admin-only.
 *
 * Props are snake_case, matching Admin\Catalog\RequiredDocumentController exactly:
 *   { required_documents: {id, name, description, allowed_mimes, max_size_kb}[] }.
 *
 * The form DTO mirrors App\Domain\Academic\Data\RequiredDocumentData (name,
 * description?, allowed_mimes, max_size_kb). This catalog has no unique column
 * (its migration omits both SoftDeletes and a unique code), so there is no
 * code/email duplicate path. Web validation surfaces as 302 + session errors.
 */
interface RequiredDocumentRow {
    id: number;
    name: string;
    description: string | null;
    allowed_mimes: string;
    max_size_kb: number;
}

interface RequiredDocumentsProps {
    required_documents: RequiredDocumentRow[];
}

type FormValues = {
    name: string;
    description: string;
    allowed_mimes: string;
    max_size_kb: string;
};

const BASE_ROUTE = '/admin/catalogs/required-documents';
const EMPTY: FormValues = { name: '', description: '', allowed_mimes: '', max_size_kb: '' };

export default function RequiredDocuments({ required_documents }: RequiredDocumentsProps) {
    const { t } = useLocale();
    const [editing, setEditing] = useState<RequiredDocumentRow | null>(null);
    const [open, setOpen] = useState(false);

    const { data, setData, transform, post, put, processing, errors, reset, clearErrors } =
        useForm<FormValues>(EMPTY);

    // Cast the size to an integer and map an empty description to null
    // (the column + DTO are nullable). The server stays authoritative.
    transform((payload) => ({
        ...payload,
        description: payload.description.trim() === '' ? null : payload.description,
        max_size_kb: payload.max_size_kb === '' ? null : Number(payload.max_size_kb),
    }));

    const openCreate = () => {
        reset();
        clearErrors();
        setData(EMPTY);
        setEditing(null);
        setOpen(true);
    };

    const openEdit = (row: RequiredDocumentRow) => {
        clearErrors();
        setData({
            name: row.name,
            description: row.description ?? '',
            allowed_mimes: row.allowed_mimes,
            max_size_kb: String(row.max_size_kb),
        });
        setEditing(row);
        setOpen(true);
    };

    const close = () => {
        setOpen(false);
        setEditing(null);
        reset();
        clearErrors();
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (editing) {
            put(`${BASE_ROUTE}/${editing.id}`, { preserveScroll: true, onSuccess: close });
        } else {
            post(BASE_ROUTE, { preserveScroll: true, onSuccess: close });
        }
    };

    const columns: ReportColumn<RequiredDocumentRow>[] = [
        {
            key: 'name',
            header: t('catalogs.required_documents.col.name'),
            cell: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'allowed_mimes',
            header: t('catalogs.required_documents.col.allowed_mimes'),
            cell: (row) => row.allowed_mimes,
            cellClassName: 'font-mono',
        },
        {
            key: 'max_size_kb',
            header: t('catalogs.required_documents.col.max_size_kb'),
            align: 'right',
            cell: (row) => row.max_size_kb,
            cellClassName: 'tabular-nums',
        },
    ];

    return (
        <>
            <CatalogShell
                titleKey="catalogs.required_documents.title"
                subtitleKey="catalogs.required_documents.description"
                baseRoute={BASE_ROUTE}
                columns={columns}
                rows={required_documents}
                rowLabel={(row) => row.name}
                onNew={openCreate}
                onEdit={openEdit}
            />

            {open ? (
                <FormModal
                    mode={editing ? 'edit' : 'create'}
                    title={t('catalogs.required_documents.title')}
                    processing={processing}
                    onSubmit={submit}
                    onCancel={close}
                >
                    <TextField
                        label={t('catalogs.required_documents.col.name')}
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                        maxLength={150}
                    />
                    <DescriptionField
                        label={t('catalogs.required_documents.col.description')}
                        value={data.description}
                        onChange={(value) => setData('description', value)}
                        error={errors.description}
                    />
                    <TextField
                        label={t('catalogs.required_documents.col.allowed_mimes')}
                        value={data.allowed_mimes}
                        onChange={(value) => setData('allowed_mimes', value)}
                        error={errors.allowed_mimes}
                        hint={t('catalogs.required_documents.hint.allowed_mimes')}
                        required
                        maxLength={255}
                    />
                    <TextField
                        label={t('catalogs.required_documents.col.max_size_kb')}
                        type="number"
                        value={data.max_size_kb}
                        onChange={(value) => setData('max_size_kb', value)}
                        error={errors.max_size_kb}
                        required
                        min={1}
                        max={102400}
                    />
                </FormModal>
            ) : null}
        </>
    );
}

/**
 * Multi-line description field with the same a11y contract as TextField
 * (explicit label, aria-describedby error wiring, aria-invalid). Kept local —
 * the only screen that needs a textarea catalog input.
 */
function DescriptionField({
    label,
    value,
    onChange,
    error,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const errorId = 'required-document-description-error';

    return (
        <div className="flex flex-col">
            <label
                htmlFor="required-document-description"
                className="mb-1 text-sm font-medium text-fg"
            >
                {label}
            </label>
            <textarea
                id="required-document-description"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                rows={3}
                maxLength={1000}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                className="rounded-md border border-border bg-surface-raised px-3 py-2 text-fg transition-colors focus-visible:border-accent aria-[invalid=true]:border-danger"
            />
            <FormError id={errorId} message={error} />
        </div>
    );
}
