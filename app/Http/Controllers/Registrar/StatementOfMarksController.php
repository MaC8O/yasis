<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\PrepareStatementOfMarksRequest;
use App\Http\Requests\Registrar\UpdateStatementOfMarksRequest;
use App\Models\AcademicYear;
use App\Models\StatementOfMarks;
use App\Models\Student;
use App\Services\StatementOfMarksService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * §7.2: the Registrar prepares a Statement of Marks (a document request), previews the draft and
 * prints it while the Principal's issuance control is on. The first print issues it.
 */
class StatementOfMarksController extends Controller
{
    public function store(PrepareStatementOfMarksRequest $request, StatementOfMarksService $statements)
    {
        $data = $request->validated();
        $student = Student::where('student_id_number', $data['student_id_number'])->firstOrFail();
        $year = AcademicYear::findOrFail($data['academic_year_id']);

        $statements->prepare($student, $year, $data['examination_date'], $data['issue_date'], $request->user());

        return redirect()->route('registrar.documents.index')
            ->with('status', "Statement of Marks prepared for {$student->name} ({$year->year_label}). Preview it, then print it to issue it.");
    }

    public function update(UpdateStatementOfMarksRequest $request, StatementOfMarks $statement, StatementOfMarksService $statements)
    {
        $data = $request->validated();
        $statements->updateDates($statement, $data['examination_date'], $data['issue_date'], $request->user());

        return back()->with('status', 'Statement of Marks dates saved.');
    }

    /** The draft with a "DRAFT – NOT ISSUED" watermark. Nothing is saved. */
    public function preview(StatementOfMarks $statement, StatementOfMarksService $statements)
    {
        if ($statement->isIssued()) {
            return back()->with('warning', 'This statement has been issued; reprint it instead.');
        }

        return $this->stream($statements->preview($statement), $statement, draft: true);
    }

    /** Print the statement: the first print issues it; reprints are the issued copy. */
    public function print(Request $request, StatementOfMarks $statement, StatementOfMarksService $statements)
    {
        return $this->stream($statements->issue($statement, $request->user()), $statement, draft: false);
    }

    private function stream(array $content, StatementOfMarks $statement, bool $draft)
    {
        $statement->loadMissing('documentRequest.student');
        $name = 'statement-of-marks-'.$statement->documentRequest->student->student_id_number.'-'.$content['school_year'].($draft ? '-draft' : '').'.pdf';

        return Pdf::loadView('documents.pdf.statement-of-marks', ['s' => $content, 'draft' => $draft])
            ->setPaper('a4', 'portrait')
            ->stream($name);
    }
}
