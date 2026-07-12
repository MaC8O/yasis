<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Student;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;

class BoardReportController extends Controller
{
    protected function academicDepartments()
    {
        return Department::whereIn('level', ['Secondary', 'Primary', 'Early Years'])->orderBy('name')->get();
    }

    protected function enrollmentByDepartment()
    {
        return $this->academicDepartments()->map(function ($department) {
            $total = Student::where('department_id', $department->id)->where('enrollment_status', 'Enrolled')->count();
            $newThisYear = Student::where('department_id', $department->id)->where('enrollment_status', 'Enrolled')
                ->whereYear('admission_date', now()->year)->count();

            return (object) ['department' => $department->name, 'total' => $total, 'newThisYear' => $newThisYear];
        });
    }

    protected function religiousBackground()
    {
        $total = Student::where('enrollment_status', 'Enrolled')->count();

        return Student::where('enrollment_status', 'Enrolled')
            ->selectRaw("COALESCE(religious_background, 'Not stated') as background, COUNT(*) as total")
            ->groupBy('background')->orderByDesc('total')->get()
            ->map(fn ($row) => (object) [
                'background' => $row->background,
                'total' => $row->total,
                'share' => $total > 0 ? round($row->total / $total * 100, 1) : 0,
            ]);
    }

    public function index()
    {
        $enrollment = $this->enrollmentByDepartment();

        return view('principal.board-reports.index', [
            'enrollment' => $enrollment,
            'totalEnrollment' => $enrollment->sum('total'),
            'religious' => $this->religiousBackground(),
        ]);
    }

    public function enrollmentPdf(AuditService $audit)
    {
        $audit->log(request()->user(), 'Generated Board enrollment report', 'BoardReport', null);
        $enrollment = $this->enrollmentByDepartment();

        $pdf = Pdf::loadView('principal.board-reports.enrollment-pdf', ['enrollment' => $enrollment, 'total' => $enrollment->sum('total')]);

        return $pdf->stream('board-enrollment-summary.pdf');
    }

    public function religiousPdf(AuditService $audit)
    {
        $audit->log(request()->user(), 'Generated Board religious background report', 'BoardReport', null);

        $pdf = Pdf::loadView('principal.board-reports.religious-pdf', ['religious' => $this->religiousBackground()]);

        return $pdf->stream('board-religious-background.pdf');
    }
}
