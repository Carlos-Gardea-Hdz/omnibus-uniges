<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Models;

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\ValueObjects\ControlNumber;
use App\Domain\Graduation\ValueObjects\Gpa;
use App\Domain\Jury\Models\JuryAssignment;
use App\Domain\Shared\ValueObjects\Address;
use App\Models\User;
use App\Support\DemoScope;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A student progressing through the 9-state graduation workflow.
 *
 * @property int $id
 * @property string|null $demo_session_id
 * @property string $control_number
 * @property float $gpa
 * @property GraduationStatus $status
 * @property Carbon|null $form_b_submitted_at
 * @property Carbon|null $documents_completed_at
 * @property-read Program $program
 * @property-read GraduationType $graduationType
 * @property-read Collection<int, StudentDocument> $documents
 * @property-read JuryAssignment|null $juryAssignment
 */
final class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Mass-assignable attributes (defense-in-depth, WARN 5).
     *
     * Deliberately ONLY the student-supplied Form B intake fields, which the
     * SubmitFormBAction fills from the validated DTO. Workflow flags and identity
     * columns (status, form_b_approved, payment_verified, payment_reference,
     * user_id, demo_session_id, diploma_folio, record_book/sheet, the *_at
     * timestamps, ceremony_*, graduation_date) are NOT fillable: the Actions set
     * them explicitly via direct assignment, so a stray fill() can never escalate
     * a student's state or rebind their identity. Factories bypass guarding, so
     * seeding/tests are unaffected.
     *
     * @var list<string>
     */
    protected $fillable = [
        'control_number',
        'program_id',
        'graduation_type_id',
        'study_plan_id',
        'advisor_id',
        'first_name',
        'last_name',
        'mother_last_name',
        'gender',
        'age',
        'phone',
        'mobile',
        'gpa',
        'enrollment_date',
        'thesis_title',
        'thesis_abstract',
        'address_street',
        'address_neighborhood',
        'address_ext_number',
        'address_int_number',
        'address_postal_code',
        'is_team_project',
    ];

    /**
     * Attributes hidden from array/JSON serialization (defense-in-depth, WARN 5).
     * Identity/PII columns never need to leak through an accidental ->toArray()
     * or a Resource that forgets ->only(); controllers project an explicit field
     * list, so hiding these has no effect on the intended payloads.
     *
     * @var list<string>
     */
    protected $hidden = [
        'demo_session_id',
        'user_id',
        'payment_reference',
        'phone',
        'mobile',
        'address_street',
        'address_neighborhood',
        'address_ext_number',
        'address_int_number',
        'address_postal_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GraduationStatus::class,
            'workflow_metadata' => 'array',
            'form_b_submitted_at' => 'datetime',
            'documents_completed_at' => 'datetime',
            'paid_at' => 'datetime',
            'ceremony_date' => 'datetime',
            'enrollment_date' => 'date',
            'graduation_date' => 'date',
            'form_b_approved' => 'bool',
            'annex_iii_completed' => 'bool',
            'payment_verified' => 'bool',
            'is_team_project' => 'bool',
            'gpa' => 'decimal:2',
        ];
    }

    /**
     * Register the symmetric demo isolation scope (slice 006): real requests see
     * only NULL-tagged students; a demo session sees only its own tag. Cleanup and
     * cross-session provisioning bypass it with withoutGlobalScope(DemoScope::class).
     */
    protected static function booted(): void
    {
        self::addGlobalScope(new DemoScope);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<GraduationType, $this>
     */
    public function graduationType(): BelongsTo
    {
        return $this->belongsTo(GraduationType::class);
    }

    /**
     * @return BelongsTo<StudyPlan, $this>
     */
    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }

    /**
     * @return BelongsTo<Professor, $this>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Professor::class, 'advisor_id');
    }

    /**
     * @return HasMany<StudentDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    /**
     * @return HasOne<JuryAssignment, $this>
     */
    public function juryAssignment(): HasOne
    {
        return $this->hasOne(JuryAssignment::class);
    }

    /** Control number as a validated value object. */
    public function controlNumber(): ControlNumber
    {
        return new ControlNumber($this->control_number);
    }

    /** GPA as a validated value object (decimal stored, hundredths in the VO). */
    public function gpaVo(): Gpa
    {
        return Gpa::fromDecimal((float) $this->gpa);
    }

    /** Postal address as a value object, or null when none has been captured. */
    public function address(): ?Address
    {
        if ($this->address_street === null) {
            return null;
        }

        return new Address(
            street: $this->address_street,
            neighborhood: $this->address_neighborhood,
            extNumber: $this->address_ext_number,
            intNumber: $this->address_int_number,
            postalCode: $this->address_postal_code,
        );
    }

    protected static function newFactory(): StudentFactory
    {
        return StudentFactory::new();
    }
}
