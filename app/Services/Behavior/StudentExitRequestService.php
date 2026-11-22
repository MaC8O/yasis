<?php

namespace App\Services\Behavior;

use App\Exceptions\ActionRefused;
use App\Models\BehaviorCase;
use App\Models\StudentExitRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Pending exit requests created by an Expel decision. A request never changes the student by
 * itself: the Registrar links it when confirming a Transfer/Drop of that student, or the
 * Principal withdraws it with a reason before then.
 */
class StudentExitRequestService
{
    public function __construct(private AuditService $audit, private BehaviorNotifier $notify) {}

    /** Called inside the case-decision transaction. */
    public function createForCase(User $actor, BehaviorCase $case, string $reason): StudentExitRequest
    {
        $request = StudentExitRequest::create([
            'student_id' => $case->student_id,
            'source_type' => 'BehaviorCase',
            'source_id' => $case->id,
            'reason' => $reason,
            'status' => 'Pending',
        ]);

        $this->audit->log($actor, 'Created pending exit request (expulsion decision)', 'StudentExitRequest', $request->id, [
            'student_id' => $case->student_id, 'behavior_case_id' => $case->id,
        ]);

        $student = $case->student;
        $this->notify->staff(['registrar'], $student, 'exit_request',
            'Exit request pending',
            "The VP and the Principal decided to expel {$student->name}. Process the exit through Transfer/Drop on the student's record and confirm it is this request. The student's status has not been changed.",
            route('registrar.students.show', $student));

        return $request;
    }

    public function withdraw(User $principal, StudentExitRequest $request, string $reason): void
    {
        if (! $principal->hasRole('principal')) {
            throw new AuthorizationException('Only the Principal can withdraw an exit request.');
        }

        DB::transaction(function () use ($principal, $request, $reason) {
            $request = StudentExitRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $request->isPending()) {
                throw new ActionRefused("This exit request is already {$request->status} and can no longer be withdrawn.");
            }

            $request->update([
                'status' => 'Withdrawn', 'withdrawn_by' => $principal->id,
                'withdrawn_at' => now(), 'withdraw_reason' => $reason,
            ]);

            $this->audit->log($principal, 'Withdrew exit request', 'StudentExitRequest', $request->id, [
                'status' => ['from' => 'Pending', 'to' => 'Withdrawn'], 'reason' => $reason,
            ]);

            $student = $request->student;
            $body = "The Principal withdrew the pending exit request for {$student->name}. Reason: {$reason}";
            $this->notify->staff(['vp_academic'], $student, 'exit_request_withdrawn', 'Exit request withdrawn', $body);
            $this->notify->staff(['registrar'], $student, 'exit_request_withdrawn', 'Exit request withdrawn', $body, route('registrar.students.show', $student));
        });
    }

    /** Link a confirmed Transfer/Drop to the pending request. Called inside the Registrar's transfer transaction. */
    public function markProcessed(User $registrar, StudentExitRequest $request, int $studentId): void
    {
        $request = StudentExitRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
        $message = 'That exit request is no longer pending for this student. Reload the page and try again.';
        // The transfer form only lists this student's own exit requests.
        if ((int) $request->student_id !== $studentId) {
            throw ActionRefused::unexpected('behavior.exit_request_other_student', $message);
        }
        if (! $request->isPending()) {
            throw new ActionRefused($message);
        }

        $request->update(['status' => 'Processed', 'processed_by' => $registrar->id, 'processed_at' => now()]);

        $this->audit->log($registrar, 'Processed exit request through Transfer/Drop', 'StudentExitRequest', $request->id, [
            'status' => ['from' => 'Pending', 'to' => 'Processed'],
        ]);
    }
}
