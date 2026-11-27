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

    /** For the VP and Principal dashboards: how many students are below the minimum in the current term. */
    public function flagSummary(): ?array
    {
        $term = \App\Http\Controllers\Principal\GovernanceController::currentTermFor();
        if (! $term) {
            return null;
        }

        return ['term' => $term, 'minimum' => ReportRule::minAttendancePct(), 'count' => count($this->belowMinimumAttendance($term))];
    }

    /**
     * Students below the minimum attendance for the term (§8, Q16), on the section's daily records.
     * Flagged only: nothing is blocked.
     *
     * @param  Collection<int, Section>|null  $sections  limit to these (default: every section of the term's year)
     * @return list<array{student_id: int, student: string, section: string, section_id: int, pct: string, attended: int, counted: int}>
     */
    public function belowMinimumAttendance(Term $term, ?Collection $sections = null): array
    {
        $minimum = BigDecimal::of(ReportRule::minAttendancePct());
        $sections ??= Section::where('academic_year_id', $term->academic_year_id)->whereNull('archived_at')->orderByGradeLevel()->orderBy('name')->get();
        $names = Student::whereIn('id', Enrollment::whereIn('section_id', $sections->pluck('id'))->pluck('student_id'))->pluck('name', 'id');

        $list = [];
        foreach ($sections as $section) {
            $roster = $this->roster($section);
            foreach ($this->attendance->forSectionTerm($section, $term)['students'] as $studentId => $row) {
                if ($row['pct'] !== null && $roster->contains($studentId) && BigDecimal::of($row['pct'])->isLessThan($minimum)) {
                    $list[] = [
                        'student_id' => $studentId, 'student' => $names[$studentId] ?? '?', 'section' => $section->name, 'section_id' => $section->id,
                        'pct' => (string) BigDecimal::of($row['pct'])->toScale(1, \Brick\Math\RoundingMode::HALF_UP),
                        'attended' => $row['attended'], 'counted' => $row['counted'],
                    ];
                }
            }
        }

        return $list;
    }
}
