<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\GradingScheme;
use App\Models\ResultFreeze;
use App\Models\ResultFreezeScheme;
use App\Models\Section;
use App\Models\StudentResult;
use App\Models\StudentSubjectResult;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
use App\Services\Results\SchemeRules;
use App\Services\Results\SectionResults;
use Illuminate\Support\Facades\DB;

/**
 * Frozen results. Locking a term calculates its results once and stores them with a copy of the
 * grading rules used, so later edits to bands or scheme settings can't change a past term. When
 * every term of the year is locked, the year is frozen too, from the frozen term marks.
 *
 * Unlocking lifts the freeze (kept as history) and the term is calculated live again. Locking it
 * again reuses the rules of the term's previous freeze (its first, unless it was recalculated), unless the Principal chooses the current
 * rules and gives a reason. A grade change applied to a locked term re-freezes that section with
 * the freeze's own rules. Every step writes its audit row in the same transaction.
 */
class ResultFreezeService
{
    public function __construct(private ResultService $results, private AuditService $audit, private AttendanceScoreService $attendance) {}

    /**
     * Lock a term and freeze its results. $rules: `previous_freeze` (the default: on a relock, the
     * rules of the term's most recent freeze, which are its first freeze's unless the Principal
     * recalculated since) or `current`, which on a relock needs a reason.
     */
    public function lock(Term $term, User $actor, string $rules = ResultFreeze::RULES_PREVIOUS, ?string $reason = null): ResultFreeze
    {
        return DB::transaction(function () use ($term, $actor, $rules, $reason) {
            $term = Term::lockForUpdate()->findOrFail($term->id);
            if (ResultFreeze::active()->where('term_id', $term->id)->exists()) {
                throw new ActionRefused("{$term->name} is already locked and its results are frozen.");
            }

            $term->update(['is_locked' => true]);

            return $this->freezeTerm($term, $actor, $rules, $reason);
        });
    }

    /** Unlock a term: its freeze, and the year's if there is one, are lifted and kept. */
    public function unlock(Term $term, User $actor): void
    {
        DB::transaction(function () use ($term, $actor) {
            $term = Term::lockForUpdate()->findOrFail($term->id);
            if (! $term->is_locked) {
                throw new ActionRefused("{$term->name} is not locked.");
            }

            $term->update(['is_locked' => false]);
            $now = now();
            $lifted = ResultFreeze::active()->where('term_id', $term->id)->get();
            $year = ResultFreeze::active()->whereNull('term_id')->where('academic_year_id', $term->academic_year_id)->get();
            ResultFreeze::whereIn('id', $lifted->merge($year)->pluck('id'))->update(['lifted_at' => $now, 'lifted_by' => $actor->id]);

            $this->audit->log($actor, 'Unlocked term results', 'Term', $term->id, [
                'term' => $term->name,
                'freezes_lifted' => $lifted->pluck('id')->all(),
                'year_freeze_lifted' => $year->pluck('id')->all(),
            ]);
        });
    }

    /**
     * After a grade change on a locked term: replace that section's stored results (old rows kept,
     * superseded) using the freeze's own rules, and the year's rows too if the year is frozen.
     */
    public function refreezeSection(Section $section, Term $term, User $actor, string $reason): void
    {
        DB::transaction(function () use ($section, $term, $actor, $reason) {
            $freeze = ResultFreeze::active()->where('term_id', $term->id)->lockForUpdate()->first();
            if (! $freeze) {
                return;
            }

            $this->supersede($freeze, $section, $reason);
            $count = $this->store($freeze, $this->results->liveSectionTerm($section, $term, $this->snapshotResolver($freeze), final: true,
                curriculumFor: $this->curriculumResolver($freeze), attendanceRules: $freeze->attendance_rules),
                $this->attendance->forSectionTerm($section, $term, $freeze->attendance_rules));

            $year = ResultFreeze::active()->whereNull('term_id')->where('academic_year_id', $term->academic_year_id)->lockForUpdate()->first();
            if ($year) {
                $this->supersede($year, $section, $reason);
                $this->store($year, $this->results->liveSectionYear($section, $this->snapshotResolver($year), $this->curriculumResolver($year)));
            }

            $this->audit->log($actor, 'Re-froze section results', 'Term', $term->id, [
                'term' => $term->name,
                'section' => $section->name,
                'reason' => $reason,
                'freeze' => $freeze->id,
                'year_freeze' => $year?->id,
                'students' => $count,
            ]);
        });
    }

    /**
     * Freeze every locked term that has no freeze (terms locked before frozen results existed),
     * oldest first. Returns how many were frozen.
     */
    public function freezeLockedTerms(User $actor): int
    {
        $terms = Term::where('is_locked', true)
            ->whereDoesntHave('resultFreezes', fn ($q) => $q->whereNull('lifted_at'))
            ->orderBy('academic_year_id')->orderBy('sequence')->get();

        foreach ($terms as $term) {
            DB::transaction(fn () => $this->freezeTerm($term, $actor, ResultFreeze::RULES_PREVIOUS, null));
        }

        return $terms->count();
    }

