<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotice;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registrar's senior correction path for attendance classifications (§3.6 Module 4):
 * the homeroom teacher classifies the day when taking attendance; the Registrar may
 * correct a classification afterwards (e.g. Absent → Excused when a notice was missed).
 */
class AttendanceCorrectionController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendanceRecord::with(['student', 'section', 'absenceNotice', 'recordedBy.user'])
            ->orderByDesc('attendance_date');

        $from = $request->date('from') ?? today()->subDays(14);
        $to = $request->date('to') ?? today();
        $query->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()]);

        if ($sectionId = $request->integer('section')) {
            $query->where('section_id', $sectionId);
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        // "Needs review": marked Absent although a submitted/acknowledged notice covers the day —
        // the classification the guardian's notice should have produced is Excused.
        $needsReview = AttendanceRecord::with(['student', 'section'])
            ->where('status', 'Absent')
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('absence_notices')
                ->whereColumn('absence_notices.student_id', 'attendance_records.student_id')
                ->whereColumn('absence_notices.from_date', '<=', 'attendance_records.attendance_date')
                ->whereColumn('absence_notices.to_date', '>=', 'attendance_records.attendance_date')
                ->whereIn('absence_notices.status', ['Submitted', 'Acknowledged']))
            ->orderByDesc('attendance_date')
            ->get();

        return view('registrar.attendance.index', [
            'records' => $query->paginate(\App\Support\PerPage::resolve($request, 20))->withQueryString(),
            'needsReview' => $needsReview,
            'sections' => Section::whereHas('academicYear', fn ($q) => $q->where('is_active', true))->orderBy('name')->get(),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'section' => $request->integer('section') ?: null, 'status' => $status ?? null],
        ]);
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord, AuditService $audit)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Present', 'Absent', 'Tardy', 'Excused'])],
        ]);

        $original = $attendanceRecord->status;

        // Keep the notice link truthful: an Excused day points at the covering notice if one
        // exists; any other classification must not reference a notice.
        $notice = $data['status'] === 'Excused'
            ? AbsenceNotice::where('student_id', $attendanceRecord->student_id)
                ->whereIn('status', ['Submitted', 'Acknowledged'])
                ->where('from_date', '<=', $attendanceRecord->attendance_date)
                ->where('to_date', '>=', $attendanceRecord->attendance_date)
                ->first()
            : null;

        $attendanceRecord->update([
            'status' => $data['status'],
            'absence_notice_id' => $notice?->id,
        ]);

        $audit->log(
            $request->user(),
            "Corrected absence classification ({$original} → {$data['status']})",
            'AttendanceRecord',
            $attendanceRecord->id
        );

        return back()->with('status', "Classification corrected: {$attendanceRecord->student->name}, {$attendanceRecord->attendance_date->format('M j')} — {$original} → {$data['status']}.");
    }
}
