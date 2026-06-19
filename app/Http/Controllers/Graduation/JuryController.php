<?php

declare(strict_types=1);

namespace App\Http\Controllers\Graduation;

use App\Domain\Academic\Models\Professor;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Actions\AssignJuryAction;
use App\Domain\Jury\Actions\VerifyPaymentAction;
use App\Domain\Jury\Data\AssignJuryData;
use App\Domain\Jury\Enums\JuryRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Staff queue to verify payment and assign a jury, the 6→7 transition (SPEC §003). */
final class JuryController extends Controller
{
    public function index(): Response
    {
        $students = Student::query()
            ->with(['program:id,name', 'graduationType:id,name'])
            ->where('status', GraduationStatus::PaymentPending->value)
            ->orderBy('control_number')
            ->paginate(15)
            ->through(fn (Student $student): array => $this->mapStudent($student));

        return Inertia::render('Graduation/JuryAssign', [
            'students' => $students,
            'professors' => $this->professors(),
            'roles' => array_map(fn (JuryRole $role): string => $role->value, JuryRole::cases()),
        ]);
    }

    public function verifyPayment(Student $student, VerifyPaymentAction $action): RedirectResponse
    {
        $action->handle($student);

        return redirect()->back()->with('success', __('payment.verified'));
    }

    public function assign(Student $student, AssignJuryData $data, AssignJuryAction $action): RedirectResponse
    {
        $action->handle($data, $student);

        return redirect()->back()->with('success', __('jury.assigned'));
    }

    /**
     * The selectable professors for the jury role pickers (SPEC §12).
     *
     * @return array<int, array{id: int, full_name: string}>
     */
    private function professors(): array
    {
        return Professor::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'mother_last_name'])
            ->map(fn (Professor $professor): array => [
                'id' => $professor->id,
                'full_name' => trim("{$professor->first_name} {$professor->last_name} {$professor->mother_last_name}"),
            ])->all();
    }

    /**
     * Shape one payment-pending student row for the page (SPEC §12).
     *
     * @return array<string, mixed>
     */
    private function mapStudent(Student $student): array
    {
        // program & graduation_type are NOT NULL FKs (eager-loaded above).
        $program = $student->program;
        $graduationType = $student->graduationType;
        $paidAt = $student->paid_at;

        return [
            'id' => $student->id,
            'control_number' => $student->control_number,
            'full_name' => trim("{$student->first_name} {$student->last_name}"),
            'program_name' => $program->name,
            'graduation_type_name' => $graduationType->name,
            'payment_reference' => $student->payment_reference,
            'paid_at' => $paidAt === null ? null : Carbon::parse($paidAt)->toIso8601String(),
            'payment_verified' => $student->payment_verified,
        ];
    }
}
