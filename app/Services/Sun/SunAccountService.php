<?php

namespace App\Services\Sun;

use App\Exceptions\ActionRefused;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\SunAccount;
use App\Models\SunAccountBalance;
use App\Models\SunAccountLink;
use App\Models\SunAccountReview;
use App\Models\SunImportBatch;
use App\Models\SunLedgerLine;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * §8 matching, with year-ranged links (T3: a code can be reused after a student leaves), the
 * "possible reused code" reviews, and the Treasurer's per-line overrides (§5, §7). Accounts are
 * matched by code only; names only suggest. Every change is audited without names or memos, and
 * the published family statements follow it straight away.
 */
class SunAccountService
{
    public function __construct(private AuditService $audit, private SunFamilyStatements $statements) {}

    // --- Links ----------------------------------------------------------------------------------------

    /**
     * Link a code to a student from $fromYear (default: the code's first year in the ISMS, or the
     * year after its last ended link). One open link per code and per student.
     */
    public function link(SunAccount $account, Student $student, User $actor, ?int $fromYear = null): SunAccountLink
    {
        return DB::transaction(function () use ($account, $student, $actor, $fromYear) {
            $account = SunAccount::lockForUpdate()->findOrFail($account->id);
            if ($account->is_control) {
                throw new ActionRefused('The Student Control account is never linked to a student.');
            }
            if ($account->links()->whereNull('to_fiscal_year')->whereNull('voided_at')->exists()) {
                throw new ActionRefused("Account {$account->code} is already linked. If the code now belongs to another student, end its link on the code's review.");
            }
            $other = SunAccountLink::with('account')->where('student_id', $student->id)->whereNull('to_fiscal_year')->whereNull('voided_at')->first();
            if ($other) {
                throw new ActionRefused("{$student->name} is already linked to account {$other->account->code}.");
            }
            $lastEnd = $account->links()->whereNull('voided_at')->max('to_fiscal_year');
            $from = max($fromYear ?? $account->first_seen_year, $lastEnd ? $lastEnd + 1 : 0);

            $link = SunAccountLink::create([
                'sun_account_id' => $account->id, 'student_id' => $student->id, 'from_fiscal_year' => $from,
                'accepted_name' => $account->name_as_printed, 'linked_by' => $actor->id, 'linked_at' => now(),
            ]);
            $account->update(['not_isms_student' => false]);
            $this->audit->log($actor, 'Linked ledger account', 'SunAccount', $account->id,
                ['code' => $account->code, 'student' => $student->student_id_number, 'from' => SunPeriods::label($from)]);
            $this->statements->reassign($account->id);

            return $link;
        });
    }

    /**
     * Only to fix a mistake: the open link is voided (kept, covering nothing). Refused while the
     * years it covers have lines in a published import (§8).
     */
    public function unlink(SunAccount $account, User $actor): void
    {
        DB::transaction(function () use ($account, $actor) {
            $account = SunAccount::lockForUpdate()->findOrFail($account->id);
            $link = $account->links()->with('student')->whereNull('to_fiscal_year')->whereNull('voided_at')->lockForUpdate()->first();
            if (! $link) {
                throw new ActionRefused('This account is not linked.');
            }
            $published = SunImportBatch::current()->whereNotNull('published_at')->pluck('id');
            $hasPublished = SunLedgerLine::current()->where('sun_account_id', $account->id)->where('fiscal_year', '>=', $link->from_fiscal_year)
                ->where(fn ($q) => $q->whereIn('created_batch_id', $published)->orWhereIn('last_batch_id', $published))->exists();
            if ($hasPublished) {
                throw new ActionRefused('This account has lines in a published import. Unpublish or revert that import before unlinking.');
            }
            $link->update(['voided_at' => now(), 'voided_by' => $actor->id]);
            $this->audit->log($actor, 'Unlinked ledger account', 'SunAccount', $account->id,
                ['code' => $account->code, 'student' => $link->student?->student_id_number]);
            $this->statements->reassign($account->id);
        });
    }

    /** §8: old accounts of students who left before the ISMS stop showing as unmatched. */
    public function markNotIsms(SunAccount $account, bool $notIsms, User $actor): void
    {
        if ($account->is_control || $account->links()->whereNull('voided_at')->exists()) {
            throw new ActionRefused('Only a student account that was never linked can be marked this way.');
        }
        $account->update(['not_isms_student' => $notIsms]);
        $this->audit->log($actor, $notIsms ? 'Marked ledger account not an ISMS student' : 'Unmarked ledger account not an ISMS student',
            'SunAccount', $account->id, ['code' => $account->code]);
    }

    /** Accounts with a balance in the year that no link covers: not control, not "not an ISMS student", not held. */
    public function unmatchedQuery(int $fiscalYear): Builder
    {
        return SunAccount::query()->where('is_control', false)->where('not_isms_student', false)
            ->whereIn('id', SunAccountBalance::current()->where('fiscal_year', $fiscalYear)->select('sun_account_id'))
            ->whereNotIn('id', SunAccountLink::covering($fiscalYear)->select('sun_account_id'))
            ->whereNotIn('id', SunAccountReview::pending()->where('fiscal_year', $fiscalYear)->select('sun_account_id'));
    }

