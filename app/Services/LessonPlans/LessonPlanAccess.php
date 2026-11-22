<?php

namespace App\Services\LessonPlans;

use App\Models\AcademicYear;
use App\Models\LessonPlan;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\LessonPlanPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who may see or change which lesson plan. Used by every lesson-plan route and by the service.
 *
 * - Read: anyone with `lesson_plans.view_all` (VP, Principal, Admin), and a teacher for their
 *   own plans from any year (read-only once they no longer teach that class).
 * - Change (new version, edit, delete) and create: a teacher with `lesson_plans.submit`, for their
 *   own plan, while they teach that section + subject in a non-archived section of the active
 *   school year.
 * - Review: `lesson_plans.review` (VP), never on a plan they own.
 * - Students and guardians hold none of these, so they are refused everywhere (403).
 */
class LessonPlanAccess
{
    public function canViewAll(User $user): bool
    {
        return $user->can(LessonPlanPermissions::VIEW_ALL);
    }

    public function canSubmit(User $user): bool
    {
        return $user->can(LessonPlanPermissions::SUBMIT);
    }

    public function canView(User $user, LessonPlan $plan): bool
    {
        return $this->canViewAll($user) || ($this->canSubmit($user) && $this->owns($user, $plan));
    }

    public function canChange(User $user, LessonPlan $plan): bool
    {
        return $this->canSubmit($user) && $this->owns($user, $plan)
            && $this->teachesNow($user, $plan->section_id, $plan->subject_id);
    }

    public function canReview(User $user, LessonPlan $plan): bool
    {
        return $user->can(LessonPlanPermissions::REVIEW) && ! $this->owns($user, $plan);
    }

    public function assertCanView(User $user, LessonPlan $plan): void
    {
        if (! $this->canView($user, $plan)) {
            throw new AuthorizationException('You do not have access to this lesson plan.');
        }
    }

    public function assertCanChange(User $user, LessonPlan $plan): void
    {
        if (! $this->canChange($user, $plan)) {
            throw new AuthorizationException('You can only change your own lesson plans for classes you teach this school year.');
        }
    }

    public function assertCanReview(User $user, LessonPlan $plan): void
    {
        if (! $this->canReview($user, $plan)) {
            throw new AuthorizationException('Only the Vice Principal can review lesson plans, and not their own.');
        }
    }

    /** Plans $user may open in a list. */
    public function visiblePlans(User $user): Builder
    {
        if ($this->canViewAll($user)) {
            return LessonPlan::query();
        }
        if ($this->canSubmit($user)) {
            return LessonPlan::where('teacher_id', $user->id);
        }

        return LessonPlan::whereRaw('1 = 0');
    }

    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    /** The teacher's classes (section + subject) in the active year — what they can plan for now. */
    public function currentAssignments(User $user): Collection
    {
        $year = $this->activeYear();
        if (! $year || ! $this->canSubmit($user)) {
            return collect();
        }

        return TeachingAssignment::with(['section', 'subject'])
            ->where('teacher_id', $user->id)
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id)->whereNull('archived_at'))
            ->get()
            ->sortBy(fn ($a) => $a->subject?->name.' '.$a->section?->name)
            ->values();
    }

    public function teachesNow(User $user, int $sectionId, int $subjectId): bool
    {
        $year = $this->activeYear();

        return $year !== null && TeachingAssignment::where('teacher_id', $user->id)
            ->where('section_id', $sectionId)->where('subject_id', $subjectId)
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id)->whereNull('archived_at'))
            ->exists();
    }

    private function owns(User $user, LessonPlan $plan): bool
    {
        return (int) $plan->teacher_id === (int) $user->id;
    }
}
