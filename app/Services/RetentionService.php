<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * §6.2 / §15.8: retention & erasure actions. Records are never hard-deleted —
 * erasure scrubs personally identifiable fields and revokes portal access,
 * leaving academic/financial history keyed to an anonymized record so the
 * audit trail and aggregates stay intact.
 */
class RetentionService
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function eraseStudent(Student $student, string $reason, User $actor): void
    {
        DB::transaction(function () use ($student, $reason, $actor) {
            $student->update([
                'student_id_number' => 'ERASED-'.$student->id,
                'first_name' => 'Erased',
                'last_name' => 'Record '.$student->id,
                'date_of_birth' => null,
                'gender' => null,
                'religious_background' => null,
                'enrollment_status' => $student->enrollment_status === 'Enrolled' ? 'Dropped' : $student->enrollment_status,
            ]);

            if ($student->user) {
                $this->anonymizeUser($student->user, 'student', $student->id);
            }

            $this->audit->log($actor, Str::limit("Retention erasure of student record: {$reason}", 255), 'Student', $student->id);
        });
    }

    public function eraseGuardian(Guardian $guardian, string $reason, User $actor): void
    {
        $orphaned = $guardian->students()
            ->where('enrollment_status', 'Enrolled')
            ->get()
            ->filter(fn ($student) => $student->guardians()->count() <= 1);

        if ($orphaned->isNotEmpty()) {
            throw ValidationException::withMessages([
                'identifier' => 'Cannot erase: this guardian is the only guardian of an enrolled student ('
                    .$orphaned->map(fn ($s) => "{$s->first_name} {$s->last_name}")->join(', ')
                    .'). Link another guardian first.',
            ]);
        }

        DB::transaction(function () use ($guardian, $reason, $actor) {
            $guardian->students()->detach();
            $guardian->update(['relationship' => null, 'phone' => null]);
            $this->anonymizeUser($guardian->user, 'guardian', $guardian->id);

            $this->audit->log($actor, Str::limit("Retention erasure of guardian record: {$reason}", 255), 'Guardian', $guardian->id);
        });
    }

    protected function anonymizeUser(User $user, string $kind, int $subjectId): void
    {
        $user->update([
            'name' => 'Erased '.ucfirst($kind).' '.$subjectId,
            'email' => "erased-{$kind}-{$subjectId}@retention.invalid",
            'password' => Hash::make(Str::password(32)),
            'status' => 'Inactive',
        ]);
    }
}