    // --- Possible reused codes (T3) --------------------------------------------------------------------

    /** The same student under a corrected name: the new name becomes the accepted one, so it isn't asked again. */
    public function confirmSameStudent(SunAccountReview $review, User $actor): void
    {
        DB::transaction(function () use ($review, $actor) {
            $review = SunAccountReview::with('account')->lockForUpdate()->findOrFail($review->id);
            $this->pending($review);
            $link = SunAccountLink::covering($review->fiscal_year)->where('sun_account_id', $review->sun_account_id)->lockForUpdate()->firstOrFail();
            $link->update(['accepted_name' => $review->printed_name]);
            $review->update(['status' => SunAccountReview::SAME, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            $this->audit->log($actor, 'Confirmed ledger account is the same student', 'SunAccount', $review->sun_account_id,
                ['code' => $review->account->code, 'year' => SunPeriods::label($review->fiscal_year)]);
            $this->statements->reassign($review->sun_account_id, $review->fiscal_year);
        });
    }

    /** The code now belongs to another student: the old link ends the year before, the new student is linked from this year. */
    public function relink(SunAccountReview $review, Student $student, User $actor): void
    {
        DB::transaction(function () use ($review, $student, $actor) {
            $review = SunAccountReview::with('account')->lockForUpdate()->findOrFail($review->id);
            $this->pending($review);
            $old = SunAccountLink::with('student')->where('sun_account_id', $review->sun_account_id)->whereNull('to_fiscal_year')->whereNull('voided_at')->lockForUpdate()->first();
            if ($old && $old->student_id === $student->id) {
                throw new ActionRefused("{$student->name} is the student already linked. Choose \"Same student\" instead.");
            }
            if ($old) {
                $old->from_fiscal_year >= $review->fiscal_year
                    ? $old->update(['voided_at' => now(), 'voided_by' => $actor->id])
                    : $old->update(['to_fiscal_year' => $review->fiscal_year - 1, 'ended_at' => now(), 'ended_by' => $actor->id]);
            }
            $other = SunAccountLink::with('account')->where('student_id', $student->id)->whereNull('to_fiscal_year')->whereNull('voided_at')->first();
            if ($other) {
                throw new ActionRefused("{$student->name} is already linked to account {$other->account->code}.");
            }
            SunAccountLink::create([
                'sun_account_id' => $review->sun_account_id, 'student_id' => $student->id, 'from_fiscal_year' => $review->fiscal_year,
                'accepted_name' => $review->printed_name, 'linked_by' => $actor->id, 'linked_at' => now(),
            ]);
            $review->update(['status' => SunAccountReview::RELINKED, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            $this->audit->log($actor, 'Linked reused ledger account to another student', 'SunAccount', $review->sun_account_id, [
                'code' => $review->account->code, 'year' => SunPeriods::label($review->fiscal_year),
                'previous_student' => $old?->student?->student_id_number, 'student' => $student->student_id_number,
            ]);
            $this->statements->reassign($review->sun_account_id);
        });
    }

    private function pending(SunAccountReview $review): void
    {
        if ($review->status !== SunAccountReview::PENDING) {
            throw new ActionRefused('This review was already resolved.');
        }
    }

    // --- Suggestions --------------------------------------------------------------------------------------

    /**
     * Students whose name is close to the account's printed name (cut off at about 30
     * characters), best first, among students with no open link. Suggestions only (§8).
     */
    public function suggestions(SunAccount $account, ?Collection $students = null, int $limit = 3, ?string $name = null): array
    {
        $students ??= Student::whereNotIn('id', SunAccountLink::whereNull('to_fiscal_year')->whereNull('voided_at')->select('student_id'))
            ->get(['id', 'name', 'student_id_number']);
        $printed = SunLinks::normalise($name ?? $account->name_as_printed);
        if ($printed === '') {
            return [];
        }

        return $students->map(function ($s) use ($printed) {
            $full = SunLinks::normalise($s->name);
            // The printed name can be cut off, so compare it with the same length of the full name too.
            similar_text($printed, mb_substr($full, 0, max(mb_strlen($printed), 1)), $cut);
            similar_text($printed, $full, $whole);

            return ['student' => $s, 'score' => (int) round(max($cut, $whole))];
        })->filter(fn ($r) => $r['score'] >= 60)->sortByDesc('score')->take($limit)->values()->all();
    }

    // --- Per-line overrides ---------------------------------------------------------------------------------

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
        $this->statements->refreshLine($line);
    }

    /** A line hidden by hand shows to families only inside the year's "Adjustments" (T4: everything else is shown). */
    public function setRestricted(SunLedgerLine $line, bool $restricted, User $actor): void
    {
        if ((bool) $line->is_restricted === $restricted && $line->restricted_overridden) {
            throw new ActionRefused('Nothing was changed.');
        }
        $line->update(['is_restricted' => $restricted, 'restricted_overridden' => true]);
        $this->audit->log($actor, $restricted ? 'Hid ledger line from families' : 'Showed ledger line to families', 'SunLedgerLine', $line->id,
            ['account' => $line->sun_account_code]);
        $this->statements->refreshLine($line);
    }
}
