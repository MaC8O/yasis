<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** §10: a school year's fee schedule. The Treasurer manages it; families see it once published. */
class FeeSchedule extends Model
{
    protected $fillable = ['academic_year_id', 'currency', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function items()
    {
        return $this->hasMany(FeeScheduleItem::class);
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
