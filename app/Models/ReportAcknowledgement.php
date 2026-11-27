<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A signature on the report card (§7.1): the class teacher's confirmation, the Principal's release
 * of the term (one row per term, student null) or the guardian's acknowledgement. Withdrawn, never
 * deleted.
 */
class ReportAcknowledgement extends Model
{
    public const CLASS_TEACHER = 'class_teacher';

    public const PRINCIPAL = 'principal';

    public const GUARDIAN = 'guardian';

    protected $fillable = ['term_id', 'student_id', 'section_id', 'role', 'user_id', 'acknowledged_at', 'withdrawn_at', 'withdrawn_by'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('withdrawn_at');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
