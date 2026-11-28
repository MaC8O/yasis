<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\Enrollment;
use App\Models\PersonalityRating;
use App\Models\ReportAcknowledgement;
use App\Models\ReportRule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What people do with report cards (§7.1, §8): the homeroom teacher's personality ratings and
 * confirmation, the guardian's acknowledgement, the Principal's release as sign-off, and the
 * attendance eligibility list. Every change is audited in the same transaction.
 */
class ReportCardActionsService
{
    public function __construct(private AuditService $audit, private AttendanceScoreService $attendance) {}

    /** The section's current roster (student ids). */
    public function roster(Section $section): Collection
    {
        return Enrollment::where('section_id', $section->id)->where('status', 'Active')->pluck('student_id');
    }

    /**
     * Save the homeroom teacher's ratings for a term: student id => trait => A|B|C (blank leaves a
     * trait unrated). Editable until the term is locked.
     *
     * @param  array<int, array<string, ?string>>  $ratings
     */
    public function savePersonality(Section $section, Term $term, array $ratings, User $actor): int
    {
        if ($term->is_locked) {
            throw new ActionRefused("{$term->name} is locked, so its personality ratings can no longer change.");
        }
        // Q14: rated once a year, on the last term.
        $last = Term::where('academic_year_id', $term->academic_year_id)->orderByDesc('sequence')->first();
        if (ReportRule::personalityOncePerYear() && $last && $last->id !== $term->id) {
            throw new ActionRefused("Personality traits are rated once a year, on {$last->name}.");
        }

        return DB::transaction(function () use ($section, $term, $ratings, $actor) {
            $roster = $this->roster($section)->all();
            $changes = [];
            foreach ($ratings as $studentId => $traits) {
                if (! in_array((int) $studentId, $roster, true)) {
                    continue;
                }
                foreach ((array) $traits as $trait => $rating) {
                    if (! array_key_exists($trait, PersonalityRating::TRAITS) || $rating === null || $rating === '') {
                        continue;
                    }
                    $row = PersonalityRating::firstOrNew(['student_id' => $studentId, 'term_id' => $term->id, 'trait' => $trait]);
                    if ($row->rating === $rating) {
                        continue;
                    }
                    $changes[] = ['student_id' => (int) $studentId, 'trait' => $trait, 'from' => $row->rating, 'to' => $rating];
                    $row->fill(['rating' => $rating, 'rated_by' => $actor->id])->save();
                }
            }
            if ($changes) {
                $this->audit->log($actor, 'Rated personality traits', 'Section', $section->id, ['term' => $term->name, 'changes' => $changes]);
            }

            return count($changes);
        });
    }

    /** The homeroom teacher confirms the term's reports for the section's students (once the term is locked). */
    public function confirm(Section $section, Term $term, User $actor): int
    {
        if (! $term->is_locked) {
            throw new ActionRefused("Reports can be confirmed once the Principal has locked {$term->name}, so you confirm the final figures.");
        }

        return DB::transaction(function () use ($section, $term, $actor) {
            $already = ReportAcknowledgement::current()->where('term_id', $term->id)->where('role', ReportAcknowledgement::CLASS_TEACHER)
                ->where('section_id', $section->id)->pluck('student_id');
            $toConfirm = $this->roster($section)->diff($already)->values();
            foreach ($toConfirm as $studentId) {
                ReportAcknowledgement::create([
                    'term_id' => $term->id, 'student_id' => $studentId, 'section_id' => $section->id,
                    'role' => ReportAcknowledgement::CLASS_TEACHER, 'user_id' => $actor->id, 'acknowledged_at' => now(),
                ]);
            }
            if ($toConfirm->isEmpty()) {
                throw new ActionRefused("Every report in {$section->name} for {$term->name} is already confirmed.");
            }
            $this->audit->log($actor, 'Confirmed term reports', 'Section', $section->id, ['term' => $term->name, 'students' => $toConfirm->all()]);

            return $toConfirm->count();
        });
    }

