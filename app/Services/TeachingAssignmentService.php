<?php

namespace App\Services;

use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * §6.3 / §12.3: one teacher per subject per section — UQ(section, subject).
 * Assigning an already-assigned subject is a conflict, never a silent
 * overwrite; reassignment is an explicit action on the existing row.
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

        $assignment = TeachingAssignment::create([
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
        ]);

        $this->audit->log($actor, 'Assigned teacher to section/subject', 'TeachingAssignment', $assignment->id);

        return $assignment;
    }

    public function reassign(TeachingAssignment $assignment, int $teacherId, User $actor): TeachingAssignment
    {
        $assignment->update(['teacher_id' => $teacherId]);
        $this->audit->log($actor, 'Reassigned teacher for section/subject', 'TeachingAssignment', $assignment->id);

        return $assignment;
    }
}
