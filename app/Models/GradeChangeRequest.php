<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeChangeRequest extends Model
{
    protected $fillable = [
        'assessment_id', 'student_id', 'term_id', 'old_score', 'new_score', 'reason', 'status',
        'requested_by', 'vp_approved_by', 'vp_approved_at',
        'principal_approved_by', 'principal_approved_at', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'old_score' => 'decimal:2',
            'new_score' => 'decimal:2',
            'vp_approved_at' => 'datetime',
            'principal_approved_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'requested_by');
    }

    public function vpApprovedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'vp_approved_by');
    }

    public function principalApprovedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'principal_approved_by');
    }
}
