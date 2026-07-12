<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $fillable = [
        'student_id', 'type', 'status', 'prepared_by', 'approved_by', 'approved_at',
        'principal_approved_by', 'principal_approved_at', 'generated_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'principal_approved_at' => 'datetime', 'generated_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'prepared_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'approved_by');
    }

    public function principalApprovedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'principal_approved_by');
    }
}
