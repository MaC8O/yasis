<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\TeachingAssignment;
use App\Services\ResultService;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(Request $request, ResultService $results)
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
        // The same figures as the grades page and the report card.
        $report = $currentTerm ? $results->studentTerm($student, $currentTerm) : null;
        $snapshot = collect($report?->subjects() ?? [])->filter(fn ($s) => $s->mark !== null)->values();

        $departmentId = $student->department_id;
        $sectionIds = $student->enrollments()->where('status', 'Active')->pluck('section_id');
        $notices = Announcement::where(fn ($q) => $q->whereIn('audience_type', ['School', 'Students'])
            ->orWhere(fn ($q2) => $q2->where('audience_type', 'Department')->where('audience_id', $departmentId))
            ->orWhere(fn ($q3) => $q3->where('audience_type', 'Section')->whereIn('audience_id', $sectionIds)))
            ->latest('published_at')->take(5)->get();

        return view('student.dashboard', [
            'student' => $student,
            'attendanceRate' => $attendanceRate,
            'report' => $report,
            'classesCount' => $assignments->count(),
            'snapshot' => $snapshot->take(3),
            'notices' => $notices,
            'recentAttendance' => $attendance->sortByDesc('attendance_date')->take(3),
            'attendanceSegments' => [
                ['label' => 'Present', 'value' => $attendance->where('status', 'Present')->count(), 'color' => '#2E8B57'],
                ['label' => 'Tardy', 'value' => $attendance->where('status', 'Tardy')->count(), 'color' => '#A8841B'],
                ['label' => 'Excused', 'value' => $attendance->where('status', 'Excused')->count(), 'color' => '#2E5AAC'],
                ['label' => 'Absent', 'value' => $attendance->where('status', 'Absent')->count(), 'color' => '#B0392B'],
            ],
            'subjectResults' => $snapshot->map(fn ($s) => [
                'label' => $s->label,
                'value' => (float) $s->roundedMark,
                'display' => $s->roundedMark.(($text = $report->bandText($s)) ? " ({$text})" : ''),
            ])->values(),
        ]);
    }
}
