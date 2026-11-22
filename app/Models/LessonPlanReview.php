<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One VP decision on a lesson plan, tied to the version that was reviewed. */
class LessonPlanReview extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['lesson_plan_id', 'lesson_plan_version_id', 'reviewer_id', 'decision', 'comment'];

    public function lessonPlan()
    {
        return $this->belongsTo(LessonPlan::class);
    }

    public function version()
    {
        return $this->belongsTo(LessonPlanVersion::class, 'lesson_plan_version_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(StaffProfile::class, 'reviewer_id');
    }

    public function decisionLabel(): string
    {
        return LessonPlan::STATUS_LABELS[$this->decision] ?? $this->decision;
    }
}
