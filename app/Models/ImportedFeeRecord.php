<?php

namespace App\Models;

use App\Support\FeeVisibility;
use Illuminate\Database\Eloquent\Model;

class ImportedFeeRecord extends Model
{
    protected $fillable = [
        'student_id', 'import_batch_id', 'raw_student_key', 'raw_student_name', 'txn_date', 'description',
        'amount', 'balance', 'status', 'is_restricted', 'is_held',
    ];

    protected function casts(): array
    {
        return ['txn_date' => 'date', 'is_restricted' => 'boolean', 'is_held' => 'boolean'];
    }

    /** Rows $audience may see — see FeeVisibility for the rules. */
    public function scopeVisibleTo($query, string $audience)
    {
        return FeeVisibility::apply($query, $audience);
    }

    /** Rows a guardian may ever see: published batch, matched, not restricted, not held. */
    public function scopeFamilyVisible($query)
    {
        return FeeVisibility::apply($query, FeeVisibility::FAMILY);
    }

    /**
     * True when the source export names a different student than the ISMS record it matched
     * by ID — usually a typo'd ID in the ledger, so the Treasurer should review it.
     */
    public function getHasNameConflictAttribute(): bool
    {
        if (! $this->student || ! $this->raw_student_name) {
            return false;
        }

        $normalize = fn (string $n) => preg_replace('/\s+/', ' ', mb_strtolower(trim($n)));

        return $normalize($this->raw_student_name) !== $normalize($this->student->name);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function scopeUnmatched($query)
    {
        return $query->whereNull('student_id');
    }
}
