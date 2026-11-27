<?php

namespace App\Services\Sun;

use App\Exceptions\ActionRefused;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\SunAccount;
use App\Models\SunImportBatch;
use App\Models\SunLedgerLine;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * §8 matching and the Treasurer's per-line overrides (§5, §7). Accounts are matched by code only,
 * never by name; names only suggest candidates. A linked code stays linked in later years and is
 * never moved to another student (T3). Every change is audited, without names or memos.
 */
class SunAccountService
{
    public function __construct(private AuditService $audit) {}

    public function link(SunAccount $account, Student $student, User $actor): void
    {
        DB::transaction(function () use ($account, $student, $actor) {
            $account = SunAccount::lockForUpdate()->findOrFail($account->id);
            if ($account->is_control) {
                throw new ActionRefused('The Student Control account is never linked to a student.');
            }
            if ($account->student_id) {
                throw new ActionRefused('This account is already linked. An account code stays with one student (T3).');
            }
            $other = SunAccount::where('student_id', $student->id)->value('code');
            if ($other) {
                throw new ActionRefused("{$student->name} is already linked to account {$other}.");
            }
            $account->update(['student_id' => $student->id, 'not_isms_student' => false, 'linked_at' => now(), 'linked_by' => $actor->id]);
            $this->audit->log($actor, 'Linked ledger account', 'SunAccount', $account->id, ['code' => $account->code, 'student' => $student->student_id_number]);
        });
    }

    /** Only to fix a mistake: refused while the account has lines in a published import (§8). */
    public function unlink(SunAccount $account, User $actor): void
    {
        DB::transaction(function () use ($account, $actor) {
            $account = SunAccount::with('student')->lockForUpdate()->findOrFail($account->id);
            if (! $account->student_id) {
                throw new ActionRefused('This account is not linked.');
            }
            $published = SunImportBatch::current()->whereNotNull('published_at')->pluck('id');
            if (SunLedgerLine::current()->where('sun_account_id', $account->id)
                ->where(fn ($q) => $q->whereIn('created_batch_id', $published)->orWhereIn('last_batch_id', $published))->exists()) {
                throw new ActionRefused('This account has lines in a published import. Unpublish or revert that import before unlinking.');
            }
            $student = $account->student?->student_id_number;
            $account->update(['student_id' => null, 'linked_at' => null, 'linked_by' => null]);
            $this->audit->log($actor, 'Unlinked ledger account', 'SunAccount', $account->id, ['code' => $account->code, 'student' => $student]);
        });
    }

    /** §8: old accounts of students who left before the ISMS stop showing as unmatched. */
    public function markNotIsms(SunAccount $account, bool $notIsms, User $actor): void
    {
        if ($account->student_id || $account->is_control) {
            throw new ActionRefused('Only an unlinked student account can be marked this way.');
        }
        $account->update(['not_isms_student' => $notIsms]);
        $this->audit->log($actor, $notIsms ? 'Marked ledger account not an ISMS student' : 'Unmarked ledger account not an ISMS student',
            'SunAccount', $account->id, ['code' => $account->code]);
    }

    /**
     * Students whose name is close to the account's printed name (cut off at about 30
     * characters), best first. Suggestions only: nothing is linked by name (§8).
     */
    public function suggestions(SunAccount $account, ?Collection $students = null, int $limit = 3): array
    {
        $students ??= Student::whereNotIn('id', SunAccount::whereNotNull('student_id')->pluck('student_id'))->get(['id', 'name', 'student_id_number']);
        $printed = self::normalise($account->name_as_printed);
        if ($printed === '') {
            return [];
        }

        return $students->map(function ($s) use ($printed) {
            $name = self::normalise($s->name);
            // The printed name can be cut off, so compare it with the same length of the full name too.
            similar_text($printed, mb_substr($name, 0, max(mb_strlen($printed), 1)), $cut);
            similar_text($printed, $name, $full);

            return ['student' => $s, 'score' => (int) round(max($cut, $full))];
        })->filter(fn ($r) => $r['score'] >= 60)->sortByDesc('score')->take($limit)->values()->all();
    }

    private static function normalise(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($name)));
    }

    // --- Per-line overrides -------------------------------------------------------------------------

    public function setCategory(SunLedgerLine $line, string $category, User $actor): void
    {
        if (! FeeCategory::where('key', $category)->exists()) {
            throw new ActionRefused('Unknown category.');
        }
        if ($line->category === $category && $line->category_overridden) {
            throw new ActionRefused('Nothing was changed.');
        }
        $from = $line->category;
        $line->update(['category' => $category, 'category_overridden' => true]);
        $this->audit->log($actor, 'Changed ledger line category', 'SunLedgerLine', $line->id, ['account' => $line->sun_account_code, 'from' => $from, 'to' => $category]);
    }

    public function setRestricted(SunLedgerLine $line, bool $restricted, User $actor): void
    {
        if ((bool) $line->is_restricted === $restricted && $line->restricted_overridden) {
            throw new ActionRefused('Nothing was changed.');
        }
        $line->update(['is_restricted' => $restricted, 'restricted_overridden' => true]);
        $this->audit->log($actor, $restricted ? 'Hid ledger line from families' : 'Showed ledger line to families', 'SunLedgerLine', $line->id,
            ['account' => $line->sun_account_code]);
    }
}
