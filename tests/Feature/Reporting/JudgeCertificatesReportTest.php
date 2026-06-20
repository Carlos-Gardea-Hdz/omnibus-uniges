<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Enums\JuryRole;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * CERT-01 — judge certificates data (spec 008 §3 scenario 6, §2.4
 * Reporting/JudgeCertificates). The read-only JudgeCertificatesService runs ONE
 * scoped JuryAssignment query (+ eager loads of student/program + the four
 * professor relations) and INVERTS the four role columns into per-professor rows
 * IN PHP (D-INVERT). Each professor appears once with assignment_count and one
 * assignments[] entry per jury they sat, carrying the correct JuryRole value +
 * role_label_key, the student record, program, status and dates. A
 * withoutSubstitute() jury yields 3 (not 4) tuples; ?professor_id narrows to one.
 * Boots the app + PostgreSQL 18 (RefreshDatabase).
 */

/**
 * Seat a jury for a fresh student with the four given professors (substitute
 * optional). Returns the created assignment.
 *
 * @param  array{president: Professor, secretary: Professor, vocal: Professor, substitute?: Professor|null}  $panel
 */
function seatJury(array $panel, ?Student $student = null): JuryAssignment
{
    $student ??= Student::factory()->juryAssigned()->create();

    return JuryAssignment::factory()->create([
        'student_id' => $student->id,
        'president_professor_id' => $panel['president']->id,
        'secretary_professor_id' => $panel['secretary']->id,
        'vocal_professor_id' => $panel['vocal']->id,
        'substitute_professor_id' => ($panel['substitute'] ?? null)?->id,
    ]);
}

it('does not crash when a seated professor has been soft-deleted — the jury is skipped', function (): void {
    $admin = User::factory()->admin()->create();

    $president = Professor::factory()->create();
    seatJury([
        'president' => $president,
        'secretary' => Professor::factory()->create(),
        'vocal' => Professor::factory()->create(),
    ]);

    // SoftDeletes nulls the eager-loaded relation; the row shaper would deref
    // ->id. The service must skip the whole assignment, not 500.
    $president->delete();

    actingAs($admin)
        ->get(route('admin.reports.judge-certificates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                ->has('professors', 0),
        );
});

it('lists each professor once with the correct roles and student record', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create(['name' => 'Programa X']);

    $president = Professor::factory()->create(['first_name' => 'Luis', 'last_name' => 'Pérez', 'mother_last_name' => null]);
    $secretary = Professor::factory()->create();
    $vocal = Professor::factory()->create();
    $substitute = Professor::factory()->create();

    $student = Student::factory()->ceremonyScheduled()->create([
        'program_id' => $program->id,
        'control_number' => '20250018',
        'first_name' => 'Marta',
        'last_name' => 'Ruiz',
        'status' => GraduationStatus::CeremonyScheduled,
    ]);

    $assignment = seatJury([
        'president' => $president,
        'secretary' => $secretary,
        'vocal' => $vocal,
        'substitute' => $substitute,
    ], $student);

    actingAs($admin)
        ->get(route('admin.reports.judge-certificates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                ->has('professors', 4) // president, secretary, vocal, substitute
                ->where('professors', function ($professors) use ($president, $assignment): bool {
                    $row = collect($professors)->firstWhere('professor_id', $president->id);

                    if ($row === null || $row['assignment_count'] !== 1) {
                        return false;
                    }

                    $a = $row['assignments'][0];

                    return $row['professor_name'] === 'Luis Pérez'
                        && $a['jury_assignment_id'] === $assignment->id
                        && $a['role'] === JuryRole::President->value
                        && $a['role_label_key'] === JuryRole::President->labelKey()
                        && $a['student_control_number'] === '20250018'
                        && $a['student_name'] === 'Marta Ruiz'
                        && $a['program_name'] === 'Programa X'
                        && $a['student_status'] === GraduationStatus::CeremonyScheduled->value;
                }),
        );
});

it('derives each professor role from the column they occupy', function (): void {
    $admin = User::factory()->admin()->create();

    $president = Professor::factory()->create();
    $secretary = Professor::factory()->create();
    $vocal = Professor::factory()->create();
    $substitute = Professor::factory()->create();

    seatJury([
        'president' => $president,
        'secretary' => $secretary,
        'vocal' => $vocal,
        'substitute' => $substitute,
    ]);

    /** @var array<int, string> $expectedRole */
    $expectedRole = [
        $president->id => JuryRole::President->value,
        $secretary->id => JuryRole::Secretary->value,
        $vocal->id => JuryRole::Vocal->value,
        $substitute->id => JuryRole::Substitute->value,
    ];

    actingAs($admin)
        ->get(route('admin.reports.judge-certificates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                ->where('professors', function ($professors) use ($expectedRole): bool {
                    foreach ($professors as $professor) {
                        $role = $professor['assignments'][0]['role'];

                        if ($role !== $expectedRole[$professor['professor_id']]) {
                            return false;
                        }
                    }

                    return true;
                }),
        );
});

