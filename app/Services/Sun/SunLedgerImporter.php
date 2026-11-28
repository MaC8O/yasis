<?php

namespace App\Services\Sun;

use App\Exceptions\ActionRefused;
use App\Models\SunAccount;
use App\Models\SunAccountBalance;
use App\Models\SunAccountLink;
use App\Models\SunAccountReview;
use App\Models\SunImportBatch;
use App\Models\SunLedgerLine;
use App\Models\SunLedgerVersion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports a Sun Accounts Account Listing (sdp_doc/YASIS_Sun_Finance_Import.md, gitignored).
 *
 * 1. stage(): the upload is kept on the private disk (pending/) and parsed; the Treasurer sees a
 *    summary before anything is saved. A file that fails a §3 check is deleted at once.
 * 2. import() parses the same file again and writes it in one transaction; cancel() deletes it.
 *    A pending upload expires after an hour (purgeExpired(), on every upload and hourly).
 *
 * Re-import (§10): existing lines (same unique key) are updated, keeping the Treasurer's category
 * and visibility overrides; new lines are inserted; lines no longer in the file are marked
 * missing_from_latest. Every record a batch creates or changes gets a version row, so a revert
 * restores exactly that. Nothing is hard-deleted.
 *
 * Privacy (§0): nothing from the file is logged; audit entries hold counts only.
 */
class SunLedgerImporter
{
    public const DISK = 'fee_imports';

    public const PENDING_MINUTES = 60;

    public function __construct(private AccountListingParser $parser, private AuditService $audit, private SunFamilyStatements $statements) {}

    // --- Upload, summary, cancel --------------------------------------------------------------------

    /** @return array{token: ?string, parsed: array, summary: ?array} */
    public function stage(UploadedFile $file, User $user): array
    {
        $this->purgeExpired();
        $token = (string) Str::uuid();
        $path = $this->pendingPath($user, $token);
        Storage::disk(self::DISK)->put($path, $file->get());

        $contents = Storage::disk(self::DISK)->get($path);
        $parsed = $this->parser->parse($contents);
        if (! $parsed['ok']) {
            Storage::disk(self::DISK)->delete($path);

            return ['token' => null, 'parsed' => $parsed, 'summary' => null];
        }

        return ['token' => $token, 'parsed' => $parsed, 'summary' => $this->summary($parsed, hash('sha256', $contents))];
    }

    public function cancel(string $token, User $user): void
    {
        Storage::disk(self::DISK)->delete($this->pendingPath($user, $token));
    }

    /** Delete pending uploads older than an hour. */
    public function purgeExpired(): int
    {
        $disk = Storage::disk(self::DISK);
        $expired = collect($disk->files('pending'))->filter(fn ($f) => $disk->lastModified($f) < now()->subMinutes(self::PENDING_MINUTES)->getTimestamp());
        $expired->each(fn ($f) => $disk->delete($f));

        return $expired->count();
    }

    private function pendingPath(User $user, string $token): string
    {
        if (! Str::isUuid($token)) {
            throw new ActionRefused('That upload is not valid. Upload the file again.');
        }

        return "pending/{$user->id}-{$token}.prn";
    }

