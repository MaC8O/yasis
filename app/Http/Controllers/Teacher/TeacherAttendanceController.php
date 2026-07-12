<?php

namespace App\Http\Controllers\Teacher;

use App\Mail\AbsenceAlertMail;
use App\Models\AbsenceNotice;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherAttendanceController extends Controller
{
    protected function accessibleSections(\App\Models\StaffProfile $teacher)
    {
        $taught = $teacher->teachingAssignments()->with('section')->get()->pluck('section');
        $homerooms = $teacher->homeroomSections;

        return $taught->concat($homerooms)->unique('id')->sortBy('name')->values();
    }

    public function index(Request $request)
    {
        $teacher = $request->user()->staffProfile;
        $sections = $this->accessibleSections($teacher);

        $section = $sections->firstWhere('id', $request->integer('section')) ?? $sections->first();
        abort_if(! $section, 403, 'No classes assigned.');

        $date = $request->date('date') ?? today();
        $activeYear = AcademicYear::where('is_active', true)->first();
        $term = $activeYear?->terms()->where('start_date', '<=', $date)->where('end_date', '>=', $date)->first()
            ?? $activeYear?->terms()->orderBy('sequence')->first();

        $roster = $section->enrollments()->with('student')->where('status', 'Active')->get()->pluck('student');

        $existing = AttendanceRecord::where('section_id', $section->id)
            ->where('attendance_date', $date->toDateString())
            ->get()->keyBy('student_id');

        $activeNotices = AbsenceNotice::whereIn('student_id', $roster->pluck('id'))
            ->whereIn('status', ['Submitted', 'Acknowledged'])
            ->where('from_date', '<=', $date->toDateString())
            ->where('to_date', '>=', $date->toDateString())
            ->get()->keyBy('student_id');

        $pendingAcknowledgement = AbsenceNotice::whereIn('student_id', $section->enrollments()->pluck('student_id'))
            ->where('status', 'Submitted')
            ->with('student')
            ->get();

        $counts = ['Present' => 0, 'Absent' => 0, 'Tardy' => 0, 'Excused' => 0, 'Unmarked' => 0];
        foreach ($roster as $student) {
            $status = $existing->get($student->id)?->status ?? ($activeNotices->has($student->id) ? 'Excused' : null);
            $counts[$status ?? 'Unmarked']++;
        }

        return view('teacher.attendance.index', [
            'sections' => $sections,
            'section' => $section,
            'date' => $date,
            'roster' => $roster,
            'existing' => $existing,
            'activeNotices' => $activeNotices,
            'pendingAcknowledgement' => $pendingAcknowledgement,
            'counts' => $counts,
            'term' => $term,
        ]);
    }

    public function store(Request $request, AuditService $audit, NotificationService $notifier)
    {
        $teacher = $request->user()->staffProfile;
        $sections = $this->accessibleSections($teacher);

        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'attendance_date' => ['required', 'date'],
            'term_id' => ['required', 'exists:terms,id'],
            'statuses' => ['required', 'array'],
            'statuses.*.student_id' => ['required', 'exists:students,id'],
            'statuses.*.status' => ['required', Rule::in(['Present', 'Absent', 'Tardy', 'Excused'])],
            'statuses.*.remark' => ['nullable', 'string', 'max:150'],
        ]);

        abort_unless($sections->contains('id', $data['section_id']), 403);

        foreach ($data['statuses'] as $row) {
            $notice = AbsenceNotice::where('student_id', $row['student_id'])
                ->whereIn('status', ['Submitted', 'Acknowledged'])
                ->where('from_date', '<=', $data['attendance_date'])
                ->where('to_date', '>=', $data['attendance_date'])
                ->first();

            $existing = AttendanceRecord::where([
                'student_id' => $row['student_id'],
                'section_id' => $data['section_id'],
                'attendance_date' => $data['attendance_date'],
            ])->first();
            $wasAlreadyAbsent = $existing?->status === 'Absent';

            $record = AttendanceRecord::updateOrCreate(
                [
                    'student_id' => $row['student_id'],
                    'section_id' => $data['section_id'],
                    'attendance_date' => $data['attendance_date'],
                ],
                [
                    'term_id' => $data['term_id'],
                    'status' => $row['status'],
                    'remark' => $row['remark'] ?? null,
                    'absence_notice_id' => $row['status'] === 'Excused' ? $notice?->id : null,
                    'recorded_by' => $teacher->id,
                ]
            );

            // §3.6 Module 4: "Absence records trigger an automated notification to the linked guardian."
            // Only fire on the transition into Absent, not on every re-save of an already-Absent day.
            if ($row['status'] === 'Absent' && ! $wasAlreadyAbsent) {
                $record->load(['student.guardians.user', 'section']);
                $guardianUser = $record->student->guardians->firstWhere('pivot.is_primary', true)?->user
                    ?? $record->student->guardians->first()?->user;

                if ($guardianUser) {
                    $notifier->sendEmail($guardianUser->email, new AbsenceAlertMail($record));
                }
            }
        }

        $audit->log($request->user(), 'Recorded attendance', 'Section', $data['section_id']);

        return back()->with('status', 'Attendance saved for '.\Carbon\Carbon::parse($data['attendance_date'])->format('M j, Y').'.');
    }

    public function acknowledge(Request $request, AbsenceNotice $absenceNotice, AuditService $audit)
    {
        $teacher = $request->user()->staffProfile;
        $homeroomSectionIds = $teacher->homeroomSections->pluck('id');

        $isHomeroomStudent = \App\Models\Enrollment::where('student_id', $absenceNotice->student_id)
            ->whereIn('section_id', $homeroomSectionIds)->exists();
        abort_unless($isHomeroomStudent, 403);

        $absenceNotice->update([
            'status' => 'Acknowledged',
            'acknowledged_by' => $teacher->id,
            'acknowledged_at' => now(),
        ]);

        $audit->log($request->user(), 'Acknowledged absence notice', 'AbsenceNotice', $absenceNotice->id);

        return back()->with('status', 'Absence notice acknowledged.');
    }
}
