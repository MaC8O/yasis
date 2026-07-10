<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionBatch extends Model
{
    protected $fillable = [
        'from_section_id', 'prepared_by', 'status',
        'vp_approved_by', 'vp_approved_at', 'principal_approved_by', 'principal_approved_at', 'applied_at',
    ];

    protected function casts(): array
    {
        return ['vp_approved_at' => 'datetime', 'principal_approved_at' => 'datetime', 'applied_at' => 'datetime'];
    }

    public function fromSection()
    {
        return $this->belongsTo(Section::class, 'from_section_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'prepared_by');
    }

    public function vpApprovedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'vp_approved_by');
    }

    public function principalApprovedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'principal_approved_by');
    }

    public function items()
    {
        return $this->hasMany(PromotionBatchItem::class);
    }
}
