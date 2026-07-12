<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use App\Models\AbsenceNotice;
use App\Services\AuditService;
use Illuminate\Http\Request;

class GuardianAbsenceNoticeController extends Controller
{
    use ResolvesChild;

    public function index(Request $request)
    {
        $children = $this->guardianChildren($request);
        $child = $this->selectedChild($request);
        $guardian = $request->user()->guardian;

        return view('guardian.absence-notices.index', [
            'children' => $children,
            'child' => $child,
            'notices' => AbsenceNotice::where('student_id', $child->id)->where('guardian_id', $guardian->id)
                ->orderByDesc('from_date')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $guardian = $request->user()->guardian;
        abort_unless($guardian->students()->where('students.id', $data['student_id'])->exists(), 403);

        $notice = AbsenceNotice::create([
            'student_id' => $data['student_id'],
            'guardian_id' => $guardian->id,
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'reason' => $data['reason'] ?? null,
            'status' => 'Submitted',
        ]);

        $audit->log($request->user(), 'Submitted absence notice', 'AbsenceNotice', $notice->id);

        return back()->with('status', 'The school has been notified. Your homeroom teacher will see this.');
    }

    protected function authorizeUpcoming(Request $request, AbsenceNotice $notice): void
    {
        $guardian = $request->user()->guardian;
        abort_unless($notice->guardian_id === $guardian->id, 403);
        abort_unless(in_array($notice->status, ['Submitted', 'Acknowledged']) && $notice->from_date->isFuture(), 403, 'This notice can no longer be edited.');
    }

    public function update(Request $request, AbsenceNotice $absenceNotice, AuditService $audit)
    {
        $this->authorizeUpcoming($request, $absenceNotice);

        $data = $request->validate([
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $absenceNotice->update($data);
        $audit->log($request->user(), 'Edited absence notice', 'AbsenceNotice', $absenceNotice->id);

        return back()->with('status', 'Absence notice updated.');
    }

    public function cancel(Request $request, AbsenceNotice $absenceNotice, AuditService $audit)
    {
        $this->authorizeUpcoming($request, $absenceNotice);

        $absenceNotice->update(['status' => 'Cancelled']);
        $audit->log($request->user(), 'Cancelled absence notice', 'AbsenceNotice', $absenceNotice->id);

        return back()->with('status', 'Absence notice cancelled.');
    }
}
