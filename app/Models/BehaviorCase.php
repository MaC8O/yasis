<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Expulsion review case: the VP decides first, then the Principal; up to MAX_ROUNDS rounds. */
class BehaviorCase extends Model
{
    public const MAX_ROUNDS = 3;

    public const DECISIONS = ['Expel', 'Monitor', 'Close'];

    protected $fillable = [
        'student_id', 'academic_year_id', 'opened_by_flag_id', 'status', 'round', 'awaiting',
        'outcome', 'resolution', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'round' => 'integer'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function flag()
    {
        return $this->belongsTo(BehaviorFlag::class, 'opened_by_flag_id');
    }

    public function decisions()
    {
        return $this->hasMany(BehaviorCaseDecision::class)->orderBy('round')->orderBy('id');
    }

    public function exitRequest()
    {
        return $this->hasOne(StudentExitRequest::class, 'source_id')->where('source_type', 'BehaviorCase');
    }

    public function isOpen(): bool
    {
        return $this->status === 'Open';
    }
}
