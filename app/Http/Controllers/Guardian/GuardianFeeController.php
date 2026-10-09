<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use App\Services\AuditService;
use App\Services\FeeSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class GuardianFeeController extends Controller
{
    use ResolvesChild;

    /** The child's statement lines, cut with the family visibility rules (no SDA/held/unpublished). */
    protected function visibleLines($child, FeeSummaryService $service)
    {
        return $service->statementLines(
            $child->importedFeeRecords()->familyVisible()->with('importBatch')->get()
        );
    }

    public function index(Request $request, FeeSummaryService $service)
    {
        $children = $this->guardianChildren($request);
        $child = $this->selectedChild($request);
        $lines = $this->visibleLines($child, $service);

        $totalBilled = $lines->sum('charge');
        $balance = $lines->last()?->balance ?? 0;

        return view('guardian.fees.index', [
            'children' => $children,
            'child' => $child,
            'lines' => $lines,
            'aging' => $service->aging($lines),
            'totalBilled' => $totalBilled,
            'paid' => $lines->sum('payment'),
            'balance' => $balance,
            'status' => $lines->isEmpty() ? 'No records' : $service->accountStatus($totalBilled, $balance),
        ]);
    }

    public function statement(Request $request, FeeSummaryService $service, AuditService $audit)
    {
        $child = $this->selectedChild($request);
        $lines = $this->visibleLines($child, $service);

        $audit->log($request->user(), 'Downloaded guardian fee statement', 'Student', $child->id);

        $child->load(['department', 'enrollments.section', 'guardians.user']);

        $pdf = Pdf::loadView('documents.pdf.fee-statement', [
            'student' => $child,
            'guardian' => $child->guardians->firstWhere('pivot.is_primary', true) ?? $child->guardians->first(),
            'lines' => $lines,
            'aging' => $service->aging($lines),
            'familyCopy' => true,
        ]);

        return $pdf->stream("fee-statement-{$child->student_id_number}.pdf");
    }
}
