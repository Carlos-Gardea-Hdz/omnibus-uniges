<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Real-time graduation status board for the authenticated student (SPEC §3.3). */
final class StatusController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()?->student;

        return Inertia::render('Student/Status', [
            'student_id' => $student?->id,
            'control_number' => $student?->control_number,
            'full_name' => $student === null ? null : $student->first_name.' '.$student->last_name,
            'status' => $student?->status->value,
            'form_b_observations' => $student?->form_b_observations,
        ]);
    }
}
