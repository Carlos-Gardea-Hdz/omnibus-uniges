import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import CatalogShell from '@/Components/catalog/CatalogShell';
import FormModal from '@/Components/catalog/FormModal';
import TextField from '@/Components/form/TextField';
import SelectField, { type SelectOption } from '@/Components/form/SelectField';
import type { ReportColumn } from '@/Components/report/ReportTable';

/**
 * Programs catalog CRUD (SLICE 009, CONTRACT §4/§7). super_admin-only.
 *
 * Props are snake_case, matching Admin\Catalog\ProgramController exactly:
 *   { programs: {id, code, name, department_id, department_name}[],
 *     department_options: {id, name}[] }.
 *
 * The form DTO mirrors App\Domain\Academic\Data\ProgramData (code, name,
 * department_id). Web validation surfaces as 302 + session errors (NEVER 422).
 */
interface ProgramRow {
    id: number;
    code: string;
    name: string;
    department_id: number;
    department_name: string;
}

interface DepartmentOption {
    id: number;
    name: string;
}

interface ProgramsProps {
    programs: ProgramRow[];
    department_options: DepartmentOption[];
}

type FormValues = {
    code: string;
    name: string;
    department_id: string;
};

const BASE_ROUTE = '/admin/catalogs/programs';
const EMPTY: FormValues = { code: '', name: '', department_id: '' };

export default function Programs({ programs, department_options }: ProgramsProps) {
    const { t } = useLocale();
    const [editing, setEditing] = useState<ProgramRow | null>(null);
    const [open, setOpen] = useState(false);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormValues>(EMPTY);

    const departmentOptions: SelectOption[] = department_options.map((option) => ({
        value: option.id,
        label: option.name,
    }));

    const openCreate = () => {
        reset();
        clearErrors();
        setData(EMPTY);
        setEditing(null);
        setOpen(true);
    };

    const openEdit = (row: ProgramRow) => {
        clearErrors();
        setData({ code: row.code, name: row.name, department_id: String(row.department_id) });
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

    const columns: ReportColumn<ProgramRow>[] = [
        {
            key: 'code',
            header: t('catalogs.programs.col.code'),
            cell: (row) => row.code,
            cellClassName: 'font-mono',
        },
        {
            key: 'name',
            header: t('catalogs.programs.col.name'),
            cell: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'department',
            header: t('catalogs.programs.col.department'),
            cell: (row) => row.department_name,
        },
    ];

    return (
        <>
            <CatalogShell
                titleKey="catalogs.programs.title"
                subtitleKey="catalogs.programs.description"
                baseRoute={BASE_ROUTE}
                columns={columns}
                rows={programs}
                rowLabel={(row) => row.name}
                onNew={openCreate}
                onEdit={openEdit}
            />

            {open ? (
                <FormModal
                    mode={editing ? 'edit' : 'create'}
                    title={t('catalogs.programs.title')}
                    processing={processing}
                    onSubmit={submit}
                    onCancel={close}
                >
                    <TextField
                        label={t('catalogs.programs.col.code')}
                        value={data.code}
                        onChange={(value) => setData('code', value)}
                        error={errors.code}
                        required
                        maxLength={20}
                    />
                    <TextField
                        label={t('catalogs.programs.col.name')}
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                        maxLength={150}
                    />
                    <SelectField
                        label={t('catalogs.programs.col.department')}
                        value={data.department_id}
                        onChange={(value) => setData('department_id', value)}
                        options={departmentOptions}
                        error={errors.department_id}
                        required
                        placeholder={t('catalogs.select.placeholder')}
                    />
                </FormModal>
            ) : null}
        </>
    );
}