    /** The most recent lifted freeze of a term that is now unlocked, if it was ever locked before. */
    public function previousFreeze(Term $term): ?ResultFreeze
    {
        return ResultFreeze::where('term_id', $term->id)->whereNotNull('lifted_at')->latest('id')->first();
    }

    // ------------------------------------------------------------------------------------------------

    private function freezeTerm(Term $term, User $actor, string $rules, ?string $reason): ResultFreeze
    {
        $previous = ResultFreeze::where('term_id', $term->id)->latest('id')->first();

        if ($rules === ResultFreeze::RULES_CURRENT && $previous && trim((string) $reason) === '') {
            throw new ActionRefused('Say why the term is being recalculated with the current rules.');
        }
        $source = $previous && $rules === ResultFreeze::RULES_PREVIOUS ? ResultFreeze::RULES_PREVIOUS : ResultFreeze::RULES_CURRENT;

        $freeze = ResultFreeze::create([
            'academic_year_id' => $term->academic_year_id,
            'term_id' => $term->id,
            'rules' => $source,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'frozen_at' => now(),
            'frozen_by' => $actor->id,
        ]);
        $this->copyRules($freeze, $source === ResultFreeze::RULES_PREVIOUS ? $previous : null);

        $resolver = $this->snapshotResolver($freeze);
        $curriculum = $this->curriculumResolver($freeze);
        $sections = Section::where('academic_year_id', $term->academic_year_id)->orderBy('id')->get();
        $students = 0;
        foreach ($sections as $section) {
            $students += $this->store($freeze, $this->results->liveSectionTerm($section, $term, $resolver, final: true, curriculumFor: $curriculum,
                attendanceRules: $freeze->attendance_rules), $this->attendance->forSectionTerm($section, $term, $freeze->attendance_rules));
        }

        $details = [
            'term' => $term->name,
            'freeze' => $freeze->id,
            'rules' => $source,
            'reason' => $freeze->reason,
            'sections' => $sections->count(),
            'students' => $students,
        ];
        if ($previous && $source === ResultFreeze::RULES_CURRENT) {
            $details['snapshot_before'] = $this->describe($previous);
            $details['snapshot_after'] = $this->describe($freeze);
        }

        $year = $this->freezeYearIfComplete($term, $actor, $freeze);
        $details['year_freeze'] = $year?->id;

        $this->audit->log($actor, 'Locked term results', 'Term', $term->id, $details);

        return $freeze;
    }

    /** Once every term of the year is locked and frozen, freeze the year with the same rules as the last term. */
    private function freezeYearIfComplete(Term $term, User $actor, ResultFreeze $termFreeze): ?ResultFreeze
    {
        $terms = Term::where('academic_year_id', $term->academic_year_id)->get();
        $allFrozen = $terms->every(fn (Term $t) => $t->is_locked && ResultFreeze::active()->where('term_id', $t->id)->exists());
        if (! $allFrozen || ResultFreeze::active()->whereNull('term_id')->where('academic_year_id', $term->academic_year_id)->exists()) {
            return null;
        }

        $year = ResultFreeze::create([
            'academic_year_id' => $term->academic_year_id,
            'term_id' => null,
            'rules' => $termFreeze->rules,
            'reason' => $termFreeze->reason,
            'curriculum' => $termFreeze->curriculum,
            'attendance_rules' => $termFreeze->attendance_rules,
            'frozen_at' => now(),
            'frozen_by' => $actor->id,
        ]);
        foreach ($termFreeze->schemes as $snapshot) {
            ResultFreezeScheme::create(['result_freeze_id' => $year->id] + $snapshot->only(['grading_scheme_id', 'settings', 'mark_bands', 'result_bands']));
        }

        $resolver = $this->snapshotResolver($year);
        $curriculum = $this->curriculumResolver($year);
        foreach (Section::where('academic_year_id', $term->academic_year_id)->orderBy('id')->get() as $section) {
            $this->store($year, $this->results->liveSectionYear($section, $resolver, $curriculum));
        }

        return $year;
    }

    /**
     * Copy every scheme's rules and every grade level's curriculum into the freeze: from $from (a
     * previous freeze) where it has them, otherwise as they are now.
     */
    private function copyRules(ResultFreeze $freeze, ?ResultFreeze $from): void
    {
        $curriculum = SubjectOffering::active()->ordered()->get()->groupBy('grade_level_id')
            ->map(fn ($rows) => $rows->map->toSnapshot()->values()->all())->all();
        foreach ($from?->curriculum ?? [] as $levelId => $rows) {
            $curriculum[$levelId] = $rows;
        }
        // The attendance rules (excused days, late arrivals) the Attendance category is worked out with.
        $freeze->update(['curriculum' => $curriculum, 'attendance_rules' => $from?->attendance_rules ?? AttendanceScoreService::rules()]);

        $previous = $from ? $from->schemes->keyBy('grading_scheme_id') : collect();
        foreach (GradingScheme::orderBy('id')->get() as $scheme) {
            $snapshot = $previous->has($scheme->id)
                ? $previous[$scheme->id]->only(['settings', 'mark_bands', 'result_bands'])
                : SchemeRules::fromScheme($scheme)->toSnapshot();
            ResultFreezeScheme::create(['result_freeze_id' => $freeze->id, 'grading_scheme_id' => $scheme->id] + $snapshot);
        }
    }

