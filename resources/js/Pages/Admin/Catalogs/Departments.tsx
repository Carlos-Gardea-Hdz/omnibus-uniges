import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import CatalogShell from '@/Components/catalog/CatalogShell';
import FormModal from '@/Components/catalog/FormModal';
import TextField from '@/Components/form/TextField';
import type { ReportColumn } from '@/Components/report/ReportTable';

/**
 * Departments catalog CRUD (SLICE 009, CONTRACT §4/§7). super_admin-only.
 *
 * Props are snake_case, matching Admin\Catalog\DepartmentController exactly:
 *   { departments: {id, code, name}[] }.
 *
 * Mutations go through the shared CatalogShell (delete) and a per-page FormModal
 * (create/edit) driven by Inertia useForm — web validation surfaces as 302 +
 * session errors (NEVER 422). The form DTO mirrors
 * App\Domain\Academic\Data\DepartmentData (code, name).
 */
interface DepartmentRow {
    id: number;
    code: string;
    name: string;
}

interface DepartmentsProps {
    departments: DepartmentRow[];
}

type FormValues = {
    code: string;
    name: string;
};

const BASE_ROUTE = '/admin/catalogs/departments';
const EMPTY: FormValues = { code: '', name: '' };

export default function Departments({ departments }: DepartmentsProps) {
    const { t } = useLocale();
    const [editing, setEditing] = useState<DepartmentRow | null>(null);
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

    const openEdit = (row: DepartmentRow) => {
        clearErrors();
        setData({ code: row.code, name: row.name });
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

    const columns: ReportColumn<DepartmentRow>[] = [
        {
            key: 'code',
            header: t('catalogs.departments.col.code'),
            cell: (row) => row.code,
            cellClassName: 'font-mono',
        },
        {
            key: 'name',
            header: t('catalogs.departments.col.name'),
            cell: (row) => <span className="font-medium">{row.name}</span>,
        },
    ];

    return (
        <>
            <CatalogShell
                titleKey="catalogs.departments.title"
                subtitleKey="catalogs.departments.description"
                baseRoute={BASE_ROUTE}
                columns={columns}
                rows={departments}
                rowLabel={(row) => row.name}
                onNew={openCreate}
                onEdit={openEdit}
            />

            {open ? (
                <FormModal
                    mode={editing ? 'edit' : 'create'}
                    title={t('catalogs.departments.title')}
                    processing={processing}
                    onSubmit={submit}
                    onCancel={close}
                >
                    <TextField
                        label={t('catalogs.departments.col.code')}
                        value={data.code}
                        onChange={(value) => setData('code', value)}
                        error={errors.code}
                        required
                        maxLength={20}
                    />
                    <TextField
                        label={t('catalogs.departments.col.name')}
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                        maxLength={150}
                    />
                </FormModal>
            ) : null}
        </>
    );
}
