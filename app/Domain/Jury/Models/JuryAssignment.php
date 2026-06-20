<?php

declare(strict_types=1);

namespace App\Domain\Jury\Models;

use App\Domain\Academic\Models\Professor;
use App\Domain\Graduation\Models\Student;
use App\Support\DemoScope;
use Database\Factories\JuryAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The jury assigned to a student at the payment → jury (6 → 7) transition.
 * One row per student (the student_id is unique). The three core members are
 * distinct columns (president/secretary/vocal); the substitute is optional.
 *
 * @property int $id
 * @property string|null $demo_session_id
 * @property int $student_id
 * @property int $president_professor_id
 * @property int $secretary_professor_id
 * @property int $vocal_professor_id
 * @property int|null $substitute_professor_id
 * @property-read Student $student
 * @property-read Professor $president
 * @property-read Professor $secretary
 * @property-read Professor $vocal
 * @property-read Professor|null $substitute
 */
final class JuryAssignment extends Model
{
    /** @use HasFactory<JuryAssignmentFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        // Demo mode (slice 006): NULL on real assignments, a UUIDv7 tag on demo rows.
        'demo_session_id',
        'student_id',
        'president_professor_id',
        'secretary_professor_id',
        'vocal_professor_id',
        'substitute_professor_id',
    ];

    /**
     * Register the symmetric demo isolation scope (slice 006). Cleanup and
     * cross-session tagging bypass it with withoutGlobalScope(DemoScope::class).
     */
    protected static function booted(): void
    {
        self::addGlobalScope(new DemoScope);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Professor, $this>
     */
    public function president(): BelongsTo
    {
        return $this->belongsTo(Professor::class, 'president_professor_id');
    }

    /**
     * @return BelongsTo<Professor, $this>
     */
    public function secretary(): BelongsTo
    {
        return $this->belongsTo(Professor::class, 'secretary_professor_id');
    }

    /**
     * @return BelongsTo<Professor, $this>
     */
    public function vocal(): BelongsTo
    {
        return $this->belongsTo(Professor::class, 'vocal_professor_id');
    }

    /**
     * @return BelongsTo<Professor, $this>
     */
    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Professor::class, 'substitute_professor_id');
    }

    protected static function newFactory(): JuryAssignmentFactory
    {
        return JuryAssignmentFactory::new();
    }
}
