<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class GuardianFeeController extends Controller
{
    use ResolvesChild;

    protected function visibleRecords($child)
    {
        return $child->importedFeeRecords()
            ->familyVisible()
            ->with('importBatch')->orderBy('txn_date')->get();
    }

    public function index(Request $request)
    {
        $children = $this->guardianChildren($request);
        $child = $this->selectedChild($request);

        $records = $this->visibleRecords($child);
        $totalBilled = $records->sum('amount');
        $latestBalance = $records->sortByDesc('txn_date')->first()?->balance ?? 0;

        return view('guardian.fees.index', [
            'children' => $children,
            'child' => $child,
            'records' => $records,
            'totalBilled' => $totalBilled,
            'paid' => $totalBilled - $latestBalance,
            'balance' => $latestBalance,
            'status' => $records->sortByDesc('txn_date')->first()?->status ?? 'No records',
        ]);
    }

    public function statement(Request $request, AuditService $audit)
    {
        $child = $this->selectedChild($request);
        $records = $this->visibleRecords($child);

        $audit->log($request->user(), 'Downloaded guardian fee statement', 'Student', $child->id);

        $pdf = Pdf::loadView('guardian.fees.statement-pdf', compact('child', 'records'));

        return $pdf->stream("fee-statement-{$child->student_id_number}.pdf");
    }
}