    /** The guardian presses "I acknowledge this report" (released terms only, once per child and term). */
    public function guardianAcknowledge(Student $student, Term $term, User $guardian): void
    {
        if (! $term->results_released) {
            throw new ActionRefused("{$term->name}'s report has not been released yet.");
        }
        DB::transaction(function () use ($student, $term, $guardian) {
            $exists = ReportAcknowledgement::current()->where('term_id', $term->id)->where('student_id', $student->id)
                ->where('role', ReportAcknowledgement::GUARDIAN)->lockForUpdate()->exists();
            if ($exists) {
                throw new ActionRefused("{$student->name}'s {$term->name} report is already acknowledged.");
            }
            $sectionId = Enrollment::where('student_id', $student->id)->where('status', '!=', 'Withdrawn')
                ->whereHas('section', fn ($q) => $q->where('academic_year_id', $term->academic_year_id))->latest('id')->value('section_id');
            $row = ReportAcknowledgement::create([
                'term_id' => $term->id, 'student_id' => $student->id, 'section_id' => $sectionId,
                'role' => ReportAcknowledgement::GUARDIAN, 'user_id' => $guardian->id, 'acknowledged_at' => now(),
            ]);
            $this->audit->log($guardian, 'Acknowledged term report', 'Student', $student->id, ['term' => $term->name, 'acknowledgement' => $row->id]);
        });
    }

    /**
     * The Principal's release of a term's results is the Principal's sign-off on every card (one
     * row per term). Turning the release off withdraws it.
     */
    public function setPrincipalRelease(Term $term, bool $released, User $actor): void
    {
        $current = ReportAcknowledgement::current()->where('term_id', $term->id)->whereNull('student_id')
            ->where('role', ReportAcknowledgement::PRINCIPAL)->get();

        if ($released && $current->isEmpty()) {
            $row = ReportAcknowledgement::create([
                'term_id' => $term->id, 'role' => ReportAcknowledgement::PRINCIPAL, 'user_id' => $actor->id, 'acknowledged_at' => now(),
            ]);
            $this->audit->log($actor, 'Signed off term reports (results released)', 'Term', $term->id, ['term' => $term->name, 'acknowledgement' => $row->id]);
        } elseif (! $released && $current->isNotEmpty()) {
            ReportAcknowledgement::whereIn('id', $current->pluck('id'))->update(['withdrawn_at' => now(), 'withdrawn_by' => $actor->id]);
            $this->audit->log($actor, 'Withdrew term report sign-off (results unreleased)', 'Term', $term->id, ['term' => $term->name]);
        }
    }

    /**
     * Sections in the term's school year whose reports aren't all confirmed by the class teacher,
     * for the Principal's warning before releasing.
     *
     * @return list<array{section: string, unconfirmed: int, total: int}>
     */
    public function unconfirmedSections(Term $term): array
    {
        $list = [];
        $sections = Section::where('academic_year_id', $term->academic_year_id)->whereNull('archived_at')->orderByGradeLevel()->orderBy('name')->get();
        foreach ($sections as $section) {
            $roster = $this->roster($section);
            if ($roster->isEmpty()) {
                continue;
            }
            $confirmed = ReportAcknowledgement::current()->where('term_id', $term->id)->where('role', ReportAcknowledgement::CLASS_TEACHER)
                ->whereIn('student_id', $roster)->distinct()->count('student_id');
            if ($confirmed < $roster->count()) {
                $list[] = ['section' => $section->name, 'unconfirmed' => $roster->count() - $confirmed, 'total' => $roster->count()];
            }
        }

        return $list;
    }

    /** For the VP and Principal dashboards: how many students are below the minimum, and above the subject-absence limit, now. */
    public function flagSummary(): ?array
    {
        $term = \App\Http\Controllers\Principal\GovernanceController::currentTermFor();
        if (! $term) {
            return null;
        }

        return ['term' => $term, 'minimum' => ReportRule::minAttendancePct(), 'yearly' => self::checksYear(), 'whole_period' => self::wholePeriod(),
            'count' => count($this->belowMinimumAttendance($term)), 'subject_count' => count($this->aboveSubjectAbsence($term)),
            'subject_limit' => self::subjectAbsenceLimit()];
    }

    /** Q16: the 80% rule is checked over the school year to date (the default), or per term. */
    public static function checksYear(): bool
    {
        return ReportRule::value('attendance_check_period', 'year') === 'year';
    }

    public static function subjectAbsenceLimit(): int
    {
        return (int) ReportRule::value('max_subject_absence_pct', '10');
    }

    /**
     * `flag_basis`: whole_period (the default) flags a student only once the breach is certain for
     * the whole term or year; so_far flags on the days recorded so far.
     */
    public static function wholePeriod(): bool
    {
        return ReportRule::value('flag_basis', 'whole_period') === 'whole_period';
    }

