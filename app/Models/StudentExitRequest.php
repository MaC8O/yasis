<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExitRequest extends Model
{
    protected $fillable = [
        'student_id', 'source_type', 'source_id', 'reason', 'status',
        'processed_by', 'processed_at', 'withdrawn_by', 'withdrawn_at', 'withdraw_reason',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function processor()
    {
        return $this->belongsTo(StaffProfile::class, 'processed_by');
    }

    public function withdrawer()
    {
        return $this->belongsTo(StaffProfile::class, 'withdrawn_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }
}
