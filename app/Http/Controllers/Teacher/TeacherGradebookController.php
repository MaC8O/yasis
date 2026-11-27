<?php

namespace App\Http\Controllers\Teacher;

use App\Exceptions\ActionRefused;
use App\Http\Controllers\Controller;
use App\Imports\AssessmentScoresImport;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Grade;
use App\Models\GradeChangeRequest;
use App\Models\GradingScheme;
use App\Models\ReportCardComment;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Services\AttendanceScoreService;
use App\Services\AuditService;
use App\Services\GradebookTemplateService;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class TeacherGradebookController extends Controller
{
    protected function assignments(StaffProfile $teacher)
    {
        return $teacher->teachingAssignments()->with(['section', 'subject'])->get();
    }

    /** Every gradebook write is limited to a section + subject the teacher is assigned to teach. */
    protected function assertTeaches(Request $request, int $sectionId, int $subjectId): void
    {
        $teaches = $this->assignments($request->user()->staffProfile)
            ->contains(fn ($a) => $a->section_id === $sectionId && $a->subject_id === $subjectId);

        abort_unless($teaches, 403, 'You are not assigned to this class and subject.');
    }

    /** Active roster student ids for a section. */
    protected function rosterIds(int $sectionId)
    {
        return \App\Models\Enrollment::where('section_id', $sectionId)->where('status', 'Active')->pluck('student_id');
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

    public function index(Request $request, ResultService $resultService, GradebookTemplateService $templates, AttendanceScoreService $attendanceScores)
    {
        $teacher = $request->user()->staffProfile;
        $assignments = $this->assignments($teacher);
        abort_if($assignments->isEmpty(), 403, 'No subject assignments yet.');

        // A teacher can hold several subjects in one section, so pick by assignment (or section + subject)
        // before falling back to the section alone.
        $assignment = $assignments->firstWhere('id', $request->integer('assignment'))
            ?? $assignments->first(fn ($a) => $a->section_id === $request->integer('section') && $a->subject_id === $request->integer('subject'))
            ?? $assignments->firstWhere('section_id', $request->integer('section'))
            ?? $assignments->first();
        $section = Section::findOrFail($assignment->section_id);
        $subject = Subject::findOrFail($assignment->subject_id);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $terms = $activeYear?->terms()->orderBy('sequence')->get() ?? collect();
        $term = $terms->firstWhere('id', $request->integer('term')) ?? $terms->first();

        // KG1 to Grade 10: an open term's gradebook is put on the school's fixed template first (§4.1).
        $usesTemplate = $templates->appliesTo($section);
        if ($usesTemplate && $term && ! $term->is_locked) {
            $templates->adopt($section, $subject->id, $term, $request->user());
        }

        $allCategories = AssessmentCategory::where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->where('term_id', $term?->id)
            ->with(['assessments' => fn ($q) => $q->whereNull('archived_at')->with('grades')])
            ->get();
        $archivedItems = Assessment::whereIn('category_id', $allCategories->pluck('id'))->whereNotNull('archived_at')
            ->with('category')->withCount('grades')->orderBy('archived_at')->get();
        $order = $templates->template()->pluck('sort_order', 'key');
        $categories = $allCategories->whereNull('archived_at')
            ->sortBy(fn ($c) => [$c->template_key ? $order[$c->template_key] ?? 99 : 99, $c->id])->values();
        // Archived categories: a teacher's own ones (restorable) and those emptied or set aside when a gradebook
        // moved onto the template (shown only while they still hold items).
        $archivedCategories = $allCategories->whereNotNull('archived_at')
            ->filter(fn ($c) => $c->assessments->isNotEmpty() || ! $usesTemplate)->values();

        $roster = $section->enrollments()->with('student')->where('status', 'Active')->get()->pluck('student');

        // The Attendance category's figures, and students with section school days that have no record for them.
        $attendance = $usesTemplate && $term ? $attendanceScores->forSectionTerm($section, $term) : null;
        $missingAttendance = $attendance
            ? $roster->filter(fn ($s) => ($attendance['students'][$s->id]['missing'] ?? 0) > 0)
                ->map(fn ($s) => ['name' => $s->name, 'missing' => $attendance['students'][$s->id]['missing']])->values()
            : collect();

        // One-time notices: items moved when a gradebook was put on the template.
        $notices = $request->user()->unreadNotifications()->where('data->kind', 'gradebook_template')->get();
        $notices->markAsRead();

        // A locked term shows its frozen figures; an unlocked one is previewed live in the grid (provisional).
        $sectionResults = $term ? $resultService->sectionTerm($section, $term) : null;
        $frozenRows = $sectionResults?->frozen
            ? $roster->mapWithKeys(fn ($student) => [$student->id => $sectionResults->for($student)?->subject($subject->code)])
            : null;

        $isHomeroom = $teacher->homeroomSections->contains('id', $section->id);
        $comments = $isHomeroom
            ? ReportCardComment::where('term_id', $term?->id)->whereIn('student_id', $roster->pluck('id'))->get()->keyBy('student_id')
            : collect();

        $changeRequests = GradeChangeRequest::where('term_id', $term?->id)
            ->where('requested_by', $teacher->id)
            ->whereHas('assessment.category', fn ($q) => $q->where('section_id', $section->id)->where('subject_id', $subject->id))
            ->with(['assessment', 'student'])
            ->latest()->get();

        // The section's grading scheme, so the grid can preview the letter as scores are typed, with the
        // same rounding and bands ResultService uses. A subject outside the curriculum has no letter.
        $section->loadMissing('gradeLevel');
        $offering = $section->gradeLevel
            ? SubjectOffering::where('grade_level_id', $section->grade_level_id)->where('subject_id', $subject->id)->active()->first()
            : null;
        $scheme = $section->gradeLevel?->grading_scheme_id ? GradingScheme::find($section->gradeLevel->grading_scheme_id) : null;
        $previewRules = $scheme && $offering ? $resultService->rules($scheme) : null;

        return view('teacher.gradebook.index', [
            'changeRequests' => $changeRequests,
            'assignments' => $assignments,
            'section' => $section,
            'subject' => $subject,
            'terms' => $terms,
            'term' => $term,
            'categories' => $categories,
            'archivedCategories' => $archivedCategories,
            'archivedItems' => $archivedItems,
            'usesTemplate' => $usesTemplate,
            'attendance' => $attendance,
            'missingAttendance' => $missingAttendance,
            'notices' => $notices,
            'roster' => $roster,
            'frozenRows' => $frozenRows,
            'frozenAt' => $sectionResults?->freeze?->frozen_at,
            'totalWeight' => (float) $categories->sum('weight_pct'),
            'isHomeroom' => $isHomeroom,
            'comments' => $comments,
            'preview' => $previewRules ? [
                'bands' => collect($previewRules->markBands)->map(fn ($b) => ['label' => $b['label'], 'min' => (float) $b['min'], 'max' => (float) $b['max'], 'fail' => $b['is_fail']])->values(),
                'decimals' => $previewRules->markDecimals,
                'mode' => $previewRules->roundingMode,
                'passFail' => $offering->grading_mode === 'pass_fail',
            ] : null,
        ]);
    }

    public function storeCategory(Request $request, AuditService $audit, GradebookTemplateService $templates)
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'name' => ['required', 'string', 'max:255'],
            'weight_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $this->assertTeaches($request, (int) $data['section_id'], (int) $data['subject_id']);
        $this->assertTermUnlocked(Term::find($data['term_id']));
        $this->refuseFixedCategories(Section::findOrFail($data['section_id']), $templates);

        $existingTotal = AssessmentCategory::where('section_id', $data['section_id'])
            ->where('subject_id', $data['subject_id'])->where('term_id', $data['term_id'])->sum('weight_pct');

        if ($existingTotal + $data['weight_pct'] > 100) {
            return back()->withErrors(['weight_pct' => 'Category weights cannot exceed 100% in total (currently at '.$existingTotal.'%).']);
        }

        $category = AssessmentCategory::create($data);
        $audit->log($request->user(), 'Added assessment category', 'AssessmentCategory', $category->id);

        return back()->with('status', "Category \"{$category->name}\" added.");
    }

    public function destroyCategory(Request $request, AssessmentCategory $assessmentCategory, AuditService $audit, GradebookTemplateService $templates)
    {
        $this->assertTeaches($request, $assessmentCategory->section_id, $assessmentCategory->subject_id);
        $this->assertTermUnlocked($assessmentCategory->term);
        if ($assessmentCategory->isArchived()) {
            throw new ActionRefused("“{$assessmentCategory->name}” is already archived.");
        }
        if ($assessmentCategory->isTemplate()) {
            throw new ActionRefused("“{$assessmentCategory->name}” is one of the school's fixed categories and can't be archived.");
        }
        $this->refuseFixedCategories($assessmentCategory->section, $templates);

        // Archived, never deleted: its items and their grades are kept but no longer count.
        $assessmentCategory->update(['archived_at' => now()]);
        $audit->log($request->user(), 'Archived assessment category', 'AssessmentCategory', $assessmentCategory->id, [
            'name' => $assessmentCategory->name,
            'items' => $assessmentCategory->assessments()->count(),
            'grades' => \App\Models\Grade::whereIn('assessment_id', $assessmentCategory->assessments()->pluck('id'))->count(),
        ]);

        return back()->with('status', "Category “{$assessmentCategory->name}” archived. Its items and scores are kept but no longer count; you can restore it while the term is open.");
    }

    /** Bring back a teacher's own category archived in an open term (Grades 11-12). */
    public function restoreCategory(Request $request, AssessmentCategory $assessmentCategory, AuditService $audit, GradebookTemplateService $templates)
    {
        $this->assertTeaches($request, $assessmentCategory->section_id, $assessmentCategory->subject_id);
        $this->assertTermUnlocked($assessmentCategory->term);
        if (! $assessmentCategory->isArchived()) {
            throw new ActionRefused("“{$assessmentCategory->name}” is not archived.");
        }
        if ($assessmentCategory->isTemplate() || $templates->appliesTo($assessmentCategory->section)) {
            throw new ActionRefused("{$assessmentCategory->section->name} uses the school's fixed categories, so “{$assessmentCategory->name}” can't be restored. Move its items into a current category instead.");
        }
        $total = (float) AssessmentCategory::where('section_id', $assessmentCategory->section_id)->where('subject_id', $assessmentCategory->subject_id)
            ->where('term_id', $assessmentCategory->term_id)->active()->sum('weight_pct');
        if ($total + (float) $assessmentCategory->weight_pct > 100) {
            throw new ActionRefused("Restoring “{$assessmentCategory->name}” would take the weights above 100% (currently {$total}%). Lower another category's weight first.");
        }

        $assessmentCategory->update(['archived_at' => null]);
        $audit->log($request->user(), 'Restored assessment category', 'AssessmentCategory', $assessmentCategory->id, ['name' => $assessmentCategory->name]);

        return back()->with('status', "Category “{$assessmentCategory->name}” restored; its items count again.");
    }

    public function storeAssessment(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:assessment_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Assessment::TYPES))],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
        ]);

        $category = AssessmentCategory::findOrFail($data['category_id']);
        $this->assertTeaches($request, $category->section_id, $category->subject_id);
        $this->assertTermUnlocked($category->term);
        if ($category->isAttendance()) {
            throw new ActionRefused('Attendance has no items: its score comes from the attendance records.');
        }
        if ($category->isArchived()) {
            throw new ActionRefused("“{$category->name}” is archived. Add the item to one of the current categories.");
        }

        $assessment = Assessment::create($data);
        $audit->log($request->user(), 'Created assessment', 'Assessment', $assessment->id);

        return back()->with('status', "Assessment \"{$assessment->name}\" created.");
    }

    public function updateAssessment(Request $request, Assessment $assessment, AuditService $audit)
    {
        $category = $this->assertTeachesAssessment($request, $assessment);
        $this->assertTermUnlocked($category->term);
        $this->refuseArchived($assessment);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Assessment::TYPES))],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
        ]);
        if (empty($data['type'])) {
            unset($data['type']); // keep the current type when the form doesn't send one
        }

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

        // Archived, never deleted: its grades are kept but count in no mark.
        $assessment->update(['archived_at' => now()]);
        $audit->log($request->user(), 'Archived assessment', 'Assessment', $assessment->id, [
            'name' => $assessment->name, 'category' => $category->name, 'grades' => $assessment->grades()->count(),
        ]);

        return back()->with('status', "Item “{$assessment->name}” archived. Its scores are kept but no longer count; you can restore it while the term is open.");
    }

    public function restoreAssessment(Request $request, Assessment $assessment, AuditService $audit)
    {
        $category = $this->assertTeachesAssessment($request, $assessment);
        $this->assertTermUnlocked($category->term);
        if (! $assessment->isArchived()) {
            throw new ActionRefused("“{$assessment->name}” is not archived.");
        }
        if ($category->isArchived()) {
            throw new ActionRefused("“{$assessment->name}” is in the archived category “{$category->name}”. Restore the category, or move the item into a current one.");
        }

        $assessment->update(['archived_at' => null]);
        $audit->log($request->user(), 'Restored assessment', 'Assessment', $assessment->id, ['name' => $assessment->name, 'category' => $category->name]);

        return back()->with('status', "Item “{$assessment->name}” restored; its scores count again.");
    }

    /** An archived item takes no new scores or edits until it is restored. */
    protected function refuseArchived(Assessment $assessment): void
    {
        if ($assessment->isArchived()) {
            throw new ActionRefused("“{$assessment->name}” is archived. Restore it before changing it or entering scores.");
        }
    }

    /**
     * Move an item, with its grades, to another category of the same gradebook (KG1 to Grade 10:
     * a template category other than Attendance). Audited.
     */
    public function moveAssessment(Request $request, Assessment $assessment, AuditService $audit)
    {
        $from = $this->assertTeachesAssessment($request, $assessment);
        $this->assertTermUnlocked($from->term);
        $this->refuseArchived($assessment);
        $data = $request->validate(['category_id' => ['required', 'integer']]);

        $to = AssessmentCategory::where('id', $data['category_id'])->where('section_id', $from->section_id)
            ->where('subject_id', $from->subject_id)->where('term_id', $from->term_id)->active()->first();
        if (! $to || $to->id === $from->id) {
            throw new ActionRefused('Choose another current category in the same gradebook.');
        }
        if ($to->isAttendance()) {
            throw new ActionRefused('Attendance has no items: its score comes from the attendance records.');
        }

        $assessment->update(['category_id' => $to->id]);
        $audit->log($request->user(), 'Moved assessment to another category', 'Assessment', $assessment->id, [
            'item' => $assessment->name, 'from' => $from->name, 'to' => $to->name, 'grades' => $assessment->grades()->count(),
        ]);

        return back()->with('status', "“{$assessment->name}” moved to {$to->name}, with its scores.");
    }

    /** KG1 to Grade 10 use the school's fixed categories: teachers can't add or remove categories. */
    protected function refuseFixedCategories(Section $section, GradebookTemplateService $templates): void
    {
        if ($templates->appliesTo($section)) {
            throw new ActionRefused("{$section->name} uses the school's fixed gradebook categories. Teachers add items inside them; the Admin edits the categories on Gradebook Template.");
        }
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
        $this->refuseArchived($assessment);

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
        $changes = [];

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

            $existing = Grade::where('assessment_id', $assessment->id)->where('student_id', $student->id)->first();
            if ($existing === null || (float) $existing->score !== $score) {
                $changes[] = [
                    'assessment_id' => $assessment->id,
                    'student_id' => $student->id,
                    'from' => $existing?->score,
                    'to' => $score,
                ];
            }

            Grade::updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $student->id],
                ['score' => $score, 'entered_by' => $teacher->id]
            );

            $updated[] = "Row {$rowNum}: {$idNumber} — {$score}";
        }

        $audit->log($request->user(), "Bulk-imported scores for assessment \"{$assessment->name}\" (".count($updated).' saved)', 'Assessment', $assessment->id, $changes ? ['changes' => $changes] : null);

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

        $this->assertTeaches($request, (int) $data['section_id'], (int) $data['subject_id']);
        $this->assertTermUnlocked(Term::find($data['term_id']));

        $changes = $this->writeScores($request, (int) $data['section_id'], (int) $data['subject_id'], $data['scores'] ?? []);

        $audit->log($request->user(), 'Entered grades', 'Section', $data['section_id'], $changes ? ['changes' => $changes] : null);

        return back()->with('status', 'Gradebook saved.');
    }

    /**
     * Validate and save [assessmentId => [studentId => score]] for one section + subject, all or
     * nothing. Every assessment must belong to that section + subject, every student must be on
     * its active roster, and each score must be within 0..max_score. Blank scores are skipped.
     *
     * @return list<array{assessment_id: int, student_id: int, from: mixed, to: float}>
     */
    protected function writeScores(Request $request, int $sectionId, int $subjectId, array $scores): array
    {
        $assessments = Assessment::with('category')->whereIn('id', array_keys($scores))->get()->keyBy('id');
        $rosterIds = $this->rosterIds($sectionId);
        $errors = [];

        foreach ($scores as $assessmentId => $studentScores) {
            $assessment = $assessments->get((int) $assessmentId);
            abort_if(! $assessment || $assessment->category->section_id !== $sectionId || $assessment->category->subject_id !== $subjectId,
                403, 'That item does not belong to your class and subject.');
            $this->refuseArchived($assessment);

            foreach ((array) $studentScores as $studentId => $score) {
                if ($score === null || $score === '') {
                    continue;
                }
                if (! $rosterIds->contains((int) $studentId)) {
                    $errors["scores.{$assessmentId}.{$studentId}"] = 'A student in this save is not on the class roster. Refresh the page and try again.';
                } elseif ((float) $score > (float) $assessment->max_score) {
                    $errors["scores.{$assessmentId}.{$studentId}"] = "A score of {$score} is above the maximum of ".rtrim(rtrim(number_format($assessment->max_score, 2), '0'), '.')." for {$assessment->name}.";
                }
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $teacher = $request->user()->staffProfile;
        $changes = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($scores, $teacher, &$changes) {
            foreach ($scores as $assessmentId => $studentScores) {
                foreach ((array) $studentScores as $studentId => $score) {
                    if ($score === null || $score === '') {
                        continue;
                    }

                    $existing = Grade::where('assessment_id', $assessmentId)->where('student_id', $studentId)->first();

                    // Record only genuine value changes (a new grade, or a different score) so the
                    // audit detail reconstructs exactly what a teacher altered.
                    if ($existing === null || (float) $existing->score !== (float) $score) {
                        $changes[] = [
                            'assessment_id' => (int) $assessmentId,
                            'student_id' => (int) $studentId,
                            'from' => $existing?->score,
                            'to' => (float) $score,
                        ];
                    }

                    Grade::updateOrCreate(
                        ['assessment_id' => $assessmentId, 'student_id' => $studentId],
                        ['score' => $score, 'entered_by' => $teacher->id]
                    );
                }
            }
        });

        return $changes;
    }

    /**
     * One item, whole class: a fast entry screen (one row per student, Enter/Tab to move on,
     * one save for everyone).
     */
    public function itemScores(Request $request, Assessment $assessment)
    {
        $category = $this->assertTeachesAssessment($request, $assessment);
        $section = Section::findOrFail($category->section_id);
        $term = $category->term;

        $roster = $section->enrollments()->with('student')->where('status', 'Active')->get()
            ->pluck('student')->filter()->sortBy('name')->values();
        $grades = $assessment->grades()->whereIn('student_id', $roster->pluck('id'))->get()->keyBy('student_id');

        // Neighbouring items in the same category, for quick next/previous navigation.
        $siblings = Assessment::where('category_id', $category->id)->orderBy('id')->get(['id', 'name']);
        $index = $siblings->search(fn ($s) => $s->id === $assessment->id);

        return view('teacher.gradebook.item-scores', [
            'assessment' => $assessment,
            'category' => $category,
            'section' => $section,
            'subject' => Subject::find($category->subject_id),
            'term' => $term,
            'roster' => $roster,
            'grades' => $grades,
            'previous' => $index > 0 ? $siblings[$index - 1] : null,
            'next' => $index !== false && $index < $siblings->count() - 1 ? $siblings[$index + 1] : null,
            'backUrl' => route('teacher.gradebook.index', ['section' => $section->id, 'subject' => $category->subject_id, 'term' => $term?->id]),
        ]);
    }

    public function saveItemScores(Request $request, Assessment $assessment, AuditService $audit)
    {
        $category = $this->assertTeachesAssessment($request, $assessment);
        $this->assertTermUnlocked($category->term);
        $this->refuseArchived($assessment);

        $data = $request->validate([
            'scores' => ['array'],
            'scores.*' => ['nullable', 'numeric', 'min:0'],
        ], ['scores.*.numeric' => 'Scores must be numbers.', 'scores.*.min' => 'Scores cannot be negative.']);

        $changes = $this->writeScores($request, $category->section_id, $category->subject_id, [$assessment->id => $data['scores'] ?? []]);

        $audit->log($request->user(), "Entered scores for item \"{$assessment->name}\"", 'Assessment', $assessment->id, $changes ? ['changes' => $changes] : null);

        $saved = collect($data['scores'] ?? [])->filter(fn ($s) => $s !== null && $s !== '')->count();

        return back()->with('status', "Saved {$saved} ".str('score')->plural($saved)." for {$assessment->name} (".count($changes).' changed).');
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

        // Report-card comments are written by the student's homeroom teacher only.
        $isHomeroomStudent = \App\Models\Enrollment::where('student_id', $data['student_id'])->where('status', 'Active')
            ->whereIn('section_id', $teacher->homeroomSections->pluck('id'))->exists();
        abort_unless($isHomeroomStudent, 403, 'Only the homeroom teacher can write this student\'s report-card comment.');

        $comment = ReportCardComment::updateOrCreate(
            ['student_id' => $data['student_id'], 'term_id' => $data['term_id']],
            ['staff_id' => $teacher->id, 'comment' => $data['comment']]
        );

        $audit->log($request->user(), 'Saved report-card comment', 'ReportCardComment', $comment->id);

        return back()->with('status', 'Comment saved.');
    }
}
