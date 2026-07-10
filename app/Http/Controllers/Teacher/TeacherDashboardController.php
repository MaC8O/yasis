<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotice;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AssessmentCategory;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\LeaveBalance;
use App\Models\Student;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $request->user()->staffProfile;

        $taught = $teacher->teachingAssignments()->with(['section', 'subject'])->get();
        $homerooms = $teacher->homeroomSections;
        $allSections = $taught->pluck('section')->concat($homerooms)->unique('id')->values();

        $activeYear = AcademicYear::where('is_active', true)->first();
        $term = $activeYear?->terms()->where('start_date', '<=', today())->where('end_date', '>=', today())->first()
            ?? $activeYear?->terms()->orderBy('sequence')->first();

        $attendancePending = $allSections->filter(function ($section) {
            $rosterCount = $section->enrollments()->where('status', 'Active')->count();
            $markedCount = AttendanceRecord::where('section_id', $section->id)->where('attendance_date', today())->count();

            return $rosterCount > 0 && $markedCount < $rosterCount;
        })->values();

        $gradebookOverview = $taught->map(function ($assignment) use ($term) {
            $weight = AssessmentCategory::where('section_id', $assignment->section_id)
                ->where('subject_id', $assignment->subject_id)->where('term_id', $term?->id)->sum('weight_pct');

            return [
                'section' => $assignment->section->name,
                'weight' => (float) $weight,
                'ready' => (float) $weight === 100.0,
            ];
        });

        $leaveBalances = LeaveBalance::where('staff_id', $teacher->id)->where('year', now()->year)->with('leaveType')->get();

        // §3.6 dashboard spec: flag students in my sections whose 3 most recent
        // recorded days are all Absent (consecutive-absence early warning).
        $recentBySection = AttendanceRecord::whereIn('section_id', $allSections->pluck('id'))
            ->orderByDesc('attendance_date')
            ->get()
            ->groupBy('student_id');

        $consecutiveAbsentIds = $recentBySection
            ->filter(fn ($records) => $records->count() >= 3 && $records->take(3)->every(fn ($r) => $r->status === 'Absent'))
            ->keys();
        $consecutiveAbsentees = Student::whereIn('id', $consecutiveAbsentIds)->get();

        // §3.6 dashboard spec: guardian absence-notice flags for my homeroom.
        $homeroomStudentIds = Enrollment::whereIn('section_id', $homerooms->pluck('id'))->where('status', 'Active')->pluck('student_id');
        $pendingNotices = AbsenceNotice::whereIn('student_id', $homeroomStudentIds)
            ->where('status', 'Submitted')
            ->with('student')
            ->get();

        return view('teacher.dashboard', [
            'consecutiveAbsentees' => $consecutiveAbsentees,
            'pendingNotices' => $pendingNotices,
            'assignedClasses' => $allSections->count(),
            'attendancePending' => $attendancePending,
            'gradebookOverview' => $gradebookOverview,
            'gradebookTasksCount' => $gradebookOverview->where('ready', false)->count(),
            'announcementsCount' => Announcement::where('author_id', $teacher->id)->count(),
            'todaySections' => $allSections,
            'leaveBalances' => $leaveBalances,
        ]);
    }
}
