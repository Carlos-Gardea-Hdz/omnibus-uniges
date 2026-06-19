import { Head, useForm } from '@inertiajs/react';
import type { GraduationStatus } from '@/types/generated';
import { useLocale } from '@/Contexts/LocaleContext';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import TextField from '@/Components/form/TextField';
import SelectField, { type SelectOption } from '@/Components/form/SelectField';
import FormError from '@/Components/form/FormError';

/**
 * Form B — the student's graduation application (workflow step 1–3).
 *
 * The field set mirrors App\Domain\Graduation\Data\SubmitFormBData exactly,
 * so the typed `useForm` here is the front-end SSOT companion to the Spatie
 * Data DTO (which owns validation server-side). When the catalog DTOs are
 * exported via `php artisan typescript:transform`, swap `FormBValues` for the
 * generated `SubmitFormBData` import — the shape is identical by contract.
 */
type FormBValues = {
    control_number: string;
    first_name: string;
    last_name: string;
    mother_last_name: string;
    gender: '' | 'male' | 'female' | 'other';
    gpa: string;
    enrollment_date: string;
    thesis_title: string;
    thesis_abstract: string;
    program_id: string;
    graduation_type_id: string;
    study_plan_id: string;
    advisor_id: string;
    phone: string;
    mobile: string;
    age: string;
    address_street: string;
    address_neighborhood: string;
    address_ext_number: string;
    address_int_number: string;
    address_postal_code: string;
};

interface CatalogOption {
    id: number;
    name: string;
}

interface GraduationTypeOption extends CatalogOption {
    requires_advisor: boolean;
}

interface StudyPlanOption extends CatalogOption {
    program_id: number;
}

/**
 * Catalog data backing the form's select fields. Snake_case throughout to match
 * the controller payload, the SubmitFormBData DTO and the `useForm` shape.
 */
interface Catalogs {
    programs: CatalogOption[];
    graduation_types: GraduationTypeOption[];
    study_plans: StudyPlanOption[];
    professors: CatalogOption[];
}

/**
 * The acting user's own student record, shaped by the controller's `->only()`.
 * It carries the prefillable Form B columns plus the workflow `status` (a
 * GraduationStatus enum value) and `form_b_observations` (rejection feedback).
 * `null` on a brand-new applicant with no Student row yet.
 */
type StudentProp =
    | (Partial<FormBValues> & {
          status: GraduationStatus;
          form_b_observations: string | null;
      })
    | null;

interface FormBProps {
    /** The acting student's own record, or null when none exists yet. */
    student?: StudentProp;
    /** Select-field catalogs (programs, graduation types, study plans, professors). */
    catalogs: Catalogs;
}

const EMPTY: FormBValues = {
    control_number: '',
    first_name: '',
    last_name: '',
    mother_last_name: '',
    gender: '',
    gpa: '',
    enrollment_date: '',
    thesis_title: '',
    thesis_abstract: '',
    program_id: '',
    graduation_type_id: '',
    study_plan_id: '',
    advisor_id: '',
    phone: '',
    mobile: '',
    age: '',
    address_street: '',
    address_neighborhood: '',
    address_ext_number: '',
    address_int_number: '',
    address_postal_code: '',
};

function toOptions(items: CatalogOption[]): SelectOption[] {
    return items.map((item) => ({ value: item.id, label: item.name }));
}

