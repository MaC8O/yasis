<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportedFeeRecord extends Model
{
    protected $fillable = ['student_id', 'import_batch_id', 'raw_student_key', 'txn_date', 'amount', 'balance', 'status', 'is_restricted', 'is_held'];

    protected function casts(): array
    {
        return ['txn_date' => 'date', 'is_restricted' => 'boolean', 'is_held' => 'boolean'];
    }

    /** Rows a guardian/student may ever see: published batch, not restricted, not held. */
    public function scopeFamilyVisible($query)
    {
        return $query->where('is_restricted', false)
            ->where('is_held', false)
            ->whereHas('importBatch', fn ($q) => $q->whereNotNull('published_at'));
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
