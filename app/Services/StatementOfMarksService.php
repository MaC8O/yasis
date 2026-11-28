<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\AcademicYear;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\GradingScheme;
use App\Models\ReportRule;
use App\Models\ResultFreeze;
use App\Models\ResultFreezeScheme;
use App\Models\StatementOfMarks;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\Term;
use App\Models\User;
use App\Services\Results\SchemeRules;
use App\Services\Results\StudentResultData;
use App\Services\Results\SubjectResult;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * §7.2: the Statement of Marks. Made from the frozen year result only (all four terms locked), for
 * a level with a statement layout (not Grade 10, Q11) and a complete result. The Registrar prepares
 * it with the two dates; it prints only while the Principal's issuance control is on. Its first
 * print issues it: the content is saved with the year freeze it came from, so reprints never change.
 */
class StatementOfMarksService
{
    public function __construct(private ResultService $results, private AuditService $audit) {}

    /**
     * Where a statement comes from: the student's section that year, its level, the year freeze and
     * the student's frozen year result. Refused, with the reason, when no statement can be made.
     *
     * @return array{section: \App\Models\Section, level: \App\Models\GradeLevel, freeze: ResultFreeze, result: StudentResultData}
     */
    public function source(Student $student, AcademicYear $year): array
    {
        $enrollments = Enrollment::with('section.gradeLevel')->where('student_id', $student->id)
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))->latest('id')->get();
        $enrollment = $enrollments->firstWhere('status', '!=', 'Withdrawn');
        if (! $enrollment) {
            throw new ActionRefused($enrollments->isNotEmpty()
                ? "{$student->name} withdrew during {$year->year_label}, so there is no year result and no Statement of Marks."
                : "{$student->name} was not enrolled in {$year->year_label}.");
        }

        $section = $enrollment->section;
        $level = $section->gradeLevel;
        if (! $level) {
            throw new ActionRefused("{$section->name} has no grade level, so it has no Statement of Marks.");
        }
        if (! $level->statement_layout) {
            throw new ActionRefused($level->code === '10'
                ? 'No Statement of Marks for Grade 10 until the school answers Q11 (whether it issues one, and how the DHSC result is recorded).'
                : "{$level->name} has no Statement of Marks.");
        }

        $freeze = $this->results->yearFreeze($year->id);
        if (! $freeze) {
            $open = Term::where('academic_year_id', $year->id)->orderBy('sequence')->get()
                ->reject(fn (Term $t) => $t->is_locked && ResultFreeze::active()->where('term_id', $t->id)->exists());
            throw new ActionRefused("The Statement of Marks is made from the frozen year result, once the Principal has locked every term of {$year->year_label}. "
                .($open->isEmpty() ? 'The year is not frozen yet.' : 'Not locked yet: '.$open->pluck('name')->implode(', ').'.'));
        }

        $result = $this->results->sectionYear($section)->for($student->id);
        if (! $result) {
            throw new ActionRefused("{$student->name} has no frozen year result in {$section->name}.");
        }
        if (! $result->isComplete()) {
            throw new ActionRefused("{$student->name}'s {$year->year_label} result is not complete, so there is no Statement of Marks: ".implode(' ', $result->reasons));
        }

        return ['section' => $section, 'level' => $level, 'freeze' => $freeze, 'result' => $result];
    }

    /** The statement as printed, from the frozen year result. */
    public function content(Student $student, AcademicYear $year, string $examinationDate, CarbonInterface $issueDate): array
    {
        ['level' => $level, 'freeze' => $freeze, 'result' => $r] = $this->source($student, $year);

        $subjects = collect($r->subjects)->filter(fn (SubjectResult $s) => $s->inCurriculum)->sortBy('sortOrder')->values();
        $content = [
            'layout' => $level->statement_layout,
            'result_freeze_id' => $freeze->id,
            'school_year' => $year->year_label,
            'name' => mb_strtoupper($student->name),
            'grade' => $level->code,
            'date_of_birth' => $student->date_of_birth ? mb_strtoupper($student->date_of_birth->format('M d, Y')) : '',
            'examination_centre' => ReportRule::examinationCentre(),
            'examination_date' => mb_strtoupper(trim($examinationDate)),
            'issue_date' => mb_strtoupper($issueDate->format('F d, Y')),
            'result' => $r->passed ? 'PASS' : 'FAIL',
            'class_of_pass' => $r->passed && $r->classLabel ? mb_strtoupper($r->classLabel) : '—',
        ];

        if ($level->statement_layout === 'kg9') {
            // Every counted subject, so the listed scores make up the accumulative score (Q21), then
            // SUPW with its word (Grades 5-9, Q5); the first four (Moral, Myanmar, English,
            // Mathematics) in the left column, as on the samples.
            $rows = $subjects->filter(fn (SubjectResult $s) => $s->counted && $s->taken)
                ->map(fn (SubjectResult $s) => ['label' => $s->label, 'score' => $s->roundedMark])
                ->merge($subjects->filter(fn (SubjectResult $s) => $s->usesCompletion() && $s->taken)
                    ->map(fn (SubjectResult $s) => ['label' => $s->label, 'score' => $s->bandLabel ?? '—', 'completion' => true]))
                ->values();

            return $content + ['left' => $rows->take(4)->all(), 'right' => $rows->slice(4)->values()->all(), 'accumulative' => $r->value];
        }

        $row = fn (SubjectResult $s) => [
            'label' => $s->label,
            'score' => $s->taken ? ($s->bandLabel ?? '') : '-',
            'hours' => in_array($s->gradingMode, ['pass_fail', 'completion'], true) || $s->creditHours === null ? '-' : $this->number($s->creditHours),
        ] + ($s->usesCompletion() ? ['completion' => true] : []);
        // Track subjects (or, in results frozen before tracks, slot subjects) are the optional ones.
        $optional = fn (SubjectResult $s) => $s->track !== null || $s->slot !== null;
        $rules = $this->rulesFor($freeze, $r->schemeId);

        return $content + [
            'compulsory' => $subjects->reject($optional)->map($row)->values()->all(),
            'optional' => $subjects->filter($optional)->map($row)->values()->all(),
            'gpa' => $r->value,
            'conversion' => $this->conversion($rules),
            'classes' => $this->classes($rules),
        ];
    }

    /**
     * SUPW completed after the year was frozen: the issued statements of that student and year show
     * COMPLETE instead of INCOMPLETE, changed in place (nothing else on them depends on SUPW).
     * Returns how many statements changed.
     */
    public function completeSupw(int $studentId, int $academicYearId): int
    {
        $changed = 0;
        $statements = StatementOfMarks::where('academic_year_id', $academicYearId)->whereNotNull('issued_at')
            ->whereHas('documentRequest', fn ($q) => $q->where('student_id', $studentId))->get();
        foreach ($statements as $statement) {
            $content = $statement->content;
            $touched = false;
            foreach (['left', 'right', 'compulsory', 'optional'] as $list) {
                foreach ($content[$list] ?? [] as $i => $row) {
                    if (($row['completion'] ?? false) && ($row['score'] ?? null) === 'INCOMPLETE') {
                        $content[$list][$i]['score'] = 'COMPLETE';
                        $touched = true;
                    }
                }
            }
            if ($touched) {
                $statement->update(['content' => $content]);
                $changed++;
            }
        }

        return $changed;
    }

    /** Prepare a statement as a Draft, with the two dates the Registrar enters. Audited. */
    public function prepare(Student $student, AcademicYear $year, string $examinationDate, string $issueDate, User $actor): StatementOfMarks
    {
        return DB::transaction(function () use ($student, $year, $examinationDate, $issueDate, $actor) {
            ['section' => $section, 'level' => $level] = $this->source($student, $year);

            $draft = StatementOfMarks::where('academic_year_id', $year->id)->whereNull('issued_at')
                ->whereHas('documentRequest', fn ($q) => $q->where('student_id', $student->id))->lockForUpdate()->exists();
            if ($draft) {
                throw new ActionRefused("{$student->name} already has a draft Statement of Marks for {$year->year_label}. Change its dates there, or print it.");
            }

            $doc = DocumentRequest::create([
                'student_id' => $student->id, 'type' => DocumentRequest::STATEMENT_OF_MARKS, 'status' => 'Draft',
                'prepared_by' => $actor->staffProfile?->id ?? $actor->id,
            ]);
            $statement = StatementOfMarks::create([
                'document_request_id' => $doc->id, 'academic_year_id' => $year->id, 'section_id' => $section->id,
                'grade_level_id' => $level->id, 'layout' => $level->statement_layout,
                'examination_date' => trim($examinationDate), 'issue_date' => $issueDate,
            ]);

            $this->audit->log($actor, 'Prepared statement of marks', 'DocumentRequest', $doc->id, [
                'student' => $student->student_id_number, 'school_year' => $year->year_label, 'section' => $section->name,
                'examination_date' => $statement->examination_date, 'issue_date' => $statement->issue_date->toDateString(),
            ]);

            return $statement;
        });
    }

    /** Change a draft's dates. An issued statement never changes. Audited with before and after. */
    public function updateDates(StatementOfMarks $statement, string $examinationDate, string $issueDate, User $actor): void
    {
        DB::transaction(function () use ($statement, $examinationDate, $issueDate, $actor) {
            $statement = StatementOfMarks::lockForUpdate()->findOrFail($statement->id);
            if ($statement->isIssued()) {
                throw new ActionRefused('This Statement of Marks has been issued, so it can no longer change. Prepare a new one if needed.');
            }

            $statement->fill(['examination_date' => trim($examinationDate), 'issue_date' => $issueDate]);
            if (! $statement->isDirty()) {
                throw new ActionRefused('Nothing was changed.');
            }
            $changes = collect($statement->getDirty())->map(fn ($to, $field) => [
                'from' => $field === 'issue_date' ? $statement->getOriginal($field)?->toDateString() : $statement->getOriginal($field),
                'to' => $field === 'issue_date' ? Carbon::parse($to)->toDateString() : $to,
            ])->all();
            $statement->save();

            $this->audit->log($actor, 'Changed statement of marks dates', 'DocumentRequest', $statement->document_request_id, ['changes' => $changes]);
        });
    }

    /** A draft's content, for the watermarked preview. Nothing is saved. */
    public function preview(StatementOfMarks $statement): array
    {
        $statement->loadMissing(['documentRequest.student', 'academicYear']);

        return $this->content($statement->documentRequest->student, $statement->academicYear, $statement->examination_date, $statement->issue_date);
    }

    /**
     * Print a statement while the Principal's issuance control is on. The first print issues it:
     * the checks run again and the content is saved with its year freeze. Reprints use that copy.
     */
    public function issue(StatementOfMarks $statement, User $actor): array
    {
        if (! self::issuanceEnabled()) {
            throw new ActionRefused(self::ISSUANCE_OFF);
        }

        return DB::transaction(function () use ($statement, $actor) {
            $statement = StatementOfMarks::with(['documentRequest.student', 'academicYear'])->lockForUpdate()->findOrFail($statement->id);
            if ($statement->isIssued()) {
                $this->audit->log($actor, 'Re-printed statement of marks', 'DocumentRequest', $statement->document_request_id);

                return $statement->content;
            }

            $content = $this->preview($statement);
            $statement->update([
                'content' => $content, 'result_freeze_id' => $content['result_freeze_id'],
                'examination_centre' => $content['examination_centre'], 'issued_at' => now(), 'issued_by' => $actor->id,
            ]);
            $statement->documentRequest->update(['status' => 'Printed', 'generated_at' => now()]);

            $this->audit->log($actor, 'Issued statement of marks', 'DocumentRequest', $statement->document_request_id, [
                'student' => $statement->documentRequest->student->student_id_number, 'school_year' => $content['school_year'],
                'result' => $content['result'], 'class_of_pass' => $content['class_of_pass'], 'result_freeze' => $content['result_freeze_id'],
            ]);

            return $content;
        });
    }

    /** An issued statement whose year results were re-frozen since (an approved grade change). */
    public function isOutdated(StatementOfMarks $statement): bool
    {
        return $statement->isIssued() && $this->results->yearFreeze($statement->academic_year_id)?->id !== $statement->result_freeze_id;
    }

    public const ISSUANCE_OFF = 'Statements of Marks print once the Principal turns on "Transcript & certificate issuance" in Setup & Controls.';

    public static function issuanceEnabled(): bool
    {
        return SystemSetting::get('transcript_issuance_enabled', '0') === '1';
    }

    // --- The Grade 11-12 footer ---------------------------------------------------------------------

    /** The scheme the year was frozen with (its saved copy), so the footer matches how the GPA was worked out. */
    private function rulesFor(ResultFreeze $freeze, ?int $schemeId): SchemeRules
    {
        $snapshot = ResultFreezeScheme::where('result_freeze_id', $freeze->id)->where('grading_scheme_id', $schemeId)->first();

        return $snapshot ? SchemeRules::fromSnapshot($snapshot) : $this->results->rules(GradingScheme::findOrFail($schemeId));
    }

    /** Each letter's grade value and its points for a 3-hour and a 2-hour subject, highest first. */
    private function conversion(SchemeRules $rules): array
    {
        return collect($rules->markBands)->sortByDesc(fn ($b) => (float) $b['min'])->map(function ($b) {
            $value = BigDecimal::of($b['value'] ?? '0');

            return [
                'letter' => $b['label'],
                'value' => $this->number((string) $value),
                'hours3' => $this->number((string) $value->multipliedBy(3)->toScale(2, RoundingMode::HALF_UP)),
                'hours2' => $this->number((string) $value->multipliedBy(2)->toScale(2, RoundingMode::HALF_UP)),
            ];
        })->values()->all();
    }

    /** The class of pass for each GPA range, highest first: "3.68-4.00", and the fail band as "below 2.00". */
    private function classes(SchemeRules $rules): array
    {
        $bands = collect($rules->resultBands)->sortByDesc(fn ($b) => (float) $b['min'])->values();
        $lowestPass = $bands->reject(fn ($b) => $b['is_fail'])->last();
        $fmt = fn ($v) => (string) BigDecimal::of($v)->toScale(2, RoundingMode::HALF_UP);

        return $bands->map(fn ($b) => [
            'label' => $b['label'],
            'range' => $b['is_fail'] && $lowestPass ? 'below '.$fmt($lowestPass['min']) : $fmt($b['min']).'-'.$fmt($b['max']),
        ])->all();
    }

    /** "3.00" as "3", "3.67" as is, "2.70" as "2.7". */
    private function number(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
