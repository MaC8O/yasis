<?php

namespace App\Services;

use App\Models\BehaviorCase;
use App\Models\BehaviorCaseDecision;
use App\Models\BehaviorLedgerEntry;
use App\Models\BehaviorReport;
use App\Models\BehaviorReportVersion;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentExitRequest;
use App\Models\SunAccount;
use App\Models\SunAccountBalance;
use App\Models\SunAccountLink;
use App\Models\SunAccountReview;
use App\Models\SunLedgerLine;
use App\Models\User;
use App\Services\Sun\SunFamilyStatements;
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
    public function __construct(protected AuditService $audit) {}

    public function eraseStudent(Student $student, string $reason, User $actor): void
    {
        DB::transaction(function () use ($student, $reason, $actor) {
            $student->update([
                'student_id_number' => 'ERASED-'.$student->id,
                'name' => 'Erased Record '.$student->id,
                'date_of_birth' => null,
                'gender' => null,
                'religious_background' => null,
                'enrollment_status' => $student->enrollment_status === 'Enrolled' ? 'Dropped' : $student->enrollment_status,
            ]);

            if ($student->user) {
                $this->anonymizeUser($student->user, 'student', $student->id);
                $this->scrubNotices($student->user);
            }

            $behavior = $this->eraseBehaviorText($student);
            $ledger = $this->eraseLedgerText($student);

            $this->audit->log($actor, Str::limit("Retention erasure of student record: {$reason}", 255), 'Student', $student->id,
                array_filter(['behavior_rows_scrubbed' => $behavior, 'ledger_lines_scrubbed' => $ledger]) ?: null);
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
                    .$orphaned->map(fn ($s) => "{$s->name}")->join(', ')
                    .'). Link another guardian first.',
            ]);
        }

        DB::transaction(function () use ($guardian, $reason, $actor) {
            $guardian->students()->detach();
            $guardian->update(['relationship' => null, 'phone' => null]);
            $this->anonymizeUser($guardian->user, 'guardian', $guardian->id);
            $this->scrubNotices($guardian->user);

            $this->audit->log($actor, Str::limit("Retention erasure of guardian record: {$reason}", 255), 'Guardian', $guardian->id);
        });
    }

    /**
     * The student's Sun ledger accounts, every code ever linked to them (T3: a code can pass to
     * another student): the printed names, the names on "possible reused code" reviews and links,
     * every line's memo and the memos inside the family statements are cleared. Amounts and
     * balances stay, as financial records.
     */
    protected function eraseLedgerText(Student $student): int
    {
        $accountIds = SunAccountLink::where('student_id', $student->id)->pluck('sun_account_id')->unique()->values();
        if ($accountIds->isEmpty()) {
            return 0;
        }
        $erased = 'Erased Record '.$student->id;
        SunAccount::whereIn('id', $accountIds)->update(['name_as_printed' => $erased]);
        SunAccountBalance::whereIn('sun_account_id', $accountIds)->update(['account_name_as_printed' => $erased]);
        SunAccountReview::whereIn('sun_account_id', $accountIds)->update(['previous_name' => $erased, 'printed_name' => $erased]);
        SunAccountLink::whereIn('sun_account_id', $accountIds)->update(['accepted_name' => $erased]);
        app(SunFamilyStatements::class)->scrub($accountIds);

        return SunLedgerLine::whereIn('sun_account_id', $accountIds)->update(['memo' => '']);
    }

    /**
     * Behavior records: blank every free-text field (descriptions and reasons can name people)
     * but keep dates, categories, points, scores and statuses, so counts and the ledger stay
     * intact. Every notice about this student, whoever received it, is blanked too.
     *
     * @return int number of behavior rows scrubbed
     */
    protected function eraseBehaviorText(Student $student): int
    {
        $erased = '[erased under a retention request]';
        $reportIds = BehaviorReport::where('student_id', $student->id)->pluck('id');
        $caseIds = BehaviorCase::where('student_id', $student->id)->pluck('id');

        $rows = BehaviorReport::whereIn('id', $reportIds)->update([
            'description' => $erased,
            'dismiss_reason' => DB::raw("CASE WHEN dismiss_reason IS NULL THEN NULL ELSE '{$erased}' END"),
            'void_reason' => DB::raw("CASE WHEN void_reason IS NULL THEN NULL ELSE '{$erased}' END"),
        ]);
        $rows += BehaviorReportVersion::whereIn('behavior_report_id', $reportIds)->update(['description' => $erased]);
        $rows += BehaviorLedgerEntry::where('student_id', $student->id)->update(['reason' => $erased]);
        $rows += BehaviorCaseDecision::whereIn('behavior_case_id', $caseIds)->update(['reason' => $erased]);
        $rows += StudentExitRequest::where('student_id', $student->id)->update([
            'reason' => $erased,
            'withdraw_reason' => DB::raw("CASE WHEN withdraw_reason IS NULL THEN NULL ELSE '{$erased}' END"),
        ]);

        DB::table('notifications')->where('data->student_id', $student->id)
            ->update(['data' => json_encode(['title' => 'Notice erased', 'body' => $erased, 'url' => null, 'student_id' => $student->id])]);

        return $rows;
    }

    /** Blank the in-app notices a person received (they can name children and incidents). */
    protected function scrubNotices(User $user): void
    {
        $user->notifications()->update(['data' => json_encode(['title' => 'Notice erased', 'body' => '[erased under a retention request]', 'url' => null])]);
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
