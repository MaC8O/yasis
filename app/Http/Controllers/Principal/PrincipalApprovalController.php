<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeChangeRequest;
use App\Models\PromotionBatch;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PrincipalApprovalController extends Controller
{
    public function index()
    {
        return view('principal.approvals.index', [
            'promotionBatches' => PromotionBatch::where('status', 'VP_Approved')->with(['fromSection.department', 'vpApprovedBy.user', 'items.student', 'items.toSection'])->get(),
            'transcripts' => DocumentRequest::where('type', 'Transcript')->where('status', 'Approved')->with(['student', 'approvedBy.user'])->get(),
            'gradeChanges' => GradeChangeRequest::where('status', 'VP_Approved')
                ->with(['assessment.category.section', 'assessment.category.subject', 'student', 'term', 'requestedBy.user', 'vpApprovedBy.user'])
                ->latest()->get(),
        ]);
    }

    public function approveGradeChange(Request $request, GradeChangeRequest $gradeChangeRequest, AuditService $audit)
    {
        abort_unless($gradeChangeRequest->status === 'VP_Approved', 403);

        $principal = $request->user()->staffProfile;

        // Second key: apply the change. The mark stays attributed to the requesting
        // teacher; the approval chain lives on the request row and in the audit log.
        Grade::updateOrCreate(
            ['assessment_id' => $gradeChangeRequest->assessment_id, 'student_id' => $gradeChangeRequest->student_id],
            ['score' => $gradeChangeRequest->new_score, 'entered_by' => $gradeChangeRequest->requested_by]
        );

        $gradeChangeRequest->update([
            'status' => 'Applied',
            'principal_approved_by' => $principal->id,
            'principal_approved_at' => now(),
            'applied_at' => now(),
        ]);

        $audit->log($request->user(), 'Principal co-approved and applied grade change', 'GradeChangeRequest', $gradeChangeRequest->id);

        return back()->with('status', 'Grade change approved and applied.');
    }

    public function rejectGradeChange(Request $request, GradeChangeRequest $gradeChangeRequest, AuditService $audit)
    {
        abort_unless($gradeChangeRequest->status === 'VP_Approved', 403);

        $principal = $request->user()->staffProfile;
        $gradeChangeRequest->update(['status' => 'Rejected', 'principal_approved_by' => $principal->id, 'principal_approved_at' => now()]);

        $audit->log($request->user(), 'Principal rejected grade-change request', 'GradeChangeRequest', $gradeChangeRequest->id);

        return back()->with('status', 'Grade-change request rejected.');
    }

    public function approvePromotion(Request $request, PromotionBatch $promotionBatch, AuditService $audit)
    {
        abort_unless($promotionBatch->status === 'VP_Approved', 403);

        $principal = $request->user()->staffProfile;

        foreach ($promotionBatch->items as $item) {
            $currentEnrollment = Enrollment::where('student_id', $item->student_id)
                ->where('section_id', $promotionBatch->from_section_id)->where('status', 'Active')->first();

            if ($item->action === 'Graduate') {
                $item->student->update(['enrollment_status' => 'Graduated']);
                $currentEnrollment?->update(['status' => 'Completed']);
            } elseif ($item->action === 'Promote' && $item->to_section_id) {
                $currentEnrollment?->update(['status' => 'Completed']);
                Enrollment::firstOrCreate(
                    ['student_id' => $item->student_id, 'section_id' => $item->to_section_id],
                    ['status' => 'Active']
                );
            }
            // 'Retain' and 'Promote' without a target section are left for the Registrar to place manually.
        }

        $promotionBatch->update(['status' => 'Applied', 'principal_approved_by' => $principal->id, 'principal_approved_at' => now(), 'applied_at' => now()]);

        $audit->log($request->user(), 'Principal co-approved and applied promotion batch', 'PromotionBatch', $promotionBatch->id);

        return back()->with('status', 'Promotion batch approved and applied.');
    }

    public function rejectPromotion(Request $request, PromotionBatch $promotionBatch, AuditService $audit)
    {
        abort_unless($promotionBatch->status === 'VP_Approved', 403);

        $principal = $request->user()->staffProfile;
        $promotionBatch->update(['status' => 'Rejected', 'principal_approved_by' => $principal->id, 'principal_approved_at' => now()]);

        $audit->log($request->user(), 'Principal rejected promotion batch', 'PromotionBatch', $promotionBatch->id);

        return back()->with('status', 'Promotion batch rejected.');
    }

    public function approveTranscript(Request $request, DocumentRequest $documentRequest, AuditService $audit)
    {
        abort_unless($documentRequest->type === 'Transcript' && $documentRequest->status === 'Approved', 403);

        $principal = $request->user()->staffProfile;
        $documentRequest->update(['status' => 'Ready', 'principal_approved_by' => $principal->id, 'principal_approved_at' => now()]);

        $audit->log($request->user(), 'Principal co-approved transcript release', 'DocumentRequest', $documentRequest->id);

        return back()->with('status', 'Transcript approved — ready for the Registrar to issue.');
    }

    public function rejectTranscript(Request $request, DocumentRequest $documentRequest, AuditService $audit)
    {
        abort_unless($documentRequest->type === 'Transcript' && $documentRequest->status === 'Approved', 403);

        $principal = $request->user()->staffProfile;
        $documentRequest->update(['status' => 'Returned', 'principal_approved_by' => $principal->id, 'principal_approved_at' => now()]);

        $audit->log($request->user(), 'Principal returned transcript to Registrar', 'DocumentRequest', $documentRequest->id);

        return back()->with('status', 'Transcript returned to Registrar.');
    }
}
