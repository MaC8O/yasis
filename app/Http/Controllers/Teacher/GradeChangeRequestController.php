<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeChangeRequest;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Post-lock grade changes (§3.6 governance): while a term is unlocked the teacher edits
 * the gradebook directly; once the Principal locks it, changes go through this two-key
 * request — VP Academic reviews (first key), Principal co-approves and the system
 * applies it (second key). Every step lands in the audit log.
 */
class GradeChangeRequestController extends Controller
{
    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'assessment_id' => ['required', 'exists:assessments,id'],
            'student_id' => ['required', 'exists:students,id'],
            'new_score' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $teacher = $request->user()->staffProfile;
        $assessment = Assessment::with('category.term')->findOrFail($data['assessment_id']);
        $category = $assessment->category;
        $term = $category->term;

        // Only the teacher assigned to this section-subject may request a change for it.
        $ownsAssignment = $teacher->teachingAssignments()
            ->where('section_id', $category->section_id)
            ->where('subject_id', $category->subject_id)
            ->exists();
        abort_unless($ownsAssignment, 403);

        if (! $term->is_locked) {
            throw ValidationException::withMessages([
                'assessment_id' => "{$term->name} is not locked — edit the score directly in the gradebook instead.",
            ]);
        }

        if ($data['new_score'] > (float) $assessment->max_score) {
            throw ValidationException::withMessages([
                'new_score' => "Score cannot exceed the assessment's maximum of {$assessment->max_score}.",
            ]);
        }

        $duplicate = GradeChangeRequest::where('assessment_id', $assessment->id)
            ->where('student_id', $data['student_id'])
            ->whereIn('status', ['Pending', 'VP_Approved'])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'assessment_id' => 'A change request for this student and assessment is already in the approval queue.',
            ]);
        }

        $existingGrade = Grade::where('assessment_id', $assessment->id)
            ->where('student_id', $data['student_id'])->first();

        $changeRequest = GradeChangeRequest::create([
            'assessment_id' => $assessment->id,
            'student_id' => $data['student_id'],
            'term_id' => $term->id,
            'old_score' => $existingGrade?->score,
            'new_score' => $data['new_score'],
            'reason' => $data['reason'],
            'status' => 'Pending',
            'requested_by' => $teacher->id,
        ]);

        $audit->log($request->user(), 'Requested post-lock grade change', 'GradeChangeRequest', $changeRequest->id);

        return back()->with('status', 'Grade-change request submitted — awaiting VP Academic review, then Principal co-approval.');
    }

    public function cancel(Request $request, GradeChangeRequest $gradeChangeRequest, AuditService $audit)
    {
        $teacher = $request->user()->staffProfile;
        abort_unless($gradeChangeRequest->requested_by === $teacher->id, 403);
        abort_unless($gradeChangeRequest->status === 'Pending', 403, 'Only a Pending request can be cancelled.');

        $gradeChangeRequest->update(['status' => 'Cancelled']);
        $audit->log($request->user(), 'Cancelled grade-change request', 'GradeChangeRequest', $gradeChangeRequest->id);

        return back()->with('status', 'Grade-change request cancelled.');
    }
}
