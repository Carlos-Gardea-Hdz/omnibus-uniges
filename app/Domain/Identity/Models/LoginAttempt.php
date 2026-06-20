<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Database\Factories\LoginAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The per-email brute-force ledger (SPEC §6.3.2). One row tracks the running
 * `failed_attempts` (reset to 0 on a successful login) and the monotonic
 * `times_blocked` escalation counter used by `LoginThrottle` to compute the
 * progressive cooldown. There is no `created_at`: the table carries `updated_at`
 * only, which is the timestamp the cooldown window is measured against.
 *
 * @property int $id
 * @property string $email
 * @property int $failed_attempts
 * @property int $times_blocked
 * @property Carbon|null $updated_at
 */
final class LoginAttempt extends Model
{
    /** @use HasFactory<LoginAttemptFactory> */
    use HasFactory;

    public const ?string CREATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'email',
        'failed_attempts',
        'times_blocked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'times_blocked' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): LoginAttemptFactory
    {
        return LoginAttemptFactory::new();
    }
}
