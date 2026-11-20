<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\Section;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * §6.3 / §12.3: one teacher per subject per section — UQ(section, subject).
 * Assigning an already-assigned subject is a conflict, never a silent
 * overwrite; reassignment is an explicit action on the existing row.
 * Removing an assignment is refused once grades exist for that section + subject.
 */
class TeachingAssignmentService
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function assign(int $sectionId, int $subjectId, int $teacherId, User $actor): TeachingAssignment
    {
        $existing = TeachingAssignment::where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->first();

        if ($existing && $existing->teacher_id !== $teacherId) {
            throw ValidationException::withMessages([
                'subject_id' => 'This subject is already assigned to another teacher for that section. Reassign or remove the existing assignment instead.',
            ]);
        }

        if ($existing) {
            return $existing; // identical assignment — idempotent no-op
        }

        try {
            return DB::transaction(function () use ($sectionId, $subjectId, $teacherId, $actor) {
                $assignment = TeachingAssignment::create([
                    'section_id' => $sectionId,
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherId,
                ]);

                $this->audit->log($actor, 'Assigned teacher to section/subject', 'TeachingAssignment', $assignment->id);

                return $assignment;
            });
        } catch (QueryException $e) {
            // A concurrent save won the UQ(section, subject) race — report it, don't 500.
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages([
                    'subject_id' => 'This subject was just assigned to a teacher for that section. Refresh and try again.',
                ]);
            }
            throw $e;
        }
    }

    public function reassign(TeachingAssignment $assignment, int $teacherId, User $actor): TeachingAssignment
    {
        return DB::transaction(function () use ($assignment, $teacherId, $actor) {
            $from = $assignment->teacher_id;
            $assignment->update(['teacher_id' => $teacherId]);
            $this->audit->log($actor, 'Reassigned teacher for section/subject', 'TeachingAssignment', $assignment->id, ['teacher' => ['from' => $from, 'to' => $teacherId]]);

            return $assignment;
        });
    }

    /** Number of grades already recorded for this section + subject (any term). */
    public function gradeCount(int $sectionId, int $subjectId): int
    {
        return Grade::whereHas('assessment.category', fn ($q) => $q->where('section_id', $sectionId)->where('subject_id', $subjectId))->count();
    }

    public function remove(TeachingAssignment $assignment, User $actor): void
    {
        if ($n = $this->gradeCount($assignment->section_id, $assignment->subject_id)) {
            $assignment->loadMissing(['section', 'subject']);
            throw ValidationException::withMessages([
                'assignment' => "Can't remove {$assignment->subject->name} from {$assignment->section->name}: {$n} grade"
                    .($n === 1 ? ' is' : 's are').' already recorded for it. Change the teacher instead.',
            ]);
        }

        DB::transaction(function () use ($assignment, $actor) {
            $id = $assignment->id;
            $assignment->delete();
            $this->audit->log($actor, 'Removed teaching assignment', 'TeachingAssignment', $id);
        });
    }

    /**
     * Save several subject → teacher choices for one section in one go. A blank teacher clears
     * the subject. All-or-nothing: if any change is refused, nothing is saved.
     *
     * @param  array<int|string, int|string|null>  $teachersBySubject
     * @return array{assigned: int, reassigned: int, removed: int}
     */
    public function bulkSave(Section $section, array $teachersBySubject, User $actor): array
    {
        $counts = ['assigned' => 0, 'reassigned' => 0, 'removed' => 0];

        DB::transaction(function () use ($section, $teachersBySubject, $actor, &$counts) {
            $existing = TeachingAssignment::where('section_id', $section->id)->get()->keyBy('subject_id');

            foreach ($teachersBySubject as $subjectId => $teacherId) {
                $subjectId = (int) $subjectId;
                $teacherId = $teacherId === null || $teacherId === '' ? null : (int) $teacherId;
                $current = $existing->get($subjectId);

                if ($teacherId === null) {
                    if ($current) {
                        $this->remove($current, $actor);
                        $counts['removed']++;
                    }
                } elseif ($current === null) {
                    $this->assign($section->id, $subjectId, $teacherId, $actor);
                    $counts['assigned']++;
                } elseif ($current->teacher_id !== $teacherId) {
                    $this->reassign($current, $teacherId, $actor);
                    $counts['reassigned']++;
                }
            }
        });

        return $counts;
    }
}
