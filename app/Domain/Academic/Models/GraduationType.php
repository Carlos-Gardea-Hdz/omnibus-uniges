<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use Database\Factories\GraduationTypeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $requires_advisor
 */
final class GraduationType extends Model
{
    /** @use HasFactory<GraduationTypeFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'requires_advisor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_advisor' => 'boolean',
        ];
    }

    /**
     * @return GraduationTypeFactory
     */
    protected static function newFactory(): Factory
    {
        return GraduationTypeFactory::new();
    }
}