export default function FormB({ student = null, catalogs }: FormBProps) {
    const { t } = useLocale();

    const { programs, graduation_types: graduationTypes, study_plans: studyPlans, professors } =
        catalogs;

    // The workflow status (when a Student row already exists) decides whether
    // this is a first submission or an edit. Rejection feedback, if any, drives
    // the banner below.
    const status = student?.status ?? null;
    const observations = student?.form_b_observations ?? null;

    // A submission exists once the status moves past the pending/rejected
    // intake gate; from there we PUT edits instead of POSTing a new one. A null
    // status means no Student row yet — a brand-new applicant who POSTs.
    // The generated enum lives in a .d.ts (types only, no runtime value), so we
    // compare against its raw values cast to the enum type — same pattern as
    // Status.tsx. See ADR/foundation note: emit a runtime enum to drop the cast.
    const isEditing =
        status !== null &&
        status !== ('form_b_pending' as GraduationStatus) &&
        status !== ('form_b_rejected' as GraduationStatus);

    // Seed from an existing application when present (coercing every field to a
    // controlled string), otherwise start blank. The shape matches the DTO.
    const initial: FormBValues = student
        ? (Object.fromEntries(
              (Object.keys(EMPTY) as Array<keyof FormBValues>).map((key) => [
                  key,
                  student[key] ?? EMPTY[key],
              ]),
          ) as FormBValues)
        : EMPTY;

    const { data, setData, post, put, processing, errors, recentlySuccessful } =
        useForm<FormBValues>(initial);

    const genderOptions: SelectOption[] = [
        { value: 'male', label: t('form.gender.male') },
        { value: 'female', label: t('form.gender.female') },
        { value: 'other', label: t('form.gender.other') },
    ];

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (isEditing) {
            put('/student/form-b', { preserveScroll: true });
        } else {
            post('/student/form-b', { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={t('form.title')} />
            <div className="flex min-h-dvh flex-col bg-surface text-fg">
                <header className="flex items-center justify-between border-b border-border px-6 py-4">
                    <span className="text-lg font-semibold text-gradient-primary">
                        {t('app.name')}
                    </span>
                    <nav className="flex items-center gap-2" aria-label="utilities">
                        <LanguageSwitcher />
                    </nav>
                </header>

                <main id="main" className="mx-auto w-full max-w-3xl flex-1 px-6 py-8">
                    <h1 className="text-2xl font-bold tracking-tight">{t('form.title')}</h1>
                    <p className="mt-2 text-fg-muted">{t('form.subtitle')}</p>

                    {recentlySuccessful ? (
                        <p
                            role="status"
                            className="mt-4 rounded-md border border-success/40 bg-success/10 px-4 py-3 text-sm font-medium text-success"
                        >
                            {t('form.success')}
                        </p>
                    ) : null}

                    {observations ? (
                        <div
                            role="alert"
                            className="mt-4 rounded-md border border-danger/40 bg-danger/10 px-4 py-3"
                        >
                            <p className="text-sm font-semibold text-danger">
                                {t('form.rejected.title')}
                            </p>
                            <p className="mt-1 text-sm text-fg">{observations}</p>
                        </div>
                    ) : null}

                    <form onSubmit={submit} noValidate className="mt-6 flex flex-col gap-8">
                        <fieldset className="flex flex-col gap-4">
                            <legend className="mb-2 text-sm font-semibold text-fg-muted uppercase tracking-wide">
                                {t('form.section.personal')}
                            </legend>

                            <TextField
                                label={t('form.field.control_number')}
                                value={data.control_number}
                                onChange={(value) => setData('control_number', value)}
                                error={errors.control_number}
                                required
                                inputMode="numeric"
                                hint={t('form.hint.control_number')}
                                autoComplete="off"
                            />

                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    label={t('form.field.first_name')}
                                    value={data.first_name}
                                    onChange={(value) => setData('first_name', value)}
                                    error={errors.first_name}
                                    required
                                    maxLength={100}
                                    autoComplete="given-name"
                                />
                                <TextField
                                    label={t('form.field.last_name')}
                                    value={data.last_name}
                                    onChange={(value) => setData('last_name', value)}
                                    error={errors.last_name}
                                    required
                                    maxLength={100}
                                    autoComplete="family-name"
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    label={t('form.field.mother_last_name')}
                                    value={data.mother_last_name}
                                    onChange={(value) => setData('mother_last_name', value)}
                                    error={errors.mother_last_name}
                                    maxLength={100}
                                    autoComplete="additional-name"
                                />
                                <SelectField
                                    label={t('form.field.gender')}
                                    value={data.gender}
                                    onChange={(value) =>
                                        setData('gender', value as FormBValues['gender'])
                                    }
                                    options={genderOptions}
                                    error={errors.gender}
                                    required
                                    placeholder={t('form.placeholder.select')}
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <TextField
                                    label={t('form.field.age')}
                                    value={data.age}
                                    onChange={(value) => setData('age', value)}
                                    error={errors.age}
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                />
                                <TextField
                                    label={t('form.field.phone')}
                                    value={data.phone}
                                    onChange={(value) => setData('phone', value)}
                                    error={errors.phone}
                                    type="tel"
                                    maxLength={15}
                                    autoComplete="tel"
                                />
                                <TextField
                                    label={t('form.field.mobile')}
                                    value={data.mobile}
                                    onChange={(value) => setData('mobile', value)}
                                    error={errors.mobile}
                                    type="tel"
                                    maxLength={15}
                                    autoComplete="tel"
                                />
                            </div>
                        </fieldset>

                        <fieldset className="flex flex-col gap-4">
                            <legend className="mb-2 text-sm font-semibold text-fg-muted uppercase tracking-wide">
                                {t('form.section.academic')}
                            </legend>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    label={t('form.field.program')}
                                    value={data.program_id}
                                    onChange={(value) => setData('program_id', value)}
                                    options={toOptions(programs)}
                                    error={errors.program_id}
                                    required
                                    placeholder={t('form.placeholder.select')}
                                />
                                <SelectField
                                    label={t('form.field.study_plan')}
                                    value={data.study_plan_id}
                                    onChange={(value) => setData('study_plan_id', value)}
                                    options={toOptions(studyPlans)}
                                    error={errors.study_plan_id}
                                    required
                                    placeholder={t('form.placeholder.select')}
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    label={t('form.field.graduation_type')}
                                    value={data.graduation_type_id}
                                    onChange={(value) => setData('graduation_type_id', value)}
                                    options={toOptions(graduationTypes)}
                                    error={errors.graduation_type_id}
                                    required
                                    placeholder={t('form.placeholder.select')}
                                />
                                <SelectField
                                    label={t('form.field.advisor')}
                                    value={data.advisor_id}
                                    onChange={(value) => setData('advisor_id', value)}
                                    options={toOptions(professors)}
                                    error={errors.advisor_id}
                                    placeholder={t('form.placeholder.none')}
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    label={t('form.field.gpa')}
                                    value={data.gpa}
                                    onChange={(value) => setData('gpa', value)}
                                    error={errors.gpa}
                                    required
                                    type="number"
                                    inputMode="decimal"
                                    min={70}
                                    max={100}
                                    step={0.01}
                                    hint={t('form.hint.gpa')}
                                />
                                <TextField
                                    label={t('form.field.enrollment_date')}
                                    value={data.enrollment_date}
                                    onChange={(value) => setData('enrollment_date', value)}
                                    error={errors.enrollment_date}
                                    required
                                    type="date"
                                />
                            </div>

                            <TextField
                                label={t('form.field.thesis_title')}
                                value={data.thesis_title}
                                onChange={(value) => setData('thesis_title', value)}
                                error={errors.thesis_title}
                                maxLength={300}
                            />

                            <div className="flex flex-col">
                                <label
                                    htmlFor="thesis_abstract"
                                    className="mb-1 text-sm font-medium text-fg"
                                >
                                    {t('form.field.thesis_abstract')}
                                </label>
                                <textarea
                                    id="thesis_abstract"
                                    value={data.thesis_abstract}
                                    onChange={(event) =>
                                        setData('thesis_abstract', event.target.value)
                                    }
                                    rows={4}
                                    aria-invalid={errors.thesis_abstract ? true : undefined}
                                    aria-describedby={
                                        errors.thesis_abstract ? 'thesis_abstract-error' : undefined
                                    }
                                    className="rounded-md border border-border bg-surface-raised px-3 py-2 text-fg transition-colors placeholder:text-fg-muted focus-visible:border-accent aria-[invalid=true]:border-danger"
                                />
                                <FormError
                                    id="thesis_abstract-error"
                                    message={errors.thesis_abstract}
                                />
                            </div>
                        </fieldset>

                        <fieldset className="flex flex-col gap-4">
                            <legend className="mb-2 text-sm font-semibold text-fg-muted uppercase tracking-wide">
                                {t('form.section.address')}
                            </legend>

                            <TextField
                                label={t('form.field.address_street')}
                                value={data.address_street}
                                onChange={(value) => setData('address_street', value)}
                                error={errors.address_street}
                                maxLength={125}
                                autoComplete="address-line1"
                            />

                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    label={t('form.field.address_neighborhood')}
                                    value={data.address_neighborhood}
                                    onChange={(value) => setData('address_neighborhood', value)}
                                    error={errors.address_neighborhood}
                                    maxLength={100}
                                />
                                <TextField
                                    label={t('form.field.address_postal_code')}
                                    value={data.address_postal_code}
                                    onChange={(value) => setData('address_postal_code', value)}
                                    error={errors.address_postal_code}
                                    type="number"
                                    inputMode="numeric"
                                    autoComplete="postal-code"
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    label={t('form.field.address_ext_number')}
                                    value={data.address_ext_number}
                                    onChange={(value) => setData('address_ext_number', value)}
                                    error={errors.address_ext_number}
                                    maxLength={11}
                                />
                                <TextField
                                    label={t('form.field.address_int_number')}
                                    value={data.address_int_number}
                                    onChange={(value) => setData('address_int_number', value)}
                                    error={errors.address_int_number}
                                    maxLength={11}
                                />
                            </div>
                        </fieldset>

                        <div className="flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex h-11 items-center rounded-md bg-accent px-6 font-medium text-accent-fg transition-colors hover:bg-primary-dark disabled:opacity-60"
                            >
                                {isEditing ? t('form.submit.update') : t('form.submit.create')}
                            </button>
                            {processing ? (
                                <span className="text-sm text-fg-muted" role="status">
                                    {t('form.sending')}
                                </span>
                            ) : null}
                        </div>
                    </form>
                </main>
            </div>
        </>
    );
}
