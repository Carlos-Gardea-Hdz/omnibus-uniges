<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use Database\Factories\RequiredDocumentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A document a student must upload to graduate, keyed by graduation type
 * via the graduation_type_required_document pivot (SPEC §6.3.9).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $allowed_mimes CSV of allowed MIME types
 * @property int $max_size_kb
 */
final class RequiredDocument extends Model
{
    /** @use HasFactory<RequiredDocumentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'allowed_mimes',
        'max_size_kb',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_size_kb' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<GraduationType, $this>
     */
    public function graduationTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            GraduationType::class,
            'graduation_type_required_document',
        );
    }

    /**
     * @return RequiredDocumentFactory
     */
    protected static function newFactory(): Factory
    {
        return RequiredDocumentFactory::new();
    }
}
