<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Imports\AssessmentScoresImport;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Grade;
use App\Models\GradeChangeRequest;
use App\Models\GradeScaleBand;
use App\Models\ReportCardComment;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\AuditService;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class TeacherGradebookController extends Controller
{
    protected function assignments(StaffProfile $teacher)
    {
        return $teacher->teachingAssignments()->with(['section', 'subject'])->get();
    }

    /**
     * Governance control (§3.6): once the Principal locks a term, its gradebook is
     * read-only for teachers — changes after finalisation require the VP + Principal
     * two-key path, so a mark cannot be quietly altered after report cards go out.
     */
    protected function assertTermUnlocked(?Term $term): void
    {
        if ($term?->is_locked) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'term' => "{$term->name} is locked by the Principal. Grade changes after finalisation require VP + Principal approval — contact the Registrar's office.",
            ]);
        }
    }

    public function index(Request $request, GradeService $gradeService)
    {
        $teacher = $request->user()->staffProfile;
        $assignments = $this->assignments($teacher);
        abort_if($assignments->isEmpty(), 403, 'No subject assignments yet.');

        $assignment = $assignments->firstWhere('section_id', $request->integer('section')) ?? $assignments->first();
        $section = Section::findOrFail($assignment->section_id);
        $subject = Subject::findOrFail($assignment->subject_id);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $terms = $activeYear?->terms()->orderBy('sequence')->get() ?? collect();
        $term = $terms->firstWhere('id', $request->integer('term')) ?? $terms->first();

        $categories = AssessmentCategory::where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->where('term_id', $term?->id)
            ->with('assessments.grades')
            ->get();

        $roster = $section->enrollments()->with('student')->where('status', 'Active')->get()->pluck('student');

        $results = $roster->mapWithKeys(fn ($student) => [
            $student->id => $gradeService->computeStudentResult($student, $section, $subject, $term),
        ]);

        $isHomeroom = $teacher->homeroomSections->contains('id', $section->id);
        $comments = $isHomeroom
            ? ReportCardComment::where('term_id', $term?->id)->whereIn('student_id', $roster->pluck('id'))->get()->keyBy('student_id')
            : collect();

        $changeRequests = GradeChangeRequest::where('term_id', $term?->id)
            ->where('requested_by', $teacher->id)
            ->whereHas('assessment.category', fn ($q) => $q->where('section_id', $section->id)->where('subject_id', $subject->id))
            ->with(['assessment', 'student'])
            ->latest()->get();

        // Grade bands for the roster's department(s), so the grid can show the live letter grade
        // client-side (as scores are typed) using the same thresholds GradeService applies on save.
        $bandsByDept = GradeScaleBand::whereIn('department_id', $roster->pluck('department_id')->unique()->filter())
            ->orderByDesc('min_score')
            ->get()
            ->groupBy('department_id')
            ->map(fn ($bands) => $bands->map(fn ($b) => ['min' => (float) $b->min_score, 'letter' => $b->letter])->values());

        return view('teacher.gradebook.index', [
            'changeRequests' => $changeRequests,
            'assignments' => $assignments,
            'section' => $section,
            'subject' => $subject,
            'terms' => $terms,
            'term' => $term,
            'categories' => $categories,
            'roster' => $roster,
            'results' => $results,
            'totalWeight' => (float) $categories->sum('weight_pct'),
            'isHomeroom' => $isHomeroom,
            'comments' => $comments,
            'bandsByDept' => $bandsByDept,
        ]);
    }

    public function storeCategory(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'name' => ['required', 'string', 'max:255'],
            'weight_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $this->assertTermUnlocked(Term::find($data['term_id']));

        $existingTotal = AssessmentCategory::where('section_id', $data['section_id'])
            ->where('subject_id', $data['subject_id'])->where('term_id', $data['term_id'])->sum('weight_pct');

        if ($existingTotal + $data['weight_pct'] > 100) {
            return back()->withErrors(['weight_pct' => 'Category weights cannot exceed 100% in total (currently at '.$existingTotal.'%).']);
        }

        $category = AssessmentCategory::create($data);
        $audit->log($request->user(), 'Added assessment category', 'AssessmentCategory', $category->id);

        return back()->with('status', "Category \"{$category->name}\" added.");
    }

    public function destroyCategory(Request $request, AssessmentCategory $assessmentCategory, AuditService $audit)
    {
        $this->assertTermUnlocked($assessmentCategory->term);

        $id = $assessmentCategory->id;
        $assessmentCategory->delete();
        $audit->log($request->user(), 'Removed assessment category', 'AssessmentCategory', $id);

        return back()->with('status', 'Category removed.');
    }

    public function storeAssessment(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:assessment_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
        ]);

        $this->assertTermUnlocked(AssessmentCategory::findOrFail($data['category_id'])->term);

        $assessment = Assessment::create($data);
        $audit->log($request->user(), 'Created assessment', 'Assessment', $assessment->id);

        return back()->with('status', "Assessment \"{$assessment->name}\" created.");
    }

    public function updateAssessment(Request $request, Assessment $assessment, AuditService $audit)
    {
        $category = $this->assertTeachesAssessment($request, $assessment);
        $this->assertTermUnlocked($category->term);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
        ]);

        // Lowering the maximum below a score already entered would make that grade impossible
        // (>100%); block it and tell the teacher which score is in the way.
        $highest = $assessment->grades()->max('score');
        if ($highest !== null && (float) $data['max_score'] < (float) $highest) {
            return back()->withErrors([
                'max_score' => "Max score can't be below the highest score already entered ({$highest}).",
            ]);
        }

        $assessment->update($data);
        $audit->log($request->user(), 'Updated assessment', 'Assessment', $assessment->id);

        return back()->with('status', "Item \"{$assessment->name}\" updated.");
    }

    public function destroyAssessment(Request $request, Assessment $assessment, AuditService $audit)
    {
        $category = $this->assertTeachesAssessment($request, $assessment);
        $this->assertTermUnlocked($category->term);

        $id = $assessment->id;
        $name = $assessment->name;
        $assessment->delete(); // grades cascade via the foreign key

        $audit->log($request->user(), 'Deleted assessment', 'Assessment', $id);

        return back()->with('status', "Item \"{$name}\" and its scores were deleted.");
    }

    /**
     * A teacher may only touch an assessment that lives in a category for a section + subject
     * they are actually assigned to teach — the same scoping the gradebook index enforces.
     */
    protected function assertTeachesAssessment(Request $request, Assessment $assessment): AssessmentCategory
    {
        $category = $assessment->category;
        abort_if($category === null, 404);

        $teacher = $request->user()->staffProfile;
        $teaches = $this->assignments($teacher)
            ->contains(fn ($a) => $a->section_id === $category->section_id && $a->subject_id === $category->subject_id);

        abort_unless($teaches, 403, 'You are not assigned to this class and subject.');

        return $category;
    }

    /**
     * Download a per-assessment score sheet pre-filled with the section roster so the teacher
     * only has to type the score column, then re-upload it via importScores().
     */
    public function scoresTemplate(Request $request, Assessment $assessment): Response
    {
        $category = $this->assertTeachesAssessment($request, $assessment);
        $section = Section::findOrFail($category->section_id);

        $roster = $section->enrollments()->with('student')->where('status', 'Active')->get()->pluck('student');

        $csv = "student_id_number,name,score\n";
        foreach ($roster as $student) {
            $name = str_replace('"', '""', (string) $student->name);
            $csv .= "{$student->student_id_number},\"{$name}\",\n";
        }

        $filename = 'scores_'.\Illuminate\Support\Str::slug($assessment->name).'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Bulk-import scores for a single assessment from the filled-in template. Rows are matched to
     * the roster by student_id_number; a row is reported and skipped (never partially saved) when
     * the student is not in this section, the score is blank, non-numeric, negative, or above the
     * assessment's max score. Mirrors the Registrar's student importer for a familiar workflow.
     */
    public function importScores(Request $request, Assessment $assessment, AuditService $audit)
    {
        $category = $this->assertTeachesAssessment($request, $assessment);
        $this->assertTermUnlocked($category->term);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $teacher = $request->user()->staffProfile;
        $section = Section::findOrFail($category->section_id);
        $rosterIds = $section->enrollments()->where('status', 'Active')->pluck('student_id');

        $import = new AssessmentScoresImport;
        Excel::import($import, $request->file('file'));

        $updated = [];
        $skipped = [];
        $errors = [];

        foreach ($import->rows as $i => $row) {
            $rowNum = $i + 2;

            $idNumber = trim((string) ($row['student_id_number'] ?? ''));
            $rawScore = trim((string) ($row['score'] ?? ''));

            if ($idNumber === '') {
                $errors[] = "Row {$rowNum}: missing student_id_number.";

                continue;
            }

            if ($rawScore === '') {
                $skipped[] = "Row {$rowNum}: {$idNumber} — blank score, skipped.";

                continue;
            }

            if (! is_numeric($rawScore)) {
                $errors[] = "Row {$rowNum}: {$idNumber} — score \"{$rawScore}\" is not a number.";

                continue;
            }

            $score = (float) $rawScore;
            if ($score < 0 || $score > (float) $assessment->max_score) {
                $errors[] = "Row {$rowNum}: {$idNumber} — score {$rawScore} is outside 0–{$assessment->max_score}.";

                continue;
            }

            $student = Student::where('student_id_number', $idNumber)->first();
            if (! $student || ! $rosterIds->contains($student->id)) {
                $errors[] = "Row {$rowNum}: {$idNumber} is not enrolled in {$section->name} — skipped.";

                continue;
            }

            Grade::updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $student->id],
                ['score' => $score, 'entered_by' => $teacher->id]
            );

            $updated[] = "Row {$rowNum}: {$idNumber} — {$score}";
        }

        $audit->log($request->user(), "Bulk-imported scores for assessment \"{$assessment->name}\" (".count($updated).' saved)', 'Assessment', $assessment->id);

        return back()
            ->with('status', count($updated).' score(s) imported, '.count($skipped).' skipped, '.count($errors).' error(s).')
            ->with('scoreImportResults', ['assessment' => $assessment->name, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors]);
    }

    public function saveScores(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'scores' => ['array'],
            'scores.*.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->assertTermUnlocked(Term::find($data['term_id']));

        $teacher = $request->user()->staffProfile;

        foreach ($data['scores'] ?? [] as $assessmentId => $studentScores) {
            foreach ($studentScores as $studentId => $score) {
                if ($score === null || $score === '') {
                    continue;
                }
                Grade::updateOrCreate(
                    ['assessment_id' => $assessmentId, 'student_id' => $studentId],
                    ['score' => $score, 'entered_by' => $teacher->id]
                );
            }
        }

        $audit->log($request->user(), 'Entered grades', 'Section', $data['section_id']);

        return back()->with('status', 'Gradebook saved.');
    }

    public function saveComment(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'comment' => ['required', 'string', 'max:500'],
        ]);

        $this->assertTermUnlocked(Term::find($data['term_id']));

        $teacher = $request->user()->staffProfile;

        $comment = ReportCardComment::updateOrCreate(
            ['student_id' => $data['student_id'], 'term_id' => $data['term_id']],
            ['staff_id' => $teacher->id, 'comment' => $data['comment']]
        );

        $audit->log($request->user(), 'Saved report-card comment', 'ReportCardComment', $comment->id);

        return back()->with('status', 'Comment saved.');
    }
}
