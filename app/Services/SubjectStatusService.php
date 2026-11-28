<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\ReportRule;
use App\Models\ResultFreeze;
use App\Models\Section;
use App\Models\StudentResult;
use App\Models\StudentSubjectResult;
use App\Models\Subject;
use App\Models\SubjectStatus;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SUPW's words (the `completion` grading mode, Grades 5-12; the school's answer to Q5): Pass,
 * Incomplete or Complete per student and term, entered by the subject's teacher, audited.
 *
 * While the term is open any word can be set. Once it is locked, only Incomplete can become
 * Complete: the school gives Complete when the work is finished later. The frozen word changes
 * in place, and, for the year's last term, the year's frozen word and the issued Statements of
 * Marks too. SUPW counts in no average or GPA, so nothing else is recalculated; the year's
 * promotion recommendation changes only when the FQ3 rule makes Incomplete stop promotion.
 */
class SubjectStatusService
{
    public function __construct(private AuditService $audit, private StatementOfMarksService $statements) {}

    /** student id => word, for the section's active roster in a term. */
    public function words(Section $section, int $subjectId, Term $term): array
    {
        return SubjectStatus::where('term_id', $term->id)->where('subject_id', $subjectId)
            ->whereIn('student_id', $this->roster($section))->pluck('status', 'student_id')->all();
    }

    /**
     * Save the submitted words (student id => Pass|Incomplete|Complete; blanks are skipped).
     * Returns how many changed.
     *
     * @param  array<int|string, ?string>  $words
     */
    public function save(Section $section, Subject $subject, Term $term, array $words, User $actor): int
    {
        return DB::transaction(function () use ($section, $subject, $term, $words, $actor) {
            $roster = $this->roster($section)->all();
            $existing = SubjectStatus::where('term_id', $term->id)->where('subject_id', $subject->id)
                ->whereIn('student_id', $roster)->lockForUpdate()->get()->keyBy('student_id');
            $changes = [];
            $completedLate = [];

            foreach ($words as $studentId => $word) {
                $studentId = (int) $studentId;
                if ($word === null || $word === '' || ! in_array($studentId, $roster, true)) {
                    continue;
                }
                if (! in_array($word, SubjectStatus::STATUSES, true)) {
                    throw new ActionRefused('SUPW is Pass, Incomplete or Complete.');
                }
                $row = $existing->get($studentId);
                if ($row?->status === $word) {
                    continue;
                }
                if ($term->is_locked && ! ($row?->status === 'Incomplete' && $word === 'Complete')) {
                    throw new ActionRefused("{$term->name} is locked: only Incomplete can still change, to Complete.");
                }

                $changes[] = ['student_id' => $studentId, 'from' => $row?->status, 'to' => $word];
                $row ??= new SubjectStatus(['student_id' => $studentId, 'subject_id' => $subject->id, 'term_id' => $term->id]);
                $row->fill(['section_id' => $section->id, 'status' => $word, 'recorded_by' => $actor->id])->save();
                if ($term->is_locked) {
                    $completedLate[] = $studentId;
                }
            }

            if ($changes === []) {
                return 0;
            }

            $frozen = [];
            foreach ($completedLate as $studentId) {
                $frozen[$studentId] = $this->completeFrozen($section, $subject, $term, $studentId);
            }

            $this->audit->log($actor, $term->is_locked ? 'Completed SUPW after the term was locked' : 'Recorded SUPW words', 'Section', $section->id, [
                'subject' => $subject->code, 'term' => $term->name, 'changes' => $changes,
            ] + ($frozen ? ['frozen_rows_updated' => $frozen] : []));

            return count($changes);
        });
    }

    /**
     * A late Complete on a locked term: the term's frozen word, and for the year's last term the
     * year's frozen word, its recommendation (FQ3 rule only) and the issued statements.
     *
     * @return array{term: int, year: int, statements: int}
     */
    private function completeFrozen(Section $section, Subject $subject, Term $term, int $studentId): array
    {
        $done = ['term' => 0, 'year' => 0, 'statements' => 0];
        $termFreeze = ResultFreeze::active()->where('term_id', $term->id)->latest('id')->first();
        if ($termFreeze) {
            $done['term'] = $this->setFrozenWord($termFreeze, $section, $subject, $studentId);
        }

        $last = Term::where('academic_year_id', $term->academic_year_id)->orderByDesc('sequence')->value('id');
        $yearFreeze = ResultFreeze::active()->whereNull('term_id')->where('academic_year_id', $term->academic_year_id)->latest('id')->first();
        if ($last === $term->id && $yearFreeze) {
            $done['year'] = $this->setFrozenWord($yearFreeze, $section, $subject, $studentId);
            if (ReportRule::value('supw_incomplete_blocks_promotion', 'no') === 'yes') {
                $result = StudentResult::where('result_freeze_id', $yearFreeze->id)->where('student_id', $studentId)
                    ->where('section_id', $section->id)->current()->first();
                if ($result?->passed && $result->recommendation === 'Retain') {
                    $level = GradeLevel::find($result->grade_level_id);
                    $top = $level && ! GradeLevel::active()->where('sort_order', '>', $level->sort_order)->exists();
                    $result->update(['recommendation' => $top ? 'Graduate' : 'Promote']);
                }
            }
            $done['statements'] = $this->statements->completeSupw($studentId, $term->academic_year_id);
        }

        return $done;
    }

    private function setFrozenWord(ResultFreeze $freeze, Section $section, Subject $subject, int $studentId): int
    {
        $resultIds = StudentResult::where('result_freeze_id', $freeze->id)->where('student_id', $studentId)
            ->where('section_id', $section->id)->current()->pluck('id');

        return StudentSubjectResult::whereIn('student_result_id', $resultIds)->where('subject_id', $subject->id)
            ->where('grading_mode', 'completion')->where('band_label', 'INCOMPLETE')
            ->update(['band_label' => 'COMPLETE', 'updated_at' => now()]);
    }

    private function roster(Section $section)
    {
        return Enrollment::where('section_id', $section->id)->where('status', '!=', 'Withdrawn')->pluck('student_id')->map(fn ($id) => (int) $id);
    }
}
