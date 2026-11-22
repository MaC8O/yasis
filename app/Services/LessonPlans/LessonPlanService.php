<?php

namespace App\Services\LessonPlans;

use App\Exceptions\ActionRefused;
use App\Models\LessonPlan;
use App\Models\LessonPlanReview;
use App\Models\LessonPlanVersion;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Notifications\LessonPlanNotice;
use App\Services\AuditService;
use App\Support\LessonPlanPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Every lesson-plan change. Each runs in one transaction that also writes its audit entry (with
 * the actor's role); notifications are sent only after the commit. Files are written inside the
 * transaction and removed again if it fails; a delete removes files only after it commits.
 */
class LessonPlanService
{
    public const DISK = 'lesson_plans';

    public function __construct(
        private AuditService $audit,
        private LessonPlanAccess $access,
        private LessonPlanFileInspector $inspector,
    ) {}

    public function create(User $teacher, int $assignmentId, int $termId, string $title, ?string $note, UploadedFile $file): LessonPlan
    {
        $assignment = TeachingAssignment::with(['section', 'subject'])->find($assignmentId);
        if (! $assignment || (int) $assignment->teacher_id !== (int) $teacher->id
            || ! $this->access->canSubmit($teacher)
            || ! $this->access->teachesNow($teacher, $assignment->section_id, $assignment->subject_id)) {
            throw new AuthorizationException('You can only upload lesson plans for classes you teach this school year.');
        }

        $term = Term::find($termId);
        if (! $term || (int) $term->academic_year_id !== (int) $assignment->section->academic_year_id) {
            throw ValidationException::withMessages(['term_id' => 'Choose a term of this school year.']);
        }

        $type = $this->inspectOrFail($file);
        $stored = null;

        try {
            return DB::transaction(function () use ($teacher, $assignment, $term, $title, $note, $file, $type, &$stored) {
                $plan = LessonPlan::create([
                    'teacher_id' => $teacher->id,
                    'section_id' => $assignment->section_id,
                    'subject_id' => $assignment->subject_id,
                    'term_id' => $term->id,
                    'title' => $title,
                    'note' => $note,
                    'status' => LessonPlan::SUBMITTED,
                    'current_version' => 1,
                    'submitted_at' => now(),
                ]);

                $version = $this->storeVersion($plan, 1, $file, $type, $teacher, $stored);

                $this->audit->log($teacher, 'Created lesson plan', 'LessonPlan', $plan->id, [
                    'title' => $title,
                    'section' => $assignment->section->name,
                    'subject' => $assignment->subject->name,
                    'term' => $term->name,
                    'version' => 1,
                    'file' => $this->fileDetails($version),
                ]);

                $this->notifyReviewers($plan, $teacher, 'lesson_plan.submitted', 'New lesson plan to review',
                    "{$teacher->name} uploaded “{$title}” ({$assignment->subject->name}, {$assignment->section->name}, {$term->name}).");

                return $plan;
            });
        } catch (Throwable $e) {
            $this->discard($stored);
            throw $e;
        }
    }

    public function addVersion(User $teacher, LessonPlan $plan, UploadedFile $file): LessonPlanVersion
    {
        $this->access->assertCanChange($teacher, $plan);
        $type = $this->inspectOrFail($file);
        $stored = null;

        try {
            return DB::transaction(function () use ($teacher, $plan, $file, $type, &$stored) {
                $plan = $this->lock($plan);

                $used = (int) LessonPlanVersion::where('lesson_plan_id', $plan->id)->sum('size_bytes');
                if ($used + (int) $file->getSize() > LessonPlan::MAX_TOTAL_BYTES) {
                    throw new ActionRefused('This plan has reached its '.(LessonPlan::MAX_TOTAL_BYTES / 1048576)
                        .' MB limit for all versions together. Upload the new file as a new lesson plan instead.');
                }

                $next = $plan->current_version + 1;
                $previousStatus = $plan->status;
                $version = $this->storeVersion($plan, $next, $file, $type, $teacher, $stored);

                $plan->update([
                    'status' => LessonPlan::SUBMITTED,
                    'current_version' => $next,
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                ]);

                $this->audit->log($teacher, 'Uploaded lesson plan version', 'LessonPlan', $plan->id, [
                    'version' => $next,
                    'status' => ['from' => $previousStatus, 'to' => LessonPlan::SUBMITTED],
                    'file' => $this->fileDetails($version),
                ]);

                $this->notifyReviewers($plan, $teacher, 'lesson_plan.new_version', 'New lesson plan version to review',
                    "{$teacher->name} uploaded version {$next} of “{$plan->title}”.");

                return $version;
            });
        } catch (Throwable $e) {
            $this->discard($stored);
            throw $e;
        }
    }

    public function update(User $teacher, LessonPlan $plan, string $title, ?string $note): void
    {
        $this->access->assertCanChange($teacher, $plan);

        DB::transaction(function () use ($teacher, $plan, $title, $note) {
            $plan = $this->lock($plan);

            $changes = [];
            foreach (['title' => $title, 'note' => $note] as $field => $value) {
                if ($plan->{$field} !== $value) {
                    $changes[$field] = ['from' => $plan->{$field}, 'to' => $value];
                }
            }
            if ($changes === []) {
                return;
            }

            $plan->update(['title' => $title, 'note' => $note]);
            $this->audit->log($teacher, 'Edited lesson plan', 'LessonPlan', $plan->id, $changes);
        });
    }

    public function delete(User $teacher, LessonPlan $plan): void
    {
        $this->access->assertCanChange($teacher, $plan);

        DB::transaction(function () use ($teacher, $plan) {
            $plan = $this->lock($plan);

            if (LessonPlanReview::where('lesson_plan_id', $plan->id)->exists()) {
                throw new ActionRefused('This plan has been reviewed, so it cannot be deleted. Upload a new version instead.');
            }

            $versions = LessonPlanVersion::where('lesson_plan_id', $plan->id)->orderBy('version')->get();
            $paths = $versions->pluck('stored_path')->all();
            $planId = $plan->id;

            $plan->delete(); // versions cascade

            $this->audit->log($teacher, 'Deleted lesson plan', 'LessonPlan', $planId, [
                'title' => $plan->title,
                'section_id' => $plan->section_id,
                'subject_id' => $plan->subject_id,
                'term_id' => $plan->term_id,
                'versions' => $versions->map(fn (LessonPlanVersion $v) => ['version' => $v->version] + $this->fileDetails($v))->all(),
            ]);

            DB::afterCommit(function () use ($paths, $planId) {
                $disk = Storage::disk(self::DISK);
                foreach ($paths as $path) {
                    try {
                        $disk->delete($path);
                    } catch (Throwable $e) {
                        Log::warning('Lesson plan file could not be removed after delete.', ['lesson_plan_id' => $planId, 'path' => $path]);
                    }
                }
                try {
                    $disk->deleteDirectory((string) $planId);
                } catch (Throwable) {
                    // An empty folder left behind is harmless.
                }
            });
        });
    }

    public function review(User $reviewer, LessonPlan $plan, string $decision, ?string $comment, int $versionId): LessonPlanReview
    {
        $this->access->assertCanReview($reviewer, $plan);

        if (! in_array($decision, [LessonPlan::APPROVED, LessonPlan::NEEDS_CHANGES], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose Approve or Needs changes.']);
        }
        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;
        if ($decision === LessonPlan::NEEDS_CHANGES && $comment === null) {
            throw ValidationException::withMessages(['comment' => 'Say what needs to change. The teacher sees this comment.']);
        }

        return DB::transaction(function () use ($reviewer, $plan, $decision, $comment, $versionId) {
            $plan = $this->lock($plan);

            if ($plan->status !== LessonPlan::SUBMITTED) {
                throw new ActionRefused('This plan was already reviewed. It can be reviewed again once the teacher uploads a new version.');
            }

            $current = LessonPlanVersion::where('lesson_plan_id', $plan->id)->where('version', $plan->current_version)->firstOrFail();
            if ((int) $current->id !== $versionId) {
                throw new ActionRefused("The teacher uploaded version {$plan->current_version} while you were reviewing. Open it and review that version.");
            }

            $review = LessonPlanReview::create([
                'lesson_plan_id' => $plan->id,
                'lesson_plan_version_id' => $current->id,
                'reviewer_id' => $reviewer->id,
                'decision' => $decision,
                'comment' => $comment,
            ]);

            $plan->update(['status' => $decision, 'reviewed_at' => now()]);

            $this->audit->log($reviewer, 'Reviewed lesson plan', 'LessonPlan', $plan->id, [
                'decision' => $decision,
                'version' => $current->version,
                'status' => ['from' => LessonPlan::SUBMITTED, 'to' => $decision],
                'comment' => $comment,
            ]);

            $teacher = $plan->teacher?->user;
            if ($teacher && $teacher->status === 'Active') {
                $needsChanges = $decision === LessonPlan::NEEDS_CHANGES;
                $title = $needsChanges ? 'Lesson plan needs changes' : 'Lesson plan approved';
                $body = $needsChanges
                    ? "{$reviewer->name} asked for changes to “{$plan->title}” (version {$current->version}): {$comment} Upload a new version when it is ready."
                    : "{$reviewer->name} approved “{$plan->title}” (version {$current->version}).".($comment ? " Comment: {$comment}" : '');
                $notice = new LessonPlanNotice($needsChanges ? 'lesson_plan.needs_changes' : 'lesson_plan.approved', $plan->id, $title, $body,
                    route('lesson-plans.show', $plan), email: $needsChanges);
                DB::afterCommit(fn () => $teacher->notify($notice));
            }

            return $review;
        });
    }

    /** @return array{extension: string, mime: string, label: string} */
    private function inspectOrFail(UploadedFile $file): array
    {
        $type = $this->inspector->inspect($file);
        if (is_string($type)) {
            throw ValidationException::withMessages(['file' => $type]);
        }

        return $type;
    }

    private function storeVersion(LessonPlan $plan, int $number, UploadedFile $file, array $type, User $uploader, ?string &$stored): LessonPlanVersion
    {
        $realPath = $file->getRealPath();
        $sha256 = hash_file('sha256', $realPath);
        $size = (int) $file->getSize();

        $stored = Storage::disk(self::DISK)->putFileAs((string) $plan->id, $file, Str::random(40).'.'.$type['extension']);

        return LessonPlanVersion::create([
            'lesson_plan_id' => $plan->id,
            'version' => $number,
            'stored_path' => $stored,
            'original_name' => $this->cleanName($file->getClientOriginalName(), $type['extension']),
            'mime_type' => $type['mime'],
            'size_bytes' => $size,
            'sha256' => $sha256,
            'uploaded_by' => $uploader->id,
        ]);
    }

    private function cleanName(string $name, string $extension): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $name)), PATHINFO_FILENAME);
        $base = trim(preg_replace('/[\x00-\x1F\x7F"\/\\\\:*?<>|]+/u', ' ', $base) ?? '');
        $base = mb_substr($base !== '' ? $base : 'lesson-plan', 0, 200);

        return $base.'.'.$extension;
    }

    private function fileDetails(LessonPlanVersion $version): array
    {
        return [
            'name' => $version->original_name,
            'type' => $version->mime_type,
            'size_bytes' => $version->size_bytes,
            'sha256' => $version->sha256,
        ];
    }

    private function lock(LessonPlan $plan): LessonPlan
    {
        return LessonPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
    }

    private function notifyReviewers(LessonPlan $plan, User $actor, string $kind, string $title, string $body): void
    {
        $reviewers = User::permission(LessonPlanPermissions::REVIEW)->where('status', 'Active')->whereKeyNot($actor->id)->get();
        if ($reviewers->isEmpty()) {
            return;
        }

        $notice = new LessonPlanNotice($kind, $plan->id, $title, $body, route('lesson-plans.show', $plan));
        DB::afterCommit(fn () => Notification::send($reviewers, $notice));
    }

    private function discard(?string $stored): void
    {
        if ($stored) {
            try {
                Storage::disk(self::DISK)->delete($stored);
            } catch (Throwable) {
                // Nothing points at it; leave it rather than hide the original error.
            }
        }
    }
}
