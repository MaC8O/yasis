<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use App\Services\AuditService;
use App\Services\FeeScheduleService;
use App\Services\Sun\SunFamilyStatements;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * A guardian's view of a linked child's fees: the published Sun ledger statements, one per
 * financial year (every line, T4; lines hidden by hand as one "Adjustments" amount), and this
 * year's published fee schedule for the child's grade. Read-only.
 */
class GuardianFeeController extends Controller
{
    use ResolvesChild;

    public function index(Request $request, SunFamilyStatements $statements, FeeScheduleService $schedules)
    {
        $child = $this->selectedChild($request);

        return view('guardian.fees.index', [
            'children' => $this->guardianChildren($request),
            'child' => $child,
            'statements' => $statements->forStudent($child),
            'feeCard' => $schedules->familyCard($child),
        ]);
    }

    public function statement(Request $request, SunFamilyStatements $statements, AuditService $audit)
    {
        $child = $this->selectedChild($request);
        $audit->log($request->user(), 'Downloaded guardian fee statement', 'Student', $child->id);

        return self::pdf($child, $statements->forStudent($child));
    }

    /** The statement PDF, for families and the Treasurer's family copy. */
    public static function pdf($student, $statements)
    {
        $student->load(['department', 'enrollments.section', 'guardians.user']);

        return Pdf::loadView('documents.pdf.fee-statement', [
            'student' => $student,
            'guardian' => $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first(),
            'statements' => $statements,
        ])->stream("fee-statement-{$student->student_id_number}.pdf");
    }
}
