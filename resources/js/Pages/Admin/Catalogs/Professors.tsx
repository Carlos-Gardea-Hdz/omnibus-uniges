import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import CatalogShell from '@/Components/catalog/CatalogShell';
import FormModal from '@/Components/catalog/FormModal';
import TextField from '@/Components/form/TextField';
import type { ReportColumn } from '@/Components/report/ReportTable';

/**
 * Professors catalog CRUD (SLICE 009, CONTRACT §4/§7). super_admin-only.
 *
 * Props are snake_case, matching Admin\Catalog\ProfessorController exactly:
 *   { professors: {id, first_name, last_name, mother_last_name, email, full_name}[] }.
 *
 * The form DTO mirrors App\Domain\Academic\Data\ProfessorData (first_name,
 * last_name, mother_last_name?, email). Web validation surfaces as 302 + session
 * errors (NEVER 422); a duplicate email on update surfaces as an `email` error
 * (UpdateProfessorAction::assertEmailAvailable).
 */
interface ProfessorRow {
    id: number;
    first_name: string;
    last_name: string;
    mother_last_name: string | null;
    email: string;
    full_name: string;
}

interface ProfessorsProps {
    professors: ProfessorRow[];
}

type FormValues = {
    first_name: string;
    last_name: string;
    mother_last_name: string;
    email: string;
};

const BASE_ROUTE = '/admin/catalogs/professors';
const EMPTY: FormValues = { first_name: '', last_name: '', mother_last_name: '', email: '' };

export default function Professors({ professors }: ProfessorsProps) {
    const { t } = useLocale();
    const [editing, setEditing] = useState<ProfessorRow | null>(null);
    const [open, setOpen] = useState(false);

    const { data, setData, transform, post, put, processing, errors, reset, clearErrors } =
        useForm<FormValues>(EMPTY);

    // An empty mother_last_name maps to null (the column + DTO are nullable).
    transform((payload) => ({
        ...payload,
        mother_last_name: payload.mother_last_name.trim() === '' ? null : payload.mother_last_name,
    }));

    const openCreate = () => {
        reset();
        clearErrors();
        setData(EMPTY);
        setEditing(null);
        setOpen(true);
    };

    const openEdit = (row: ProfessorRow) => {
        clearErrors();
        setData({
            first_name: row.first_name,
            last_name: row.last_name,
            mother_last_name: row.mother_last_name ?? '',
            email: row.email,
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

    const columns: ReportColumn<ProfessorRow>[] = [
        {
            key: 'name',
            header: t('catalogs.professors.col.name'),
            cell: (row) => <span className="font-medium">{row.full_name}</span>,
        },
        {
            key: 'email',
            header: t('catalogs.professors.col.email'),
            cell: (row) => row.email,
            cellClassName: 'font-mono',
        },
    ];

    return (
        <>
            <CatalogShell
                titleKey="catalogs.professors.title"
                subtitleKey="catalogs.professors.description"
                baseRoute={BASE_ROUTE}
                columns={columns}
                rows={professors}
                rowLabel={(row) => row.full_name}
                onNew={openCreate}
                onEdit={openEdit}
            />

            {open ? (
                <FormModal
                    mode={editing ? 'edit' : 'create'}
                    title={t('catalogs.professors.title')}
                    processing={processing}
                    onSubmit={submit}
                    onCancel={close}
                >
                    <TextField
                        label={t('catalogs.professors.col.first_name')}
                        value={data.first_name}
                        onChange={(value) => setData('first_name', value)}
                        error={errors.first_name}
                        required
                        maxLength={100}
                    />
                    <TextField
                        label={t('catalogs.professors.col.last_name')}
                        value={data.last_name}
                        onChange={(value) => setData('last_name', value)}
                        error={errors.last_name}
                        required
                        maxLength={100}
                    />
                    <TextField
                        label={t('catalogs.professors.col.mother_last_name')}
                        value={data.mother_last_name}
                        onChange={(value) => setData('mother_last_name', value)}
                        error={errors.mother_last_name}
                        maxLength={100}
                    />
                    <TextField
                        label={t('catalogs.professors.col.email')}
                        type="email"
                        value={data.email}
                        onChange={(value) => setData('email', value)}
                        error={errors.email}
                        required
                        maxLength={255}
                    />
                </FormModal>
            ) : null}
        </>
    );
}
