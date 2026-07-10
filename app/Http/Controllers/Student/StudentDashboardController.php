<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\TeachingAssignment;
use App\Services\GradeService;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(Request $request, GradeService $gradeService)
    {
        $student = $request->user()->student;
        abort_unless($student, 403, 'No student record linked to this account.');

        $attendance = $student->attendanceRecords()->get();
        $attendanceRate = $attendance->count()
            ? round($attendance->whereIn('status', ['Present', 'Tardy', 'Excused'])->count() / $attendance->count() * 100)
            : null;

        $activeYear = AcademicYear::where('is_active', true)->first();
        $currentTerm = $activeYear?->terms()->where('start_date', '<=', today())->where('end_date', '>=', today())->first();

        $assignments = TeachingAssignment::whereIn('section_id', $student->enrollments()->pluck('section_id'))->with('subject')->get();
        $snapshot = $assignments->map(fn ($a) => (object) [
            'subject' => $a->subject->name,
            'result' => $currentTerm ? $gradeService->computeStudentResult($student, $a->section, $a->subject, $currentTerm) : ['pct' => null, 'letter' => null, 'gpa' => null],
        ]);
        $gpas = $snapshot->pluck('result.gpa')->filter();

        $departmentId = $student->department_id;
        $notices = Announcement::where(fn ($q) => $q->whereIn('audience_type', ['School', 'Students'])
            ->orWhere(fn ($q2) => $q2->where('audience_type', 'Department')->where('audience_id', $departmentId)))
            ->latest('published_at')->take(5)->get();

        return view('student.dashboard', [
            'student' => $student,
            'attendanceRate' => $attendanceRate,
            'termGpa' => $gpas->isNotEmpty() ? round($gpas->avg(), 2) : null,
            'classesCount' => $assignments->count(),
            'snapshot' => $snapshot->take(3),
            'notices' => $notices,
            'recentAttendance' => $attendance->sortByDesc('attendance_date')->take(3),
        ]);
    }
}
