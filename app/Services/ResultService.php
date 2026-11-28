<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\GradingScheme;
use App\Models\ReportRule;
use App\Models\ResultFreeze;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentElective;
use App\Models\StudentResult;
use App\Models\StudentTrack;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\SubjectStatus;
use App\Models\Term;
use App\Services\Results\SchemeRules;
use App\Services\Results\SectionResults;
use App\Services\Results\StudentResultData;
use App\Services\Results\StudentTermReport;
use App\Services\Results\SubjectResult;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Results (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4-6 and §9): each subject's mark, letter and
 * value; the average (KG1-9), point total (Grade 10) or GPA (11-12); class of pass; pass or fail
 * with the scheme's subject-fail rule; position in the section; and the promotion recommendation.
 * Every rule comes from the grading scheme of the section's grade level and from the curriculum.
 *
 * A locked term (or a year whose terms are all locked) is read from its freeze and never
 * recalculated; anything else is calculated live.
 *
 * A subject's year mark is the average of its four rounded term marks, and the year result is
 * worked out from those year marks like a term's (Q3, the school's "Final"). The average, point
 * total and GPA are built from the rounded subject marks (Q10, still open). Tied students share a
 * position by the printed figure, densely by default (1, 2, 2, 3; Q13). Grade 10 and Grades 11-12
 * students take the compulsory subjects plus their track's (Q6, Q7). SUPW has a word, not a mark,
 * and counts in nothing (Q5). A blocking question on the curriculum stops the level's result; a
 * band flagged with a class question (Q19) shows the result with its class pending.
 *
 * Frozen results are recalculated (a re-freeze after a grade change, a relock with the previous
 * rules) with the rules and the curriculum stored in their freeze.
 */
class ResultService
{
    /**
     * Defaults that decide the result itself: while one is flagged on a level's curriculum or
     * scheme, no result is calculated for that level. Q6 (which Grade 10 subjects count) is
     * answered; the list stays so frozen results that waited on it still read the same.
     */
    public const BLOCKING_QUESTIONS = ['Q6'];

    /**
     * Questions that leave only the class of pass undecided: a student whose subject mark or
     * result falls in a band flagged with one gets their result and position, with the class
     * "pending school answer". Q19: Grade 10 with a counted subject below 40 marks, or a point
     * total below 40.
     */
    public const CLASS_QUESTIONS = ['Q19'];

    /** Decimal places kept in intermediate steps. */
    private const SCALE = 10;

    public function __construct(private GradeService $grades) {}

    // --- Rules and rounding ----------------------------------------------------------------------

    public function rules(GradingScheme $scheme): SchemeRules
    {
        return SchemeRules::fromScheme($scheme);
    }

    /** Round a decimal string with the scheme's mode: "60.5" to 0 places is "61" half up, "60" down. */
    public function round(string $value, int $decimals, string $mode): string
    {
        return (string) BigDecimal::of($value)->toScale($decimals, $mode === 'down' ? RoundingMode::DOWN : RoundingMode::HALF_UP);
    }

