<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Models;

use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Enums\DocumentStatus;
use App\Models\User;
use App\Support\DemoScope;
use Database\Factories\StudentDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A single document a student uploads against a required-document slot,
 * moving through the pending → uploaded → approved/rejected lifecycle
 * (SPEC §6.3.13).
 *
 * @property int $id
 * @property string|null $demo_session_id
 * @property int $student_id
 * @property int $required_document_id
 * @property int|null $reviewed_by
 * @property string|null $file_path
 * @property string $file_token
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property DocumentStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $uploaded_at
 * @property Carbon|null $reviewed_at
 */
final class StudentDocument extends Model
{
    /** @use HasFactory<StudentDocumentFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        // Demo mode (slice 006): NULL on real documents, a UUIDv7 tag on demo rows.
        'demo_session_id',
        'student_id',
        'required_document_id',
        'reviewed_by',
        'file_path',
        'file_token',
        'original_filename',
        'mime_type',
        'file_size',
        'status',
        'rejection_reason',
        'uploaded_at',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

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
     * @return BelongsTo<RequiredDocument, $this>
     */
    public function requiredDocument(): BelongsTo
    {
        return $this->belongsTo(RequiredDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function newFactory(): StudentDocumentFactory
    {
        return StudentDocumentFactory::new();
    }
}
