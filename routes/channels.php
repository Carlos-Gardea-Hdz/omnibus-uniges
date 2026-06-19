<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Per-student progress channel (SPEC §3.3). A student may listen to their own
 * channel; staff may listen to any. Authorization lives here, not the domain.
 */
Broadcast::channel('student.{studentId}', fn (User $u, string $studentId): bool => (string) $u->student?->id === $studentId
    || $u->role->isStaff());
