<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Sun ledger account code (§8): linked to at most one student for good (T3), or the Student
 * Control clearing account (§6), or marked "not an ISMS student".
 */
class SunAccount extends Model
{
    protected $fillable = ['code', 'student_id', 'is_control', 'not_isms_student', 'name_as_printed', 'first_seen_year', 'linked_at', 'linked_by'];

    protected function casts(): array
    {
        return ['is_control' => 'boolean', 'not_isms_student' => 'boolean', 'linked_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function lines()
    {
        return $this->hasMany(SunLedgerLine::class);
    }

    public function balances()
    {
        return $this->hasMany(SunAccountBalance::class);
    }

    /** Waiting for the Treasurer: not linked, not the control account, not marked as outside the ISMS. */
    public function scopeUnmatched($query)
    {
        return $query->whereNull('student_id')->where('is_control', false)->where('not_isms_student', false);
    }
}