    /**
     * Students below the minimum attendance (§8, Q16), on the section's daily records, over the
     * school year up to and including $term (late arrivals converted on the year's total), or over
     * $term alone when the rule says so. With the whole-period basis, a student is flagged once their
     * absences are more than (100 − minimum)% of the year's (or term's) school days, so the minimum
     * can no longer be reached; otherwise when the attendance so far is below the minimum. Flagged
     * only: nothing is blocked.
     *
     * @param  Collection<int, Section>|null  $sections  limit to these (default: every section of the term's year)
     * @return list<array{student_id: int, student: string, section: string, section_id: int, pct: string, absent_pct: string, attended: int, counted: int, late_absences: int, absences: int, period_days: ?int, limit: ?int}>
     */
    public function belowMinimumAttendance(Term $term, ?Collection $sections = null): array
    {
        $minimum = ReportRule::minAttendancePct();
        $yearly = self::checksYear();
        if (self::wholePeriod()) {
            $days = $yearly ? $this->schoolDays()->inYear($term->academic_year_id) : $this->schoolDays()->inTerm($term);

            return $this->flagged($term, $sections, $yearly, $days, 100 - $minimum);
        }
        $min = BigDecimal::of($minimum);

        return $this->flagged($term, $sections, $yearly, null, null,
            fn (array $row) => $row['pct'] !== null && BigDecimal::of($row['pct'])->isLessThan($min));
    }

    /**
     * Students with more than the subject-absence limit (default 10%) absent in a subject in the
     * term: with the whole-period basis, more than that share of the term's school days; otherwise
     * of the days recorded so far. ISMS has no subject attendance, so it is worked out from the
     * section's daily attendance (`subject_attendance_source` = daily, FQ2) and is the same for
     * every subject. Flagged only.
     */
    public function aboveSubjectAbsence(Term $term, ?Collection $sections = null): array
    {
        $limit = self::subjectAbsenceLimit();
        if (self::wholePeriod()) {
            return $this->flagged($term, $sections, false, $this->schoolDays()->inTerm($term), $limit);
        }
        $max = BigDecimal::of($limit);

        return $this->flagged($term, $sections, false, null, null,
            fn (array $row) => $row['pct'] !== null && BigDecimal::of(100)->minus($row['pct'])->isGreaterThan($max));
    }

    private function schoolDays(): SchoolDays
    {
        return app(SchoolDays::class);
    }

    /**
     * $periodDays and $absencePct (whole period): flagged when absences > $absencePct% of the
     * period's school days. Otherwise $matches decides on the row so far.
     */
    private function flagged(Term $term, ?Collection $sections, bool $yearToDate, ?int $periodDays, ?int $absencePct, ?callable $matches = null): array
    {
        $sections ??= Section::where('academic_year_id', $term->academic_year_id)->whereNull('archived_at')->orderByGradeLevel()->orderBy('name')->get();
        $names = Student::whereIn('id', Enrollment::whereIn('section_id', $sections->pluck('id'))->pluck('student_id'))->pluck('name', 'id');
        $allowed = $periodDays !== null ? BigDecimal::of($periodDays)->multipliedBy($absencePct)->dividedBy(100, 4, RoundingMode::HALF_UP) : null;
        $matches ??= fn (array $row) => BigDecimal::of($row['counted'] - $row['attended'])->isGreaterThan($allowed);

        $list = [];
        foreach ($sections as $section) {
            $roster = $this->roster($section);
            $rows = $yearToDate ? $this->attendance->forSectionYearTo($section, $term) : $this->attendance->forSectionTerm($section, $term);
            foreach ($rows['students'] as $studentId => $row) {
                if ($roster->contains($studentId) && $row['counted'] > 0 && $matches($row)) {
                    $list[] = [
                        'student_id' => $studentId, 'student' => $names[$studentId] ?? '?', 'section' => $section->name, 'section_id' => $section->id,
                        'pct' => (string) BigDecimal::of($row['pct'])->toScale(1, RoundingMode::HALF_UP),
                        'absent_pct' => (string) BigDecimal::of(100)->minus($row['pct'])->toScale(1, RoundingMode::HALF_UP),
                        'attended' => $row['attended'], 'counted' => $row['counted'], 'late_absences' => $row['late_absences'],
                        'absences' => $row['counted'] - $row['attended'],
                        'period_days' => $periodDays,
                        // The most absences allowed without breaking the limit.
                        'limit' => $allowed?->toScale(0, RoundingMode::DOWN)->toInt(),
                    ];
                }
            }
        }

        return $list;
    }
}