    /** Maps a grade level id to the freeze's copy of its curriculum (null when the freeze has none for it). */
    private function curriculumResolver(ResultFreeze $freeze): callable
    {
        $stored = $freeze->curriculum ?? [];
        $subjects = Subject::whereIn('id', collect($stored)->flatten(1)->pluck('subject_id')->unique())->get()->keyBy('id');

        return function (int $levelId) use ($stored, $subjects) {
            $rows = $stored[$levelId] ?? $stored[(string) $levelId] ?? null;

            return $rows === null ? null : collect($rows)->sortBy('sort_order')->values()
                ->map(fn (array $row) => SubjectOffering::fromSnapshot($levelId, $row, $subjects->get($row['subject_id'])));
        };
    }

    /** Maps a scheme id to the freeze's copy of its rules. */
    private function snapshotResolver(ResultFreeze $freeze): callable
    {
        $rules = $freeze->schemes()->get()->mapWithKeys(fn ($s) => [$s->grading_scheme_id => SchemeRules::fromSnapshot($s)]);

        return function (int $schemeId) use ($rules) {
            return $rules[$schemeId] ?? SchemeRules::fromScheme(GradingScheme::findOrFail($schemeId));
        };
    }

    /** @return array<string, array> scheme code => settings and bands, for the audit log */
    private function describe(ResultFreeze $freeze): array
    {
        return $freeze->schemes()->orderBy('grading_scheme_id')->get()
            ->mapWithKeys(fn ($s) => [$s->settings['code'] => SchemeRules::fromSnapshot($s)->describe()])->all();
    }

    private function supersede(ResultFreeze $freeze, Section $section, string $reason): void
    {
        StudentResult::where('result_freeze_id', $freeze->id)->where('section_id', $section->id)->current()
            ->update(['superseded_at' => now(), 'superseded_reason' => mb_substr($reason, 0, 255)]);
    }

    /**
     * Store a section's calculated results under a freeze, with the term's attendance (days absent
     * and attendance %) when given, so the report card doesn't change if attendance is edited later.
     * Returns the number of students stored.
     */
    private function store(ResultFreeze $freeze, SectionResults $results, ?array $attendance = null): int
    {
        foreach ($results->students as $r) {
            $att = $attendance['students'][$r->studentId] ?? null;
            $row = StudentResult::create([
                'result_freeze_id' => $freeze->id,
                'student_id' => $r->studentId,
                'section_id' => $r->sectionId,
                'grade_level_id' => $r->gradeLevelId,
                'grading_scheme_id' => $r->schemeId,
                'result_basis' => $r->basis,
                'status' => $r->status,
                'status_reason' => $r->reasons === [] ? null : json_encode($r->reasons),
                'result_value' => $r->value,
                'result_unrounded' => $r->unrounded,
                'average_value' => $r->average,
                'class_label' => $r->classLabel,
                'class_pending' => $r->classPending,
                'passed' => $r->passed,
                'position' => $r->position,
                'ranked_count' => $r->rankedCount,
                'days_absent' => $attendance ? AttendanceScoreService::daysAbsent($att) : null,
                'attendance_pct' => $att && $att['pct'] !== null ? (string) \Brick\Math\BigDecimal::of($att['pct'])->toScale(2, \Brick\Math\RoundingMode::HALF_UP) : null,
                'recommendation' => $r->recommendation,
            ]);
            $now = now();
            StudentSubjectResult::insert(array_map(fn ($s) => [
                'student_result_id' => $row->id,
                'subject_id' => $s->subjectId,
                'subject_offering_id' => $s->offeringId,
                'report_label' => $s->label,
                'sort_order' => $s->sortOrder,
                'in_curriculum' => $s->inCurriculum,
                'is_compulsory' => $s->compulsory,
                'elective_slot' => $s->slot,
                'track' => $s->track,
                'taken' => $s->taken,
                'counts_in_result' => $s->counted,
                'grading_mode' => $s->gradingMode,
                'credit_hours' => $s->creditHours,
                'mark' => $s->mark,
                'rounded_mark' => $s->roundedMark,
                'band_label' => $s->bandLabel,
                'band_value' => $s->bandValue,
                'is_fail' => $s->isFail,
                'pending_question' => $s->pendingQuestion,
                'created_at' => $now,
                'updated_at' => $now,
            ], $r->subjects));
        }

        return $results->students->count();
    }
}
