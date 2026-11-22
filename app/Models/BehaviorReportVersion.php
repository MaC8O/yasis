<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** How a report read before an edit. */
class BehaviorReportVersion extends Model
{
    protected $fillable = [
        'behavior_report_id', 'version', 'incident_date', 'behavior_category_id', 'description',
        'is_office_referral', 'recorded_by', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['incident_date' => 'date', 'is_office_referral' => 'boolean', 'recorded_at' => 'datetime', 'version' => 'integer'];
    }

    public function category()
    {
        return $this->belongsTo(BehaviorCategory::class, 'behavior_category_id');
    }

    public function recorder()
    {
        return $this->belongsTo(StaffProfile::class, 'recorded_by');
    }
}
