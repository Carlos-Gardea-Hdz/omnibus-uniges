import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useLocale } from '@/Contexts/LocaleContext';
import SelectField, { type SelectOption } from '@/Components/form/SelectField';

/**
 * Per-student jury assignment form (workflow step 6→7).
 *
 * Four professor selects — President / Secretary / Vocal (required) and an
 * optional Substitute — mirroring App\Domain\Jury\Data\AssignJuryData. Each
 * student row owns its own Inertia `useForm`, so several rows can be filled in
 * independently without colliding (same isolation pattern as DocumentUploadForm).
 *
 * Two client-side guards complement the authoritative server rules:
 *   - all-different: when any two non-empty selects pick the same professor,
 *     submit is disabled and a distinct-professors message is shown (the server
 *     `Different` rules + the DB CHECK remain the source of truth)
 *   - payment gate: submit stays disabled until the student's payment is
 *     verified (the AssignJuryAction enforces this server-side regardless)
 *
 * snake_case field names match the controller route payload and the DTO.
 */
export interface ProfessorOption {
    id: number;
    full_name: string;
}

interface JuryAssignFormProps {
    studentId: number;
    professors: ProfessorOption[];
    /** Whether the student's payment has been verified (server-authoritative). */
    paymentVerified: boolean;
}

/** Mirrors App\Domain\Jury\Data\AssignJuryData (ids as controlled strings). */
type JuryValues = {
    president_professor_id: string;
    secretary_professor_id: string;
    vocal_professor_id: string;
    substitute_professor_id: string;
};

const EMPTY: JuryValues = {
    president_professor_id: '',
    secretary_professor_id: '',
    vocal_professor_id: '',
    substitute_professor_id: '',
};

export default function JuryAssignForm({
    studentId,
    professors,
    paymentVerified,
}: JuryAssignFormProps) {
    const { t } = useLocale();

    const { data, setData, transform, post, processing, errors } = useForm<JuryValues>(EMPTY);

    const options: SelectOption[] = useMemo(
        () => professors.map((professor) => ({ value: professor.id, label: professor.full_name })),
        [professors],
    );

    // Collision among the non-empty selects → the server would reject it, so we
    // disable submit early and explain why. Only non-empty values are compared
    // (an unchosen optional substitute never collides).
    const chosen = [
        data.president_professor_id,
        data.secretary_professor_id,
        data.vocal_professor_id,
        data.substitute_professor_id,
    ].filter((value) => value !== '');
    const hasCollision = new Set(chosen).size !== chosen.length;

    const requiredFilled =
        data.president_professor_id !== '' &&
        data.secretary_professor_id !== '' &&
        data.vocal_professor_id !== '';

    const canSubmit = paymentVerified && requiredFilled && !hasCollision && !processing;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!canSubmit) {
            return;
        }
        // Drop the empty optional substitute so it serialises as absent (the
        // server `Nullable` rule + `IS NULL` CHECK handle a missing substitute).
        transform((payload) => ({
            ...payload,
            substitute_professor_id:
                payload.substitute_professor_id === ''
                    ? null
                    : payload.substitute_professor_id,
        }));
        post(`/admin/graduation/jury/${studentId}/assign`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate className="mt-4 flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <SelectField
                    label={t('admin.jury.role.president')}
                    value={data.president_professor_id}
                    onChange={(value) => setData('president_professor_id', value)}
                    options={options}
                    error={errors.president_professor_id}
                    required
                    disabled={!paymentVerified}
                    placeholder={t('form.placeholder.select')}
                />
                <SelectField
                    label={t('admin.jury.role.secretary')}
                    value={data.secretary_professor_id}
                    onChange={(value) => setData('secretary_professor_id', value)}
                    options={options}
                    error={errors.secretary_professor_id}
                    required
                    disabled={!paymentVerified}
                    placeholder={t('form.placeholder.select')}
                />
                <SelectField
                    label={t('admin.jury.role.vocal')}
                    value={data.vocal_professor_id}
                    onChange={(value) => setData('vocal_professor_id', value)}
                    options={options}
                    error={errors.vocal_professor_id}
                    required
                    disabled={!paymentVerified}
                    placeholder={t('form.placeholder.select')}
                />
                <SelectField
                    label={t('admin.jury.role.substitute')}
                    value={data.substitute_professor_id}
                    onChange={(value) => setData('substitute_professor_id', value)}
                    options={options}
                    error={errors.substitute_professor_id}
                    disabled={!paymentVerified}
                    placeholder={t('admin.jury.substitute_none')}
                />
            </div>

            {/* Distinct-professors guard message (role="alert" announces it). */}
            {hasCollision ? (
                <p role="alert" className="text-sm font-medium text-danger">
                    {t('admin.jury.guard.distinct')}
                </p>
            ) : null}

            {!paymentVerified ? (
                <p className="text-sm text-fg-muted">{t('admin.jury.guard.payment')}</p>
            ) : null}

            <div>
                <button
                    type="submit"
                    disabled={!canSubmit}
                    className="inline-flex h-11 items-center rounded-md bg-accent px-6 font-medium text-accent-fg transition-colors hover:bg-primary-dark disabled:opacity-60"
                >
                    {processing ? t('admin.jury.assigning') : t('admin.jury.assign')}
                </button>
            </div>
        </form>
    );
}
