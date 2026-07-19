<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Verifies the audit log's tamper-evidence hash chain. Any post-hoc edit to a hashed
 * row, or deletion of one from the middle, breaks the chain and is reported here.
 */
class AuditIntegrityService
{
    /**
     * Walk the chain in insertion order.
     *
     * @return array{ok: bool, checked: int, brokenAtId: int|null, reason: string|null}
     */
    public function verify(): array
    {
        $checked = 0;
        $prevHash = null;
        $seenHashed = false;

        foreach (AuditLog::orderBy('id')->cursor() as $row) {
            // Rows written before the tamper-evidence migration have no hash. They may only
            // appear at the head of the log; one appearing after hashed rows means a hashed
            // row was replaced with an unhashed forgery.
            if ($row->hash === null) {
                if ($seenHashed) {
                    return $this->broken($checked, $row->id, "Row #{$row->id} has no hash but follows hash-protected rows.");
                }

                continue;
            }

            $seenHashed = true;

            // Linkage: a row must point at the previous hashed row's hash. A mismatch means a
            // row between them was deleted, or rows were reordered.
            if ($row->prev_hash !== $prevHash) {
                return $this->broken($checked, $row->id, "Chain linkage broken at row #{$row->id} — a record may have been deleted or reordered.");
            }

            // Content integrity: the stored hash must match a fresh HMAC of the row's content.
            if (! hash_equals($row->hash, $row->computeHash($row->prev_hash))) {
                return $this->broken($checked, $row->id, "Row #{$row->id} no longer matches its hash — its content was modified.");
            }

            $prevHash = $row->hash;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'brokenAtId' => null, 'reason' => null];
    }

    private function broken(int $checked, int $brokenAtId, string $reason): array
    {
        return ['ok' => false, 'checked' => $checked, 'brokenAtId' => $brokenAtId, 'reason' => $reason];
    }
}
