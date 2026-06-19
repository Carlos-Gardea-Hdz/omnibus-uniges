<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Actions\SubmitPaymentAction;
use App\Domain\Jury\Data\SubmitPaymentData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Student self-service for the payment stage that precedes jury assignment (SPEC §003). */
final class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $this->student($request);
        $paidAt = $student->paid_at;

        return Inertia::render('Student/Payment', [
            'student_id' => $student->id,
            'status' => $student->status->value,
            'payment_reference' => $student->payment_reference,
            'paid_at' => $paidAt === null ? null : Carbon::parse($paidAt)->toIso8601String(),
            'payment_verified' => $student->payment_verified,
        ]);
    }

    public function submit(SubmitPaymentData $data, Request $request, SubmitPaymentAction $action): RedirectResponse
    {
        $action->handle($data, $this->student($request));

        return redirect()->route('student.payment.index')->with('success', __('payment.submitted'));
    }

    /** Resolve the authenticated student or fail loud (SPEC §003). */
    private function student(Request $request): Student
    {
        $student = $request->user()?->student;

        if ($student === null) {
            abort(404);
        }

        return $student;
    }
}
