<?php

namespace App\Services\Behavior;

use App\Models\BehaviorReport;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who may see which student's behavior record. Enforced on every behavior route.
 *
 * - VP, Principal, Admin, Registrar: all students (only the VP writes; the Principal also decides cases).
 * - Teacher: students with an Active enrollment in a non-archived section of the ACTIVE school year
 *   that they teach (teaching assignment) or are homeroom teacher of. Not earlier years.
 *   They may always open reports they wrote themselves, without the rest of that student's record.
 * - Student: their own record. Guardian: their linked children only.
 */
class BehaviorAccess
{
    public const ALL_STUDENT_ROLES = ['vp_academic', 'principal', 'admin', 'registrar'];

    public function __construct(private BehaviorStanding $standing) {}

    public function seesAllStudents(User $user): bool
    {
        return $user->hasAnyRole(self::ALL_STUDENT_ROLES);
    }

    /** Student ids in a teacher's scope for the active school year. */
    public function teacherStudentIds(int $staffId): Collection
    {
        $yearId = $this->standing->activeYearId();
        if (! $yearId) {
            return collect();
        }

        $sectionIds = Section::where('academic_year_id', $yearId)->whereNull('archived_at')
            ->where(fn ($q) => $q->where('homeroom_teacher_id', $staffId)
                ->orWhereIn('id', fn ($sub) => $sub->select('section_id')->from('teaching_assignments')->where('teacher_id', $staffId)))
            ->pluck('id');

        return Enrollment::whereIn('section_id', $sectionIds)->where('status', 'Active')->distinct()->pluck('student_id');
    }

    /** Students whose full behavior record $user may open (staff lists). */
    public function visibleStudents(User $user): Builder
    {
        if ($this->seesAllStudents($user)) {
            return Student::query();
        }
        if ($user->hasRole('teacher')) {
            return Student::whereIn('id', $this->teacherStudentIds($user->id));
        }

        return Student::whereRaw('1 = 0');
    }

    public function canViewStudent(User $user, Student $student): bool
    {
        if ($this->seesAllStudents($user)) {
            return true;
        }
        if ($user->hasRole('teacher') && $this->teacherStudentIds($user->id)->contains($student->id)) {
            return true;
        }
        if ($user->hasRole('student') && $student->user_id !== null && (int) $student->user_id === (int) $user->id) {
            return true;
        }
        if ($user->hasRole('guardian') && $user->guardian) {
            return $user->guardian->students()->where('students.id', $student->id)->exists();
        }

        return false;
    }

    /** @throws AuthorizationException */
    public function assertCanViewStudent(User $user, Student $student): void
    {
        if (! $this->canViewStudent($user, $student)) {
            throw new AuthorizationException('You do not have access to this student\'s behavior record.');
        }
    }

    /** Teachers may file reports only for students currently in their scope. */
    public function assertTeacherCanReport(User $user, Student $student): void
    {
        if (! $user->hasRole('teacher') || ! $this->teacherStudentIds($user->id)->contains($student->id)) {
            throw new AuthorizationException('You can only report students you teach this school year.');
        }
    }

    /** A teacher's own report stays reachable even after the student leaves their scope. */
    public function canViewReport(User $user, BehaviorReport $report): bool
    {
        return (int) $report->reported_by === (int) $user->id || $this->canViewStudent($user, $report->student);
    }
}
