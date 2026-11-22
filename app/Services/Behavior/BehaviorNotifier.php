<?php

namespace App\Services\Behavior;

use App\Models\Student;
use App\Models\User;
use App\Notifications\BehaviorNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Builds and sends behavior notices. Called from inside the write transactions; delivery is
 * deferred until the transaction commits, so a rolled-back change never notifies anyone.
 * Each recipient gets a link to the page their own role can open.
 */
class BehaviorNotifier
{
    /** Notify the student (if they have an active login) and their guardians. */
    public function family(Student $student, string $kind, string $title, string $body, bool $emailGuardians = false, bool $includeStudent = true): void
    {
        if ($includeStudent && $student->user && $student->user->status === 'Active') {
            $this->send(collect([$student->user]), $kind, $student, $title, $body, fn () => route('student.behavior.index'));
        }

        $guardianUsers = $student->guardians()->with('user')->get()->pluck('user')->filter()
            ->reject(fn (User $u) => $u->status === 'Inactive');
        $this->send($guardianUsers, $kind, $student, $title, $body, fn () => route('guardian.behavior.index', ['child' => $student->id]), $emailGuardians);
    }

    /** Notify every active user holding one of $roles. */
    public function staff(array $roles, Student $student, string $kind, string $title, string $body, ?string $url = null): void
    {
        $users = User::role($roles)->where('status', 'Active')->get();
        $this->send($users, $kind, $student, $title, $body, fn () => $url ?? route('behavior.students.show', $student));
    }

    private function send(Collection $users, string $kind, Student $student, string $title, string $body, \Closure $url, bool $email = false): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $link = $url();
        DB::afterCommit(fn () => Notification::send($users, new BehaviorNotice($kind, $student->id, $title, $body, $link, $email)));
    }
}