    /** What an import of this parsed file would do, and the §3 warnings. Writes nothing. */
    public function summary(array $parsed, string $sha, $existing = null): array
    {
        $fy = $parsed['header']['fiscal_year'];
        $accounts = $parsed['accounts'];
        $known = SunAccount::whereIn('code', array_keys($accounts))->get()->keyBy('code');
        $control = self::controlCode();
        $firstMonth = self::firstMonth();

        $newAccounts = count(array_diff_key($accounts, $known->all()));
        // T3: a linked code printed under a name other than the one accepted for its student is held for review.
        $links = SunAccountLink::covering($fy)->whereIn('sun_account_id', $known->pluck('id'))->get()->keyBy('sun_account_id');
        $byId = $known->keyBy('id');
        $pending = SunAccountReview::pending()->where('fiscal_year', $fy)->whereIn('sun_account_id', $known->pluck('id'))->pluck('sun_account_id')->flip();
        $held = $links->keys()->filter(fn ($id) => $pending->has($id) || ! SunLinks::sameName($links[$id]->accepted_name, $accounts[$byId[$id]->code]['name']));
        $matched = $links->count() - $held->count();
        // Waiting to be linked: every code except the control account, linked or held ones and "not an ISMS student".
        $unmatched = collect($accounts)->filter(fn ($a) => $a['code'] !== $control && (! $known->has($a['code'])
            || (! $links->has($known[$a['code']]->id) && ! $known[$a['code']]->not_isms_student && ! $known[$a['code']]->is_control)))->count();

        $suspect = 0;
        foreach ($accounts as $a) {
            foreach ($a['lines'] as $l) {
                $suspect += SunPeriods::isSuspect($l['txn_date'], SunPeriods::month($l['period'], $fy, $firstMonth)) ? 1 : 0;
            }
        }

        $previous = SunAccountBalance::current()->where('fiscal_year', $fy - 1)->whereIn('sun_account_id', $known->pluck('id'))->get()->keyBy('sun_account_id');
        $openingDiffers = $known->filter(fn ($k) => isset($previous[$k->id]) && (string) $previous[$k->id]->closing_balance !== $accounts[$k->code]['opening'])->count();

        $existing ??= $this->existingLines($fy, $parsed['header']['account_from'], $parsed['header']['account_to']);
        $changed = 0;
        $seen = [];
        $new = 0;
        foreach ($accounts as $a) {
            $code = $a['code'];
            foreach ($a['lines'] as $l) {
                $key = self::key($l['journal_type'], $l['journal_no'], $l['journal_line'], $code);
                $seen[$key] = true;
                $row = $existing[$key] ?? null;
                if (! $row || $row->removed_at) {
                    $new++;
                } elseif ((string) $row->amount !== $l['amount']) {
                    $changed++;
                }
            }
        }
        $missing = $existing->reject(fn ($r, $key) => isset($seen[$key]) || $r->removed_at || $r->missing_from_latest)->count();

        return [
            'fiscal_year' => $fy, 'year_label' => SunPeriods::label($fy),
            'printed_at' => $parsed['header']['printed_at'],
            'period_from' => $parsed['header']['period_from'], 'period_to' => $parsed['header']['period_to'],
            'account_from' => $parsed['header']['account_from'], 'account_to' => $parsed['header']['account_to'],
            'accounts' => count($accounts), 'lines' => $parsed['line_count'],
            'new_lines' => $new, 'matched' => $matched, 'unmatched' => $unmatched, 'new_accounts' => $newAccounts,
            'control_balance' => $accounts[$control]['closing'] ?? '0.00',
            'warnings' => [
                'duplicate_file' => SunImportBatch::current()->where('file_sha256', $sha)->exists(),
                'opening_differs' => $openingDiffers,
                'suspect_dates' => $suspect,
                'possible_reused' => $held->count(),
                'unallocated' => $parsed['unallocated'],
                'changed_amounts' => $changed,
                'missing_lines' => $missing,
            ],
        ];
    }

    // --- Import ---------------------------------------------------------------------------------------

    public function import(string $token, string $originalName, User $user): SunImportBatch
    {
        $disk = Storage::disk(self::DISK);
        $pending = $this->pendingPath($user, $token);
        if (! $disk->exists($pending)) {
            throw new ActionRefused('That upload has expired or was cancelled. Upload the file again.');
        }
        $contents = $disk->get($pending);
        $sha = hash('sha256', $contents);
        $parsed = $this->parser->parse($contents);
        if (! $parsed['ok']) {
            $disk->delete($pending);
            throw new ActionRefused('The file failed its checks. Upload it again to see the errors.');
        }

        @set_time_limit(120);
        $batch = DB::transaction(fn () => $this->write($parsed, $sha, mb_substr($originalName, 0, 255), $user));

        $stored = "{$batch->fiscal_year}/{$sha}.prn";
        $disk->exists($stored) ? $disk->delete($pending) : $disk->move($pending, $stored);
        $batch->update(['stored_path' => $stored]);

        return $batch;
    }

