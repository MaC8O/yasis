<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One uploaded file of a lesson plan, on the private `lesson_plans` disk. */
class LessonPlanVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'lesson_plan_id', 'version', 'stored_path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by',
    ];

    protected $hidden = ['stored_path'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'size_bytes' => 'integer'];
    }

    public function lessonPlan()
    {
        return $this->belongsTo(LessonPlan::class);
    }

    public function uploader()
    {
        return $this->belongsTo(StaffProfile::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return match ($this->mime_type) {
            'application/pdf' => 'PDF',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint',
            default => 'File',
        };
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;

        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB';
    }
}
