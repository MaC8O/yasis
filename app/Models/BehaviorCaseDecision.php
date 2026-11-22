<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BehaviorCaseDecision extends Model
{
    protected $fillable = ['behavior_case_id', 'round', 'role', 'decision', 'reason', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'round' => 'integer'];
    }

    public function decider()
    {
        return $this->belongsTo(StaffProfile::class, 'decided_by');
    }
}
