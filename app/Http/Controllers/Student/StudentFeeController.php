<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\GuardianFeeController;
use App\Services\AuditService;
use App\Services\FeeScheduleService;
use App\Services\Sun\SunFamilyStatements;
use Illuminate\Http\Request;

/** The student's own fees: the same published statements and fee card their guardian sees. Read-only. */
class StudentFeeController extends Controller
{
    public function index(Request $request, SunFamilyStatements $statements, FeeScheduleService $schedules)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        return view('student.fees.index', [
            'student' => $student,
            'statements' => $statements->forStudent($student),
            'feeCard' => $schedules->familyCard($student),
        ]);
    }

    public function statement(Request $request, SunFamilyStatements $statements, AuditService $audit)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);
        $audit->log($request->user(), 'Downloaded own fee statement', 'Student', $student->id);

        return GuardianFeeController::pdf($student, $statements->forStudent($student));
    }
}
