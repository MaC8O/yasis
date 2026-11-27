<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A weighted category in a gradebook (one section, subject and term). In grade levels that use the
 * gradebook template (KG1 to Grade 10), the categories are the template's five (`template_key` set):
 * teachers can't add, rename, reweight or delete them. A category taken out of use when a gradebook
 * moved onto the template is archived, never deleted, with its items and grades.
 */
class AssessmentCategory extends Model
{
    protected $fillable = ['section_id', 'subject_id', 'term_id', 'name', 'weight_pct', 'template_key', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function isTemplate(): bool
    {
        return $this->template_key !== null;
    }

    /** The template's Attendance category: no items; its score comes from attendance records. */
    public function isAttendance(): bool
    {
        return $this->template_key === GradebookTemplateCategory::ATTENDANCE;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    /** Every item, archived ones included. */
    public function assessments()
    {
        return $this->hasMany(Assessment::class, 'category_id');
    }

    /** The items that count (not archived). */
    public function activeAssessments()
    {
        return $this->hasMany(Assessment::class, 'category_id')->whereNull('archived_at');
    }
}
