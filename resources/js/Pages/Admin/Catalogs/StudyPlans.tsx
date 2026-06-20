import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import CatalogShell from '@/Components/catalog/CatalogShell';
import FormModal from '@/Components/catalog/FormModal';
import TextField from '@/Components/form/TextField';
import SelectField, { type SelectOption } from '@/Components/form/SelectField';
import type { ReportColumn } from '@/Components/report/ReportTable';

/**
 * Study plans catalog CRUD (SLICE 009, CONTRACT §4/§7). super_admin-only.
 *
 * Props are snake_case, matching Admin\Catalog\StudyPlanController exactly:
 *   { study_plans: {id, code, name, program_id, program_name}[],
 *     program_options: {id, name}[] }.
 *
 * The form DTO mirrors App\Domain\Academic\Data\StudyPlanData (code, name,
 * program_id). Web validation surfaces as 302 + session errors (NEVER 422).
 */
interface StudyPlanRow {
    id: number;
    code: string;
    name: string;
    program_id: number;
    program_name: string;
}

interface ProgramOption {
    id: number;
    name: string;
}

interface StudyPlansProps {
    study_plans: StudyPlanRow[];
    program_options: ProgramOption[];
}

type FormValues = {
    code: string;
    name: string;
    program_id: string;
};

const BASE_ROUTE = '/admin/catalogs/study-plans';
const EMPTY: FormValues = { code: '', name: '', program_id: '' };

export default function StudyPlans({ study_plans, program_options }: StudyPlansProps) {
    const { t } = useLocale();
    const [editing, setEditing] = useState<StudyPlanRow | null>(null);
    const [open, setOpen] = useState(false);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormValues>(EMPTY);

    const programOptions: SelectOption[] = program_options.map((option) => ({
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

    const openEdit = (row: StudyPlanRow) => {
        clearErrors();
        setData({ code: row.code, name: row.name, program_id: String(row.program_id) });
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

    const columns: ReportColumn<StudyPlanRow>[] = [
        {
            key: 'code',
            header: t('catalogs.study_plans.col.code'),
            cell: (row) => row.code,
            cellClassName: 'font-mono',
        },
        {
            key: 'name',
            header: t('catalogs.study_plans.col.name'),
            cell: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'program',
            header: t('catalogs.study_plans.col.program'),
            cell: (row) => row.program_name,
        },
    ];

    return (
        <>
            <CatalogShell
                titleKey="catalogs.study_plans.title"
                subtitleKey="catalogs.study_plans.description"
                baseRoute={BASE_ROUTE}
                columns={columns}
                rows={study_plans}
                rowLabel={(row) => row.name}
                onNew={openCreate}
                onEdit={openEdit}
            />

            {open ? (
                <FormModal
                    mode={editing ? 'edit' : 'create'}
                    title={t('catalogs.study_plans.title')}
                    processing={processing}
                    onSubmit={submit}
                    onCancel={close}
                >
                    <TextField
                        label={t('catalogs.study_plans.col.code')}
                        value={data.code}
                        onChange={(value) => setData('code', value)}
                        error={errors.code}
                        required
                        maxLength={20}
                    />
                    <TextField
                        label={t('catalogs.study_plans.col.name')}
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                        maxLength={150}
                    />
                    <SelectField
                        label={t('catalogs.study_plans.col.program')}
                        value={data.program_id}
                        onChange={(value) => setData('program_id', value)}
                        options={programOptions}
                        error={errors.program_id}
                        required
                        placeholder={t('catalogs.select.placeholder')}
                    />
                </FormModal>
            ) : null}
        </>
    );
}
