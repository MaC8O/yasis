<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Grade;
use App\Models\GradeChangeRequest;
use App\Models\ReportCardComment;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\Term;
use App\Services\AuditService;
use App\Services\GradeService;
use Illuminate\Http\Request;

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
