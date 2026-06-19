<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use Database\Factories\ProfessorFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $mother_last_name
 * @property string $email
 */
final class Professor extends Model
{
    /** @use HasFactory<ProfessorFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'last_name',
        'mother_last_name',
        'email',
    ];

    /**
     * @return ProfessorFactory
     */
    protected static function newFactory(): Factory
    {
        return ProfessorFactory::new();
    }
}
