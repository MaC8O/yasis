<?php

namespace App\Http\Controllers\VpAcademic;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\GradeChangeRequest;
use App\Models\PromotionBatch;
use App\Services\AuditService;
use Illuminate\Http\Request;

class VpApprovalController extends Controller
{
    public function index()
    {
        return view('vp_academic.approvals.index', [
            'promotionBatches' => PromotionBatch::where('status', 'Pending')->with(['fromSection.department', 'preparedBy.user', 'items'])->get(),
            'transcripts' => DocumentRequest::where('type', 'Transcript')->where('status', 'Pending Approval')->with(['student', 'preparedBy.user'])->get(),
            'gradeChanges' => GradeChangeRequest::where('status', 'Pending')
                ->with(['assessment.category.section', 'assessment.category.subject', 'student', 'term', 'requestedBy.user'])
                ->latest()->get(),
        ]);
    }

    public function approveGradeChange(Request $request, GradeChangeRequest $gradeChangeRequest, AuditService $audit)
    {
        abort_unless($gradeChangeRequest->status === 'Pending', 403);

        $vp = $request->user()->staffProfile;
        $gradeChangeRequest->update(['status' => 'VP_Approved', 'vp_approved_by' => $vp->id, 'vp_approved_at' => now()]);

        $audit->log($request->user(), 'VP approved grade-change request (first key)', 'GradeChangeRequest', $gradeChangeRequest->id);

        return back()->with('status', 'Grade change approved — awaiting Principal co-approval.');
    }

    public function rejectGradeChange(Request $request, GradeChangeRequest $gradeChangeRequest, AuditService $audit)
    {
        abort_unless($gradeChangeRequest->status === 'Pending', 403);

        $vp = $request->user()->staffProfile;
        $gradeChangeRequest->update(['status' => 'Rejected', 'vp_approved_by' => $vp->id, 'vp_approved_at' => now()]);

        $audit->log($request->user(), 'VP rejected grade-change request', 'GradeChangeRequest', $gradeChangeRequest->id);

        return back()->with('status', 'Grade-change request rejected.');
    }

    public function approvePromotion(Request $request, PromotionBatch $promotionBatch, AuditService $audit)
    {
        abort_unless($promotionBatch->status === 'Pending', 403);

        $vp = $request->user()->staffProfile;
        $promotionBatch->update(['status' => 'VP_Approved', 'vp_approved_by' => $vp->id, 'vp_approved_at' => now()]);

        $audit->log($request->user(), 'VP approved promotion batch (first key)', 'PromotionBatch', $promotionBatch->id);

        return back()->with('status', 'Promotion batch approved — awaiting Principal co-approval.');
    }

    public function rejectPromotion(Request $request, PromotionBatch $promotionBatch, AuditService $audit)
    {
        abort_unless($promotionBatch->status === 'Pending', 403);

        $vp = $request->user()->staffProfile;
        $promotionBatch->update(['status' => 'Rejected', 'vp_approved_by' => $vp->id, 'vp_approved_at' => now()]);

        $audit->log($request->user(), 'VP rejected promotion batch', 'PromotionBatch', $promotionBatch->id);

        return back()->with('status', 'Promotion batch returned to Registrar.');
    }

    public function approveTranscript(Request $request, DocumentRequest $documentRequest, AuditService $audit)
    {
        abort_unless($documentRequest->type === 'Transcript' && $documentRequest->status === 'Pending Approval', 403);

        $vp = $request->user()->staffProfile;
        $documentRequest->update(['status' => 'Approved', 'approved_by' => $vp->id, 'approved_at' => now()]);

        $audit->log($request->user(), 'VP approved transcript (first key)', 'DocumentRequest', $documentRequest->id);

        return back()->with('status', 'Transcript approved — awaiting Principal co-approval.');
    }

    public function rejectTranscript(Request $request, DocumentRequest $documentRequest, AuditService $audit)
    {
        abort_unless($documentRequest->type === 'Transcript' && $documentRequest->status === 'Pending Approval', 403);

        $vp = $request->user()->staffProfile;
        $documentRequest->update(['status' => 'Returned', 'approved_by' => $vp->id, 'approved_at' => now()]);

        $audit->log($request->user(), 'VP returned transcript to Registrar', 'DocumentRequest', $documentRequest->id);

        return back()->with('status', 'Transcript returned to Registrar.');
    }
}
