import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import CatalogShell from '@/Components/catalog/CatalogShell';
import FormModal from '@/Components/catalog/FormModal';
import TextField from '@/Components/form/TextField';
import FormError from '@/Components/form/FormError';
import type { ReportColumn } from '@/Components/report/ReportTable';

/**
 * Graduation types catalog CRUD (SLICE 009, CONTRACT §4/§7). super_admin-only.
 *
 * Props are snake_case, matching Admin\Catalog\GraduationTypeController exactly:
 *   { graduation_types: {id, code, name, requires_advisor, required_document_ids}[],
 *     required_document_options: {id, name}[] }.
 *
 * The form DTO mirrors App\Domain\Academic\Data\GraduationTypeData (code, name,
 * requires_advisor, required_document_ids[]). The advisor flag is a checkbox; the
 * required documents are a labelled multi-select group of checkboxes (each its
 * own labelled control, so screen readers announce the set). The server
 * re-syncs the pivot exactly. Web validation surfaces as 302 + session errors.
 */
interface GraduationTypeRow {
    id: number;
    code: string;
    name: string;
    requires_advisor: boolean;
    required_document_ids: number[];
}

interface RequiredDocumentOption {
    id: number;
    name: string;
}

interface GraduationTypesProps {
    graduation_types: GraduationTypeRow[];
    required_document_options: RequiredDocumentOption[];
}

type FormValues = {
    code: string;
    name: string;
    requires_advisor: boolean;
    required_document_ids: number[];
};

const BASE_ROUTE = '/admin/catalogs/graduation-types';
const EMPTY: FormValues = {
    code: '',
    name: '',
    requires_advisor: false,
    required_document_ids: [],
};

export default function GraduationTypes({
    graduation_types,
    required_document_options,
}: GraduationTypesProps) {
    const { t } = useLocale();
    const advisorId = useId();
    const [editing, setEditing] = useState<GraduationTypeRow | null>(null);
    const [open, setOpen] = useState(false);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormValues>(EMPTY);

    const openCreate = () => {
        reset();
        clearErrors();
        setData(EMPTY);
        setEditing(null);
        setOpen(true);
    };

    const openEdit = (row: GraduationTypeRow) => {
        clearErrors();
        setData({
            code: row.code,
            name: row.name,
            requires_advisor: row.requires_advisor,
            required_document_ids: [...row.required_document_ids],
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

    const toggleDocument = (id: number, checked: boolean) => {
        const next = checked
            ? [...data.required_document_ids, id]
            : data.required_document_ids.filter((existing) => existing !== id);
        setData('required_document_ids', next);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (editing) {
            put(`${BASE_ROUTE}/${editing.id}`, { preserveScroll: true, onSuccess: close });
        } else {
            post(BASE_ROUTE, { preserveScroll: true, onSuccess: close });
        }
    };

    const columns: ReportColumn<GraduationTypeRow>[] = [
        {
            key: 'code',
            header: t('catalogs.graduation_types.col.code'),
            cell: (row) => row.code,
            cellClassName: 'font-mono',
        },
        {
            key: 'name',
            header: t('catalogs.graduation_types.col.name'),
            cell: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'requires_advisor',
            header: t('catalogs.graduation_types.col.requires_advisor'),
            cell: (row) => (row.requires_advisor ? t('catalogs.yes') : t('catalogs.no')),
        },
        {
            key: 'documents',
            header: t('catalogs.graduation_types.col.documents'),
            align: 'right',
            cell: (row) => row.required_document_ids.length,
            cellClassName: 'tabular-nums',
        },
    ];

    return (
        <>
            <CatalogShell
                titleKey="catalogs.graduation_types.title"
                subtitleKey="catalogs.graduation_types.description"
                baseRoute={BASE_ROUTE}
                columns={columns}
                rows={graduation_types}
                rowLabel={(row) => row.name}
                onNew={openCreate}
                onEdit={openEdit}
            />

            {open ? (
                <FormModal
                    mode={editing ? 'edit' : 'create'}
                    title={t('catalogs.graduation_types.title')}
                    processing={processing}
                    onSubmit={submit}
                    onCancel={close}
                >
                    <TextField
                        label={t('catalogs.graduation_types.col.code')}
                        value={data.code}
                        onChange={(value) => setData('code', value)}
                        error={errors.code}
                        required
                        maxLength={20}
                    />
                    <TextField
                        label={t('catalogs.graduation_types.col.name')}
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                        maxLength={150}
                    />

                    <div className="flex items-center gap-2">
                        <input
                            id={advisorId}
                            type="checkbox"
                            checked={data.requires_advisor}
                            onChange={(event) => setData('requires_advisor', event.target.checked)}
                            className="h-5 w-5 rounded border-border text-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        />
                        <label htmlFor={advisorId} className="text-sm font-medium text-fg">
                            {t('catalogs.graduation_types.requires_advisor')}
                        </label>
                    </div>

                    <fieldset className="flex flex-col gap-2 rounded-md border border-border p-3">
                        <legend className="px-1 text-sm font-medium text-fg">
                            {t('catalogs.graduation_types.required_documents')}
                        </legend>
                        {required_document_options.length === 0 ? (
                            <p className="text-sm text-fg-muted">
                                {t('catalogs.graduation_types.no_documents')}
                            </p>
                        ) : (
                            required_document_options.map((option) => {
                                const checkboxId = `required-document-${option.id}`;
                                return (
                                    <div key={option.id} className="flex items-center gap-2">
                                        <input
                                            id={checkboxId}
                                            type="checkbox"
                                            checked={data.required_document_ids.includes(option.id)}
                                            onChange={(event) =>
                                                toggleDocument(option.id, event.target.checked)
                                            }
                                            className="h-5 w-5 rounded border-border text-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                        />
                                        <label htmlFor={checkboxId} className="text-sm text-fg">
                                            {option.name}
                                        </label>
                                    </div>
                                );
                            })
                        )}
                        <FormError
                            id="required-document-ids-error"
                            message={errors.required_document_ids}
                        />
                    </fieldset>
                </FormModal>
            ) : null}
        </>
    );
}
