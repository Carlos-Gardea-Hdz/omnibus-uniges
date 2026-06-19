<?php

declare(strict_types=1);

use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Runtime contract test: the FormBReviewController::index payload and the
 * Graduation/Review page's prop interface must agree on ONE shape. Inertia
 * props are untyped at runtime, so the static gates (tsc/PHPStan) cannot catch
 * a controller/page mismatch on their own — this locks it (SPEC §7).
 */

it('renders the review queue with the prop shape the page consumes', function (): void {
    $admin = User::factory()->admin()->create();

    $student = Student::factory()
        ->for(User::factory()->student())
        ->formBReview()
        ->create();

    actingAs($admin)
        ->get(route('admin.graduation.review'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Graduation/Review')
                ->has('students.data', 1)
                ->has(
                    'students.data.0',
                    fn ($row) => $row
                        ->where('id', $student->id)
                        ->where('control_number', $student->control_number)
                        ->where('program_name', $student->program->name)
                        ->where('graduation_type_name', $student->graduationType->name)
                        ->where('full_name', "{$student->first_name} {$student->last_name}")
                        ->has('first_name')
                        ->has('last_name')
                        ->has('gpa')
                        ->has('status')
                        ->has('form_b_submitted_at'),
                ),
        );
});
