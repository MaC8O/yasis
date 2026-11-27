<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DocumentRequest;
use App\Models\ResultFreeze;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\StatementOfMarksService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DocumentRequestController extends Controller
{
    protected array $types = ['Transcript', 'Report Card', 'Transfer/Leaving Certificate', 'Completion Certificate', 'Enrollment Certificate'];

    public function index(Request $request, StatementOfMarksService $statements)
    {
        $query = DocumentRequest::with(['student', 'preparedBy.user', 'statement.academicYear']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('student', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('student_id_number', 'like', "%{$search}%"));
        }

        if ($type = $request->string('type')->value()) {
            $query->where('type', $type);
        }

        $documents = $query->latest()->paginate(\App\Support\PerPage::resolve($request))->withQueryString();

        return view('registrar.documents.index', [
            'documents' => $documents,
            // Issued statements whose year results were re-frozen since: a new statement should be prepared.
            'outdated' => $documents->filter(fn ($d) => $d->statement && $statements->isOutdated($d->statement))->pluck('id')->all(),
            'types' => $this->types,
            'filters' => $request->only(['search', 'type']),
            // §7.2: the Statement of Marks form and the print gate.
            'years' => AcademicYear::orderByDesc('year_label')->get(),
            'defaultYearId' => $this->latestFrozenYearId(),
            'issuanceEnabled' => StatementOfMarksService::issuanceEnabled(),
            'issuanceOff' => StatementOfMarksService::ISSUANCE_OFF,
            'stats' => [
                'queued' => DocumentRequest::where('status', 'Draft')->count(),
                'readyToPrint' => DocumentRequest::where('status', 'Ready')->count(),
                'printed' => DocumentRequest::where('status', 'Printed')->count(),
            ],
        ]);
    }

    /** The latest school year whose results are frozen (all four terms locked), else the active year. */
    private function latestFrozenYearId(): ?int
    {
        return ResultFreeze::active()->whereNull('term_id')->join('academic_years', 'academic_years.id', '=', 'result_freezes.academic_year_id')
            ->orderByDesc('academic_years.year_label')->value('result_freezes.academic_year_id')
            ?? AcademicYear::where('is_active', true)->value('id');
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'exists:students,student_id_number'],
            'type' => ['required', 'in:'.implode(',', $this->types)],
        ]);

        $student = Student::where('student_id_number', $data['student_id_number'])->firstOrFail();

        $doc = DocumentRequest::create([
            'student_id' => $student->id,
            'type' => $data['type'],
            'status' => 'Draft',
            'prepared_by' => $request->user()->id,
        ]);

        $audit->log($request->user(), 'Prepared document request', 'DocumentRequest', $doc->id);

        return redirect()->route('registrar.documents.index')->with('status', "{$data['type']} draft created for {$student->name}.");
    }

    public function submitForApproval(Request $request, DocumentRequest $documentRequest, AuditService $audit)
    {
        abort_unless($documentRequest->type === 'Transcript' && $documentRequest->status === 'Draft', 403);

        $documentRequest->update(['status' => 'Pending Approval']);
        $audit->log($request->user(), 'Submitted transcript for two-key approval', 'DocumentRequest', $documentRequest->id);

        return back()->with('status', 'Transcript submitted — awaiting VP Academic then Principal approval.');
    }

    public function download(Request $request, DocumentRequest $documentRequest, AuditService $audit)
    {
        // A Statement of Marks prints through its own route, which issues it (StatementOfMarksController).
        abort_if($documentRequest->type === DocumentRequest::STATEMENT_OF_MARKS, 404);

        if ($documentRequest->type === 'Transcript' && ! in_array($documentRequest->status, ['Ready', 'Printed'])) {
            return back()->withErrors(['status' => 'This transcript needs VP + Principal two-key approval before it can be issued.']);
        }

        // Governance control (§3.6): transcript issuance can be disabled school-wide by the Principal.
        if ($documentRequest->type === 'Transcript' && \App\Models\SystemSetting::get('transcript_issuance_enabled', '0') !== '1') {
            return back()->withErrors(['status' => 'Transcript issuance is currently disabled by the Principal (governance control).']);
        }

        $documentRequest->load('student.department');

        $pdf = Pdf::loadView('documents.pdf.certificate', ['doc' => $documentRequest]);

        $wasFirstGeneration = is_null($documentRequest->generated_at);
        $documentRequest->update([
            'status' => 'Printed',
            'generated_at' => $documentRequest->generated_at ?? now(),
        ]);

        $audit->log($request->user(), $wasFirstGeneration ? 'Generated document PDF' : 'Re-printed document PDF', 'DocumentRequest', $documentRequest->id);

        return $pdf->stream(str($documentRequest->type)->slug().'-'.$documentRequest->student->student_id_number.'.pdf');
    }
}