it('emits 3 tuples (no substitute) for a jury without a substitute', function (): void {
    $admin = User::factory()->admin()->create();

    $president = Professor::factory()->create();
    $secretary = Professor::factory()->create();
    $vocal = Professor::factory()->create();

    JuryAssignment::factory()->withoutSubstitute()->create([
        'student_id' => Student::factory()->juryAssigned()->create()->id,
        'president_professor_id' => $president->id,
        'secretary_professor_id' => $secretary->id,
        'vocal_professor_id' => $vocal->id,
    ]);

    actingAs($admin)
        ->get(route('admin.reports.judge-certificates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                // Exactly the 3 seated members appear; the null substitute is skipped.
                ->has('professors', 3)
                ->where('professors', fn ($professors) => collect($professors)
                    ->pluck('professor_id')->sort()->values()->all()
                    === collect([$president->id, $secretary->id, $vocal->id])->sort()->values()->all()),
        );
});

it('counts multiple assignments when one professor sits on several juries', function (): void {
    $admin = User::factory()->admin()->create();

    $shared = Professor::factory()->create(['first_name' => 'Sara', 'last_name' => 'Lima']);

    // Jury 1: shared is president.
    seatJury([
        'president' => $shared,
        'secretary' => Professor::factory()->create(),
        'vocal' => Professor::factory()->create(),
        'substitute' => Professor::factory()->create(),
    ]);
    // Jury 2: shared is vocal.
    seatJury([
        'president' => Professor::factory()->create(),
        'secretary' => Professor::factory()->create(),
        'vocal' => $shared,
        'substitute' => Professor::factory()->create(),
    ]);

    actingAs($admin)
        ->get(route('admin.reports.judge-certificates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                ->where('professors', function ($professors) use ($shared): bool {
                    $row = collect($professors)->firstWhere('professor_id', $shared->id);

                    if ($row === null || $row['assignment_count'] !== 2) {
                        return false;
                    }

                    // The two assignments carry the two distinct roles she held.
                    $roles = collect($row['assignments'])->pluck('role')->sort()->values()->all();

                    return $roles === collect(['president', 'vocal'])->sort()->values()->all();
                }),
        );
});

it('filters to one professor via ?professor_id', function (): void {
    $admin = User::factory()->admin()->create();

    $target = Professor::factory()->create();

    seatJury([
        'president' => $target,
        'secretary' => Professor::factory()->create(),
        'vocal' => Professor::factory()->create(),
        'substitute' => Professor::factory()->create(),
    ]);
    // A second jury with entirely different professors — must be excluded.
    seatJury([
        'president' => Professor::factory()->create(),
        'secretary' => Professor::factory()->create(),
        'vocal' => Professor::factory()->create(),
        'substitute' => Professor::factory()->create(),
    ]);

    actingAs($admin)
        ->get(route('admin.reports.judge-certificates', ['professor_id' => $target->id]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                ->has('professors', 1)
                ->where('professors.0.professor_id', $target->id)
                ->where('filters.professor_id', $target->id),
        );
});

it('offers as filter options only professors who served on at least one jury', function (): void {
    $admin = User::factory()->admin()->create();

    $president = Professor::factory()->create();
    $secretary = Professor::factory()->create();
    $vocal = Professor::factory()->create();
    $substitute = Professor::factory()->create();

    seatJury([
        'president' => $president,
        'secretary' => $secretary,
        'vocal' => $vocal,
        'substitute' => $substitute,
    ]);

    // A professor who never sat on a jury — must NOT appear in the options.
    $unseated = Professor::factory()->create();

    actingAs($admin)
        ->get(route('admin.reports.judge-certificates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                ->where('filter_options.professors', function ($options) use ($president, $secretary, $vocal, $substitute, $unseated): bool {
                    $ids = collect($options)->pluck('id');

                    return $ids->contains($president->id)
                        && $ids->contains($secretary->id)
                        && $ids->contains($vocal->id)
                        && $ids->contains($substitute->id)
                        && ! $ids->contains($unseated->id);
                }),
        );
});

it('returns no professors when no juries exist', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('admin.reports.judge-certificates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                ->has('professors', 0)
                ->where('filters.professor_id', null),
        );
});
