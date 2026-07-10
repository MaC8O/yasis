<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportedFeeRecord extends Model
{
    protected $fillable = ['student_id', 'import_batch_id', 'raw_student_key', 'txn_date', 'amount', 'balance', 'status', 'is_restricted'];

    protected function casts(): array
    {
        return ['txn_date' => 'date', 'is_restricted' => 'boolean'];
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