    private function write(array $parsed, string $sha, string $name, User $user): SunImportBatch
    {
        $h = $parsed['header'];
        $fy = $h['fiscal_year'];
        $existing = $this->existingLines($fy, $h['account_from'], $h['account_to']);
        $summary = $this->summary($parsed, $sha, $existing);
        $control = self::controlCode();
        $firstMonth = self::firstMonth();
        $categoriser = FeeCategoriser::fromDatabase();
        $now = now();

        $batch = SunImportBatch::create([
            'uploaded_by' => $user->id, 'source_file' => $name, 'file_sha256' => $sha, 'fiscal_year' => $fy,
            'period_from' => $h['period_from'], 'period_to' => $h['period_to'],
            'account_code_from' => $h['account_from'], 'account_code_to' => $h['account_to'], 'printed_at' => $h['printed_at'],
            'accounts_count' => $summary['accounts'], 'lines_count' => $summary['lines'],
            'control_balance' => $summary['control_balance'],
        ]);
        $versions = [];

        // Accounts: new codes are added; a changed name is updated (the warning was counted above).
        $known = SunAccount::whereIn('code', array_keys($parsed['accounts']))->get()->keyBy('code');
        $newAccounts = [];
        foreach ($parsed['accounts'] as $a) {
            $code = $a['code'];
            if (! $known->has($code)) {
                $newAccounts[] = ['code' => $code, 'is_control' => $code === $control, 'name_as_printed' => mb_substr($a['name'], 0, 80),
                    'first_seen_year' => $fy, 'created_at' => $now, 'updated_at' => $now];
            } elseif ($known[$code]->name_as_printed !== $a['name']) {
                $known[$code]->update(['name_as_printed' => mb_substr($a['name'], 0, 80)]);
            }
        }
        foreach (array_chunk($newAccounts, 500) as $chunk) {
            SunAccount::insert($chunk);
        }
        $accountIds = SunAccount::whereIn('code', array_keys($parsed['accounts']))->pluck('id', 'code');
        $this->openReviews($parsed, $fy, $accountIds);

        // Lines. New rows and version rows are written in chunks as they come, to keep memory low.
        $flush = function (array &$rows, string $table, int $at = 500) {
            if (count($rows) >= $at) {
                DB::table($table)->insert($rows);
                $rows = [];
            }
        };
        $inserted = 0;
        $seen = [];
        $inserts = [];
        $updated = 0;
        $recreated = 0;
        $changedIds = [];
        foreach ($parsed['accounts'] as $a) {
            $code = $a['code'];
            foreach ($a['lines'] as $l) {
                $key = self::key($l['journal_type'], $l['journal_no'], $l['journal_line'], $code);
                $seen[$key] = true;
                $month = SunPeriods::month($l['period'], $fy, $firstMonth);
                $category = $categoriser->classify($l['memo'], $l['journal_type'], $l['amount']);
                $values = [
                    'period' => $l['period'], 'period_month' => $month, 'txn_date' => $l['txn_date'],
                    'date_suspect' => SunPeriods::isSuspect($l['txn_date'], $month), 'reference' => $l['reference'],
                    'allocated' => $l['allocated'], 'amount' => $l['amount'], 'running_balance' => $l['running_balance'],
                    'memo' => $l['memo'], 'billed_month' => FeeCategoriser::billedMonth($l['memo']), 'file_line' => $l['file_line'],
                    'missing_from_latest' => false, 'removed_at' => null,
                ];
                $row = $existing[$key] ?? null;
                if (! $row) {
                    $inserts[] = $values + [
                        'sun_account_id' => $accountIds[$code], 'sun_account_code' => $code, 'created_batch_id' => $batch->id,
                        'last_batch_id' => $batch->id, 'fiscal_year' => $fy, 'journal_type' => $l['journal_type'],
                        'journal_no' => $l['journal_no'], 'journal_line' => $l['journal_line'], 'category' => $category,
                        'is_restricted' => $categoriser->restrictedByDefault($category), 'created_at' => $now, 'updated_at' => $now,
                    ];
                    $inserted++;
                    $flush($inserts, 'sun_ledger_lines');

                    continue;
                }
                // Keep the Treasurer's overrides (§5, §7).
                $values['category'] = $row->category_overridden ? $row->category : $category;
                $values['is_restricted'] = $row->restricted_overridden ? (bool) $row->is_restricted : $categoriser->restrictedByDefault($values['category']);
                if ($this->differs($row, $values)) {
                    $versions[] = $this->version($batch, 'line', $row, SunLedgerLine::VERSIONED, $now);
                    $flush($versions, 'sun_ledger_versions');
                    if ((string) $row->amount !== $l['amount'] && ! $row->removed_at) {
                        $changedIds[] = $row->id;
                    }
                    DB::table('sun_ledger_lines')->where('id', $row->id)->update($values + ['last_batch_id' => $batch->id, 'updated_at' => $now]);
                    $row->removed_at ? $recreated++ : $updated++;
                }
            }
        }
        $flush($inserts, 'sun_ledger_lines', 1);
        // One "created" version per new line, written by the database in one statement.
        DB::insert('INSERT INTO sun_ledger_versions (batch_id, record_type, record_id, previous, created_at, updated_at)
            SELECT ?, ?, id, NULL, ?, ? FROM sun_ledger_lines WHERE created_batch_id = ?', [$batch->id, 'line', $now, $now, $batch->id]);

        // Lines in ISMS for this year and account range that the file no longer has (§10).
        $missing = $existing->reject(fn ($r, $key) => isset($seen[$key]) || $r->removed_at || $r->missing_from_latest);
        foreach ($missing as $row) {
            $versions[] = $this->version($batch, 'line', $row, SunLedgerLine::VERSIONED, $now);
            $flush($versions, 'sun_ledger_versions');
        }
        if ($missing->isNotEmpty()) {
            DB::table('sun_ledger_lines')->whereIn('id', $missing->pluck('id'))->update(['missing_from_latest' => true, 'last_batch_id' => $batch->id, 'updated_at' => $now]);
        }

        // Balances, one per account and year.
        $balances = DB::table('sun_account_balances')->where('fiscal_year', $fy)->whereIn('sun_account_id', $accountIds->values())
            ->get(['id', 'sun_account_id', ...SunAccountBalance::VERSIONED])->keyBy('sun_account_id');
        foreach ($parsed['accounts'] as $a) {
            $code = $a['code'];
            $values = ['opening_balance' => $a['opening'], 'closing_balance' => $a['closing'], 'account_name_as_printed' => mb_substr($a['name'], 0, 80), 'removed_at' => null];
            $row = $balances[$accountIds[$code]] ?? null;
            if (! $row) {
                $id = DB::table('sun_account_balances')->insertGetId($values + [
                    'sun_account_id' => $accountIds[$code], 'fiscal_year' => $fy, 'is_control' => $code === $control,
                    'created_batch_id' => $batch->id, 'last_batch_id' => $batch->id, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $versions[] = ['batch_id' => $batch->id, 'record_type' => 'balance', 'record_id' => $id, 'previous' => null, 'created_at' => $now, 'updated_at' => $now];
            } elseif ($this->differs($row, $values)) {
                $versions[] = $this->version($batch, 'balance', $row, SunAccountBalance::VERSIONED, $now);
                DB::table('sun_account_balances')->where('id', $row->id)->update($values + ['last_batch_id' => $batch->id, 'updated_at' => $now]);
            }
        }

        $flush($versions, 'sun_ledger_versions', 1);

        $batch->update([
            'new_lines' => $inserted + $recreated, 'updated_lines' => $updated, 'missing_lines' => $missing->count(),
            'matched_accounts' => $summary['matched'], 'unmatched_accounts' => $summary['unmatched'], 'new_accounts' => $summary['new_accounts'],
            'warnings' => $summary['warnings'] + ['changed_line_ids' => array_slice($changedIds, 0, 500)],
        ]);
        $this->audit->log($user, 'Imported Sun ledger', 'SunImportBatch', $batch->id, [
            'school_year' => SunPeriods::label($fy), 'accounts' => $summary['accounts'], 'lines' => $summary['lines'],
            'new_lines' => $inserted + $recreated, 'updated_lines' => $updated, 'missing_lines' => $missing->count(),
        ]);

        return $batch;
    }

    /**
     * T3: a linked code whose printed name differs from the name accepted for its student (case
     * and spacing ignored) gets a pending "possible reused code" review for the year; its lines for
     * that year match nobody until the Treasurer resolves it. A review resolved for this exact name
     * isn't reopened (the accepted name was updated); a third name reopens it.
     */
    private function openReviews(array $parsed, int $fy, $accountIds): void
    {
        $links = SunAccountLink::covering($fy)->whereIn('sun_account_id', $accountIds->values())->get()->keyBy('sun_account_id');
        $reviews = SunAccountReview::where('fiscal_year', $fy)->whereIn('sun_account_id', $links->keys())->get()->keyBy('sun_account_id');
        foreach ($parsed['accounts'] as $a) {
            $id = $accountIds[$a['code']];
            $link = $links[$id] ?? null;
            if (! $link || SunLinks::sameName($link->accepted_name, $a['name'])) {
                continue;
            }
            $values = ['previous_name' => $link->accepted_name, 'printed_name' => mb_substr($a['name'], 0, 80), 'status' => SunAccountReview::PENDING, 'resolved_by' => null, 'resolved_at' => null];
            $review = $reviews[$id] ?? null;
            $review ? $review->update($values) : SunAccountReview::create($values + ['sun_account_id' => $id, 'fiscal_year' => $fy]);
        }
    }

    // --- Publish and revert ---------------------------------------------------------------------------

    public function publish(SunImportBatch $batch, bool $publish, User $user): void
    {
        DB::transaction(function () use ($batch, $publish, $user) {
            $batch = SunImportBatch::lockForUpdate()->findOrFail($batch->id);
            if ($batch->isReverted()) {
                throw new ActionRefused('This import was reverted.');
            }
            if ($batch->isPublished() === $publish) {
                throw new ActionRefused($publish ? 'This import is already published.' : 'This import is not published.');
            }
            if ($publish) {
                // Statements are built from the current ledger, which is the newest import's.
                $newer = SunImportBatch::current()->where('fiscal_year', $batch->fiscal_year)->where('id', '>', $batch->id)->value('id');
                if ($newer) {
                    throw new ActionRefused("Import #{$newer} is newer for {$batch->yearLabel()}. Publish that one instead.");
                }
                $this->statements->buildForBatch($batch);
            }
            $batch->update($publish ? ['published_at' => now(), 'published_by' => $user->id] : ['published_at' => null, 'published_by' => null]);
            $this->audit->log($user, $publish ? 'Published Sun ledger import' : 'Unpublished Sun ledger import', 'SunImportBatch', $batch->id,
                ['school_year' => $batch->yearLabel()]);
        });
    }

    /**
     * Undo a batch: what it created is marked removed, what it changed gets its previous values
     * back. Only the latest batch to touch a record may be reverted: if a newer batch changed any
     * of the same lines or balances, the newer one must be reverted first.
     */
    public function revert(SunImportBatch $batch, User $user): void
    {
        DB::transaction(function () use ($batch, $user) {
            $batch = SunImportBatch::lockForUpdate()->findOrFail($batch->id);
            if ($batch->isReverted()) {
                throw new ActionRefused('This import was already reverted.');
            }
            $versions = SunLedgerVersion::where('batch_id', $batch->id)->whereNull('reverted_at')->orderByDesc('id')->get();

            $newer = SunLedgerVersion::whereNull('reverted_at')->where('batch_id', '>', $batch->id)
                ->where(function ($q) use ($versions) {
                    foreach ($versions->groupBy('record_type') as $type => $rows) {
                        $q->orWhere(fn ($w) => $w->where('record_type', $type)->whereIn('record_id', $rows->pluck('record_id')));
                    }
                })->orderByDesc('batch_id')->value('batch_id');
            if ($versions->isNotEmpty() && $newer) {
                $n = SunImportBatch::find($newer);
                throw new ActionRefused("A newer import changed the same lines: #{$n->id}, {$n->source_file}, imported {$n->created_at->format('j M Y H:i')}. Revert that one first.");
            }

            $now = now();
            foreach ($versions as $v) {
                $table = $v->record_type === 'line' ? 'sun_ledger_lines' : 'sun_account_balances';
                DB::table($table)->where('id', $v->record_id)->update(
                    ($v->previous === null ? ['removed_at' => $now] : $v->previous) + ['updated_at' => $now]
                );
            }
            SunLedgerVersion::where('batch_id', $batch->id)->update(['reverted_at' => $now]);
            $batch->update(['reverted_at' => $now, 'reverted_by' => $user->id]);

            $this->audit->log($user, 'Reverted Sun ledger import', 'SunImportBatch', $batch->id, [
                'school_year' => $batch->yearLabel(),
                'lines_removed' => $versions->where('record_type', 'line')->whereNull('previous')->count(),
                'records_restored' => $versions->whereNotNull('previous')->count(),
            ]);
        });
    }

    // --- Helpers ----------------------------------------------------------------------------------------

    public static function controlCode(): string
    {
        return (string) SystemSetting::get('fees.sun_control_account', '1000285005');
    }

    public static function firstMonth(): int
    {
        return max(1, min(12, (int) SystemSetting::get('fees.sun_period_one_month', '4')));
    }

    public static function key(string $journalType, string $journalNo, int|string $journalLine, string $code): string
    {
        return strtoupper($journalType)."|{$journalNo}|".(int) $journalLine."|{$code}";
    }

    /**
     * This year's lines for the report's account range, keyed by the §9 unique key (removed ones
     * included), as plain rows with only the columns compared and versioned, to keep memory low.
     */
    private function existingLines(int $fy, ?string $from, ?string $to)
    {
        return DB::table('sun_ledger_lines')->where('fiscal_year', $fy)
            ->when($from && $to, fn ($q) => $q->whereBetween('sun_account_code', [$from, $to]))
            ->get(['id', 'sun_account_code', 'journal_type', 'journal_no', 'journal_line', 'category_overridden', 'restricted_overridden', ...SunLedgerLine::VERSIONED])
            ->keyBy(fn ($r) => self::key($r->journal_type, $r->journal_no, $r->journal_line, $r->sun_account_code));
    }

    /** Whether any of $values differs from the stored record. */
    private function differs($row, array $values): bool
    {
        foreach ($values as $field => $value) {
            if ($this->normalise($row->{$field}) !== $this->normalise($value)) {
                return true;
            }
        }

        return false;
    }

    private function normalise($value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof Carbon => $value->format('Y-m-d H:i:s'),
            default => (string) $value,
        };
    }

    private function version(SunImportBatch $batch, string $type, $row, array $fields, $now): array
    {
        $previous = [];
        foreach ($fields as $field) {
            $previous[$field] = $row->{$field};
        }

        return ['batch_id' => $batch->id, 'record_type' => $type, 'record_id' => $row->id, 'previous' => json_encode($previous), 'created_at' => $now, 'updated_at' => $now];
    }
}