    /** The band an already rounded value falls in, or null. */
    public function band(array $bands, string $rounded): ?array
    {
        $value = BigDecimal::of($rounded);
        foreach ($bands as $band) {
            if ($value->isGreaterThanOrEqualTo($band['min']) && $value->isLessThanOrEqualTo($band['max'])) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Round a result (average, point total or GPA) and find its class of pass.
     *
     * @return array{value: string, label: ?string, is_fail: bool, question: ?string}
     */
    public function classify(SchemeRules $rules, string $unrounded): array
    {
        $value = $this->round($unrounded, $rules->resultDecimals, $rules->roundingMode);
        $band = $this->band($rules->resultBands, $value);

        return ['value' => $value, 'label' => $band['label'] ?? null, 'is_fail' => (bool) ($band['is_fail'] ?? true),
            'question' => $this->classQuestion($band['pending_question'] ?? null)];
    }

    // --- Terms -------------------------------------------------------------------------------------

    /** A section's results for a term: from the term's freeze when it has one, otherwise live. */
    public function sectionTerm(Section $section, Term $term): SectionResults
    {
        $freeze = ResultFreeze::active()->where('term_id', $term->id)->latest('id')->first();
        if ($freeze) {
            return $this->stored($freeze, $section);
        }

        $live = $this->liveSectionTerm($section, $term);

        return $term->is_locked ? new SectionResults($live->students, lockedWithoutFreeze: true) : $live;
    }

    /**
     * A student's term as the pages show it: the section they were in that school year, and their
     * result from the term's freeze or calculated live (provisional).
     */
    public function studentTerm(Student $student, Term $term): StudentTermReport
    {
        $section = Section::whereHas('enrollments', fn ($q) => $q->where('student_id', $student->id)->where('status', '!=', 'Withdrawn'))
            ->where('academic_year_id', $term->academic_year_id)
            ->with('gradeLevel')->orderByDesc('id')->first();
        if (! $section) {
            return new StudentTermReport($term, null, null, false, false, null);
        }

        $results = $this->sectionTerm($section, $term);

        return new StudentTermReport($term, $section, $results->for($student), $results->frozen,
            $results->lockedWithoutFreeze, $results->freeze?->frozen_at);
    }

    /**
     * Calculate a section's term results now. $rulesFor maps a scheme id to the rules to use, and
     * $curriculumFor a grade level id to its curriculum rows (a freeze's copies; null for a level
     * the freeze has none for); by default each scheme's current rules and the current curriculum.
     */
    public function liveSectionTerm(Section $section, Term $term, ?callable $rulesFor = null, bool $final = false, ?callable $curriculumFor = null): SectionResults
    {
        $marks = $this->grades->sectionMarks($section, $term);

        // Locking a term (final): a subject on the gradebook template with an empty Examinations
        // category has no final mark, so a student counting it has no overall result (phase 5).
        $noExam = [];
        if ($final) {
            $noExam = $this->grades->subjectsWithoutExaminations($section, $term);
            foreach ($marks as $studentId => $subjects) {
                foreach ($noExam as $subjectId) {
                    unset($marks[$studentId][$subjectId]);
                }
            }
        }

        return $this->calculate($section, $term->academic_year_id, $marks, $rulesFor, false, $noExam, $curriculumFor, $this->words($section, $term));
    }

    /**
     * The words of the `completion` subjects (SUPW) for a term: student id => subject id => Pass,
     * Incomplete or Complete.
     *
     * @return array<int, array<int, string>>
     */
    public function words(Section $section, Term $term): array
    {
        $roster = Enrollment::where('section_id', $section->id)->where('status', '!=', 'Withdrawn')->pluck('student_id');
        $words = [];
        foreach (SubjectStatus::where('term_id', $term->id)->whereIn('student_id', $roster)->get() as $row) {
            $words[$row->student_id][$row->subject_id] = $row->status;
        }

        return $words;
    }

    // --- The year ----------------------------------------------------------------------------------

    /** A section's year results: from the year's freeze when every term is locked and frozen, otherwise live. */
    public function sectionYear(Section $section): SectionResults
    {
        $freeze = $this->yearFreeze($section->academic_year_id);

        return $freeze ? $this->stored($freeze, $section) : $this->liveSectionYear($section);
    }

    /** The year's active freeze: it exists once every term of the year is locked and frozen. */
    public function yearFreeze(int $academicYearId): ?ResultFreeze
    {
        return ResultFreeze::active()->whereNull('term_id')->where('academic_year_id', $academicYearId)->latest('id')->first();
    }

    /**
     * Calculate a section's year results now. A subject's year mark is the simple average of its
     * rounded term marks (Q3); it has none until every term of the year has a mark. Each term's
     * marks come from that term's freeze when it has one. SUPW's year word is the last term's.
     */
    public function liveSectionYear(Section $section, ?callable $rulesFor = null, ?callable $curriculumFor = null): SectionResults
    {
        $terms = Term::where('academic_year_id', $section->academic_year_id)->orderBy('sequence')->get();
        $termMarks = []; // student id => subject id => list of rounded term marks
        foreach ($terms as $term) {
            foreach ($this->sectionTerm($section, $term)->students as $studentId => $result) {
                foreach ($result->subjects as $subject) {
                    if ($subject->roundedMark !== null) {
                        $termMarks[$studentId][$subject->subjectId][] = $subject->roundedMark;
                    }
                }
            }
        }

        $marks = [];
        foreach ($termMarks as $studentId => $subjects) {
            foreach ($subjects as $subjectId => $list) {
                if ($terms->isNotEmpty() && count($list) === $terms->count()) {
                    $sum = array_reduce($list, fn (BigDecimal $s, string $m) => $s->plus($m), BigDecimal::zero());
                    $marks[$studentId][$subjectId] = (string) $sum->dividedBy(count($list), 2, RoundingMode::HALF_UP);
                }
            }
        }

        $last = $terms->last();

        return $this->calculate($section, $section->academic_year_id, $marks, $rulesFor, true, [], $curriculumFor,
            $last ? $this->words($section, $last) : []);
    }

    /**
     * Why a promotion batch for this section can't be submitted yet, or null when it can. A batch
     * needs the year's results frozen (every term locked by the Principal), so the decisions are
     * made against final results, not provisional ones.
     */
    public function promotionBlocker(Section $section): ?string
    {
        if ($this->yearFreeze($section->academic_year_id)) {
            return null;
        }

        $terms = Term::where('academic_year_id', $section->academic_year_id)->orderBy('sequence')->get();
        $open = $terms->reject(fn (Term $t) => $t->is_locked && ResultFreeze::active()->where('term_id', $t->id)->exists());

        return 'A promotion batch can be submitted once the year\'s results are frozen, when the Principal has locked every term. '
            .($open->isEmpty() ? 'The year is not frozen yet.' : 'Not locked yet: '.$open->pluck('name')->implode(', ').'.')
            .' The recommendations below are provisional until then.';
    }

    /**
     * Section 9: each student's recommended promotion decision, with what it is based on, from the
     * section's year results. Provisional until the year is frozen.
     *
     * @return array<int, array{action: ?string, basis: string}> student id => recommendation
     */
    public function promotionRecommendations(Section $section): array
    {
        $year = $this->sectionYear($section);
        $roster = Enrollment::where('section_id', $section->id)->where('status', '!=', 'Withdrawn')->pluck('student_id');

        return $roster->mapWithKeys(function ($studentId) use ($year) {
            $r = $year->for($studentId);
            if (! $r) {
                return [$studentId => ['action' => null, 'basis' => 'No recommendation: no result for this year.']];
            }
            if ($r->isComplete() && $r->recommendation === null) {
                return [$studentId => ['action' => null, 'basis' => "No recommendation: {$r->summary()}. The DHSC result decides (Q11)."]];
            }
            if (! $r->isComplete()) {
                return [$studentId => ['action' => null, 'basis' => 'No recommendation: '.implode(' ', $r->reasons)]];
            }

            // SUPW Incomplete is shown beside the recommendation; it changes it only when the FQ3 rule says so.
            $supw = $r->completionWord() === 'INCOMPLETE' ? ' · SUPW Incomplete' : '';

            return [$studentId => ['action' => $r->recommendation, 'basis' => $r->summary().$supw.($year->frozen ? '' : ' · provisional')]];
        })->all();
    }

    // --- The calculation -----------------------------------------------------------------------------

    /**
     * @param  array<int, array<int, string>>  $marks  student id => subject id => mark (two decimals)
     * @param  list<int>  $noExam  subjects with no final mark because their Examinations category is empty
     * @param  array<int, array<int, string>>  $words  student id => subject id => SUPW word
     */
    private function calculate(
        Section $section, int $yearId, array $marks, ?callable $rulesFor, bool $isYear, array $noExam = [],
        ?callable $curriculumFor = null, array $words = [],
    ): SectionResults {
        $section->loadMissing('gradeLevel');
        $level = $section->gradeLevel;
        $rules = null;
        if ($level?->grading_scheme_id) {
            $rules = $rulesFor ? $rulesFor($level->grading_scheme_id) : $this->rules(GradingScheme::findOrFail($level->grading_scheme_id));
        }

        $offerings = $level ? ($curriculumFor ? $curriculumFor($level->id) : null) ?? $this->curriculum($level->id) : collect();
        $slots = $offerings->whereNotNull('elective_slot')->pluck('elective_slot')->unique()->sort()->values();
        $hasTracks = $offerings->whereNotNull('track')->isNotEmpty();

        $roster = Enrollment::where('section_id', $section->id)->where('status', '!=', 'Withdrawn')
            ->orderBy('id')->pluck('student_id')->unique()->values();
        $choices = StudentElective::where('academic_year_id', $yearId)->whereIn('student_id', $roster)->get()
            ->groupBy('student_id')->map(fn ($rows) => $rows->pluck('subject_offering_id', 'elective_slot'));
        $tracks = StudentTrack::where('academic_year_id', $yearId)->whereIn('student_id', $roster)->pluck('track', 'student_id');
        $supwBlocks = $isYear && ReportRule::value('supw_incomplete_blocks_promotion', 'no') === 'yes';

        $blockers = $this->blockers($level, $rules, $offerings);
        $extraSubjects = Subject::whereIn('id', collect($marks)->flatMap(fn ($m) => array_keys($m))->unique()
            ->diff($offerings->pluck('subject_id')))->orderBy('name')->get();
        $isTopLevel = $level && ! GradeLevel::active()->where('sort_order', '>', $level->sort_order)->exists();

        $students = $roster->mapWithKeys(fn ($studentId) => [$studentId => $this->student(
            $studentId, $section, $level, $rules, $offerings, $slots, $choices->get($studentId, collect()),
            $hasTracks, $tracks->get($studentId), $marks[$studentId] ?? [], $words[$studentId] ?? [],
            $extraSubjects, $blockers, $isYear, $isTopLevel, $noExam, $supwBlocks,
        )]);

        $this->rank($students, $rules);

        return new SectionResults($students);
    }

    /** A grade level's active curriculum rows, in report order. */
    public function curriculum(int $gradeLevelId): Collection
    {
        return SubjectOffering::with('subject')->where('grade_level_id', $gradeLevelId)->active()->ordered()->get();
    }

    /** Why no result can be calculated for this level at all, if anything stops it. */
    private function blockers(?GradeLevel $level, ?SchemeRules $rules, Collection $offerings): array
    {
        if (! $level) {
            return ['This section has no grade level, so it has no grading scheme or curriculum.'];
        }
        if (! $rules) {
            return ["{$level->name} has no grading scheme. The Admin sets it on Grading Schemes."];
        }

        $reasons = [];
        $curriculumFlags = $offerings->flatMap(fn ($o) => $o->pendingQuestions())->unique();
        foreach (array_intersect(self::BLOCKING_QUESTIONS, $curriculumFlags->all()) as $q) {
            $reasons[] = "Which subjects count in {$level->name}'s result is waiting for the school's answer to {$q}, so no {$this->lower($rules)} or class is calculated yet.";
        }
        foreach (array_intersect(self::BLOCKING_QUESTIONS, $rules->pendingQuestions()) as $q) {
            $reasons[] = "The {$rules->name} grading rules are waiting for the school's answer to {$q}, so no {$this->lower($rules)} or class is calculated yet.";
        }

        return $reasons;
    }

    private function student(
        int $studentId, Section $section, ?GradeLevel $level, ?SchemeRules $rules, Collection $offerings,
        Collection $slots, Collection $choices, bool $hasTracks, ?string $track, array $marks, array $words,
        Collection $extraSubjects, array $blockers, bool $isYear, bool $isTopLevel, array $noExam, bool $supwBlocks,
    ): StudentResultData {
        $subjects = [];
        foreach ($offerings as $o) {
            $taken = match (true) {
                $o->track !== null => $track === $o->track,
                $o->elective_slot !== null => (int) $choices->get($o->elective_slot) === $o->id,
                default => (bool) $o->is_compulsory,
            };
            $completion = $o->grading_mode === 'completion';
            $mark = $taken && ! $completion ? ($marks[$o->subject_id] ?? null) : null;
            $word = $taken && $completion ? ($words[$o->subject_id] ?? null) : null;
            $subjects[] = $this->subject($o->subject_id, $o->subject->code, $o->subject->name, $o->report_label, $o->sort_order, $o,
                $taken, $taken && $o->counts_in_result && ! $completion, $mark, $rules, $word);
        }
        foreach ($extraSubjects as $i => $subject) {
            if (isset($marks[$subject->id])) {
                $subjects[] = $this->subject($subject->id, $subject->code, $subject->name, $subject->name, 1000 + $i, null,
                    true, false, $marks[$subject->id], $rules);
            }
        }

        $base = [
            'studentId' => $studentId, 'sectionId' => $section->id, 'gradeLevelId' => $level?->id,
            'schemeId' => $rules?->schemeId, 'basis' => $rules?->resultBasis, 'resultLabel' => $rules?->resultLabel(),
        ];

        if ($blockers !== []) {
            return $this->noResult($base, 'blocked', $blockers, $subjects);
        }

        $missing = [];
        if ($hasTracks && $track === null) {
            $missing[] = 'No track chosen (science or arts). The Registrar sets it on Tracks.';
        }
        foreach ($slots as $slot) {
            if (! $choices->has($slot)) {
                $missing[] = "No elective chosen for slot {$slot}.";
            }
        }
        $unmarked = array_filter($subjects, fn (SubjectResult $s) => $s->counted && $s->mark === null);
        $withoutExam = array_filter($unmarked, fn (SubjectResult $s) => in_array($s->subjectId, $noExam, true));
        $unmarked = array_diff_key($unmarked, $withoutExam);
        if ($withoutExam) {
            $missing[] = 'No examination was set in '.implode(', ', array_map(fn ($s) => $s->label, $withoutExam)).' this term, so '
                .(count($withoutExam) === 1 ? 'it has' : 'they have').' no final mark.';
        }
        if ($unmarked) {
            $missing[] = ($isYear ? 'Not every term has a mark yet in ' : 'No mark yet in ').implode(', ', array_map(fn ($s) => $s->label, $unmarked)).'.';
        }
        if ($missing !== []) {
            return $this->noResult($base, 'incomplete', $missing, $subjects);
        }

        $counted = array_values(array_filter($subjects, fn (SubjectResult $s) => $s->counted));
        if ($counted === []) {
            return $this->noResult($base, 'blocked', ["No subject in {$level->name}'s curriculum counts in the result."], $subjects);
        }

        $unrounded = $this->combine($rules, $counted);
        if ($unrounded === null) {
            $noHours = array_map(fn ($s) => $s->label, array_filter($counted, fn ($s) => $s->creditHours === null || $s->bandValue === null));

            return $this->noResult($base, 'blocked', ['These counted subjects have no credit hours or grade value: '.implode(', ', $noHours).'.'], $subjects);
        }

        $class = $this->classify($rules, (string) $unrounded);
        $average = match ($rules->resultBasis) {
            'average' => $class['value'],
            'point_total' => $this->round((string) $this->mean($counted), $rules->resultDecimals, $rules->roundingMode),
            default => null,
        };

        // Q19: a counted subject or the result in a band whose meaning the school hasn't confirmed.
        $pending = collect($counted)->pluck('bandQuestion')->push($class['question'])->filter()->unique()->values();
        if ($pending->isNotEmpty()) {
            return new StudentResultData(...$base, status: 'complete', reasons: [], value: $class['value'],
                unrounded: (string) $unrounded->toScale(4, RoundingMode::HALF_UP), classLabel: null, passed: null,
                position: null, rankedCount: null, recommendation: null, subjects: $subjects,
                average: $average, classPending: $pending->implode(', '));
        }

        $subjectFails = match ($rules->subjectFailRule) {
            'any_counted' => collect($counted)->contains(fn ($s) => $s->isFail),
            'any_compulsory' => collect($counted)->contains(fn ($s) => $s->isFail && $s->compulsory),
            default => false,
        };
        $passed = ! $class['is_fail'] && ! $subjectFails;
        $classLabel = $passed || $class['is_fail'] ? $class['label'] : $this->failLabel($rules);

        $recommendation = null;
        if ($isYear && $rules->resultBasis !== 'point_total') { // Grade 10: the DHSC decides (Q11)
            $recommendation = $passed ? ($isTopLevel ? 'Graduate' : 'Promote') : 'Retain';
            // FQ3: SUPW Incomplete stops promotion only when the Admin's rule says so (default no).
            $supw = collect($subjects)->first(fn (SubjectResult $s) => $s->usesCompletion() && $s->taken);
            if ($supwBlocks && $passed && $supw?->bandLabel === 'INCOMPLETE') {
                $recommendation = 'Retain';
            }
        }

        return new StudentResultData(...$base, status: 'complete', reasons: [], value: $class['value'],
            unrounded: (string) $unrounded->toScale(4, RoundingMode::HALF_UP), classLabel: $classLabel, passed: $passed,
            position: null, rankedCount: null, recommendation: $recommendation, subjects: $subjects, average: $average);
    }

    private function subject(
        int $subjectId, string $code, string $name, string $label, int $sortOrder, ?SubjectOffering $offering,
        bool $taken, bool $counted, ?string $mark, ?SchemeRules $rules, ?string $word = null,
    ): SubjectResult {
        $completion = $offering?->grading_mode === 'completion';
        $rounded = $mark !== null && $rules && ! $completion ? $this->round($mark, $rules->markDecimals, $rules->roundingMode) : null;
        $band = $rounded !== null && $offering ? $this->band($rules->markBands, $rounded) : null;
        $passFail = $offering?->grading_mode === 'pass_fail';

        return new SubjectResult(
            subjectId: $subjectId, code: $code, name: $name, label: $label, sortOrder: $sortOrder,
            offeringId: $offering?->id, inCurriculum: $offering !== null, compulsory: (bool) ($offering?->is_compulsory ?? false),
            slot: $offering?->elective_slot, taken: $taken, counted: $counted, gradingMode: $offering?->grading_mode,
            creditHours: $offering?->credit_hours === null ? null : (string) $offering->credit_hours,
            mark: $completion ? null : $mark, roundedMark: $rounded,
            bandLabel: $completion ? ($word === null ? null : mb_strtoupper($word))
                : ($band ? ($passFail ? ($band['is_fail'] ? 'FAIL' : 'PASS') : $band['label']) : null),
            bandValue: $band && ! $passFail && $rules->usesBandValues() ? $band['value'] : null,
            isFail: $completion ? ($word === null ? null : false) : ($band ? (bool) $band['is_fail'] : null),
            pendingQuestion: $offering?->pending_question,
            track: $offering?->track,
            bandQuestion: $this->classQuestion($band['pending_question'] ?? null),
        );
    }

    /** The class question (Q19) in a band's flags, if any. */
    private function classQuestion(?string $flags): ?string
    {
        $found = array_values(array_intersect(self::CLASS_QUESTIONS, array_map('trim', explode(',', (string) $flags))));

        return $found[0] ?? null;
    }

    /** The plain average of the counted subjects' rounded marks. @param  list<SubjectResult>  $counted */
    private function mean(array $counted): BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($counted as $s) {
            $sum = $sum->plus($s->roundedMark);
        }

        return $sum->dividedBy(count($counted), self::SCALE, RoundingMode::HALF_UP);
    }

    /**
     * The unrounded result from the counted subjects' rounded marks (Q10): the average of the
     * marks, the sum of the points, or the credit-hour GPA. Null when a value or hours are missing.
     *
     * @param  list<SubjectResult>  $counted
     */
    private function combine(SchemeRules $rules, array $counted): ?BigDecimal
    {
        if ($rules->resultBasis === 'average') {
            return $this->mean($counted);
        }

        $sum = BigDecimal::zero();
        if ($rules->resultBasis === 'point_total') {
            foreach ($counted as $s) {
                if ($s->bandValue === null) {
                    return null;
                }
                $sum = $sum->plus($s->bandValue);
            }

            return $sum;
        }

        $hours = BigDecimal::zero();
        foreach ($counted as $s) {
            if ($s->bandValue === null || $s->creditHours === null) {
                return null;
            }
            $sum = $sum->plus(BigDecimal::of($s->bandValue)->multipliedBy($s->creditHours));
            $hours = $hours->plus($s->creditHours);
        }

        return $hours->isZero() ? null : $sum->dividedBy($hours, self::SCALE, RoundingMode::HALF_UP);
    }

    /**
     * Positions by the printed figure, or by the printed average where the scheme ranks by it
     * (Grade 10). Tied students share a position; with `dense` ties (the school's answer, Q13) the
     * next position follows on (1, 2, 2, 3), with `standard` it is skipped (1, 2, 2, 4; freezes
     * made before). With the scheme's `failed_last` ranking (Q20), every student who failed comes
     * after all who passed; by default everyone is ranked together.
     */
    private function rank(Collection $students, ?SchemeRules $rules): void
    {
        $failedLast = $rules?->failedRanking === 'failed_last';
        $dense = ($rules?->tieRanking ?? 'dense') === 'dense';
        $figure = fn (StudentResultData $r) => $rules?->rankBasis === 'average' ? $r->average : $r->value;
        $ok = fn (StudentResultData $r) => $r->passed !== false;
        $key = fn (StudentResultData $r) => ($failedLast ? ($ok($r) ? '1' : '0').'|' : '').$figure($r);
        $ranked = $students->filter(fn (StudentResultData $r) => $r->isComplete() && $figure($r) !== null)
            ->sort(function ($a, $b) use ($failedLast, $figure, $ok) {
                if ($failedLast && $ok($a) !== $ok($b)) {
                    return $ok($a) ? -1 : 1;
                }

                return BigDecimal::of($figure($b))->compareTo($figure($a));
            })->values();

        $position = 0;
        $previous = null;
        foreach ($ranked as $i => $result) {
            if ($previous === null || $key($result) !== $previous) {
                $position = $dense ? $position + 1 : $i + 1;
                $previous = $key($result);
            }
            $result->position = $position;
            $result->rankedCount = $ranked->count();
        }
    }

    private function noResult(array $base, string $status, array $reasons, array $subjects): StudentResultData
    {
        return new StudentResultData(...$base, status: $status, reasons: $reasons, value: null, unrounded: null,
            classLabel: null, passed: null, position: null, rankedCount: null, recommendation: null, subjects: $subjects);
    }

    private function failLabel(SchemeRules $rules): string
    {
        $fail = collect($rules->resultBands)->where('is_fail', true)->sortBy(fn ($b) => (float) $b['min'])->first();

        return $fail['label'] ?? 'Fail';
    }

    private function lower(SchemeRules $rules): string
    {
        return $rules->resultBasis === 'gpa' ? 'GPA' : mb_strtolower($rules->resultLabel());
    }

    // --- Reading a freeze ------------------------------------------------------------------------------

    private function stored(ResultFreeze $freeze, Section $section): SectionResults
    {
        $rows = StudentResult::with(['subjects.subject'])->where('result_freeze_id', $freeze->id)
            ->where('section_id', $section->id)->current()->orderBy('id')->get();

        $students = $rows->mapWithKeys(fn (StudentResult $r) => [$r->student_id => new StudentResultData(
            studentId: $r->student_id, sectionId: $r->section_id, gradeLevelId: $r->grade_level_id, schemeId: $r->grading_scheme_id,
            basis: $r->result_basis,
            resultLabel: $r->result_basis ? ['average' => 'Average', 'point_total' => 'Point total', 'gpa' => 'GPA'][$r->result_basis] : null,
            status: $r->status, reasons: $r->status_reason ? json_decode($r->status_reason, true) : [],
            value: $r->result_value === null ? null : $this->trimTo((string) $r->result_value, $this->decimals($freeze, $r->grading_scheme_id, 'result')),
            unrounded: $r->result_unrounded === null ? null : (string) $r->result_unrounded,
            classLabel: $r->class_label, passed: $r->passed, position: $r->position, rankedCount: $r->ranked_count,
            recommendation: $r->recommendation,
            average: $r->average_value === null ? null : $this->trimTo((string) $r->average_value, $this->decimals($freeze, $r->grading_scheme_id, 'result')),
            classPending: $r->class_pending,
            subjects: $r->subjects->map(fn ($s) => new SubjectResult(
                subjectId: $s->subject_id, code: $s->subject->code, name: $s->subject->name, label: $s->report_label,
                sortOrder: $s->sort_order, offeringId: $s->subject_offering_id, inCurriculum: $s->in_curriculum,
                compulsory: $s->is_compulsory, slot: $s->elective_slot, taken: $s->taken, counted: $s->counts_in_result,
                gradingMode: $s->grading_mode, creditHours: $s->credit_hours === null ? null : (string) $s->credit_hours,
                mark: $s->mark === null ? null : (string) $s->mark,
                roundedMark: $s->rounded_mark === null ? null : $this->trimTo((string) $s->rounded_mark, $this->decimals($freeze, $r->grading_scheme_id, 'mark')),
                bandLabel: $s->band_label, bandValue: $s->band_value === null ? null : (string) $s->band_value,
                isFail: $s->is_fail, pendingQuestion: $s->pending_question, track: $s->track,
            ))->all(),
            frozen: true,
        )]);

        return new SectionResults($students, frozen: true, freeze: $freeze);
    }

    /** A stored DECIMAL shown with the freeze's own decimals, as it was calculated ("90", "3.04"). */
    private function trimTo(string $stored, int $decimals): string
    {
        return (string) BigDecimal::of($stored)->toScale($decimals, RoundingMode::UNNECESSARY);
    }

    /** @var array<string, int> */
    private array $decimalsCache = [];

    private function decimals(ResultFreeze $freeze, ?int $schemeId, string $kind): int
    {
        if (! $schemeId) {
            return 2;
        }
        $key = "{$freeze->id}:{$schemeId}:{$kind}";
        if (! isset($this->decimalsCache[$key])) {
            $settings = $freeze->schemes()->where('grading_scheme_id', $schemeId)->value('settings');
            $settings = is_string($settings) ? json_decode($settings, true) : $settings;
            $this->decimalsCache[$key] = (int) ($settings[$kind === 'mark' ? 'mark_decimals' : 'result_decimals'] ?? 2);
        }

        return $this->decimalsCache[$key];
    }
}
