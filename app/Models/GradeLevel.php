<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A school grade level (KG1, KG2, 1 … 12). Grading, curriculum, report card and fee rules are
 * keyed by `code`, which never changes once created; the Admin may rename, reorder and retire.
 */
class GradeLevel extends Model
{
    /** The school's four report cards (§7.1): layout => [title, accent colour]. */
    public const REPORT_CARD_LAYOUTS = [
        'kg4' => ['KG to Grade 4', 'blue'],
        'g5_9' => ['Grades 5 to 9', 'green'],
        'g10' => ['Grade 10', 'yellow'],
        'g11_12' => ['Grades 11 and 12', 'yellow'],
    ];

    protected $fillable = ['code', 'name', 'sort_order', 'grading_scheme_id', 'report_card_layout', 'archived_at'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'archived_at' => 'datetime'];
    }

    /** Levels that can be chosen for a section (not retired). */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function gradingScheme()
    {
        return $this->belongsTo(GradingScheme::class);
    }

    public function offerings()
    {
        return $this->hasMany(SubjectOffering::class);
    }

    /** The elective slots this level's active curriculum offers, e.g. ['A', 'B', 'C']. */
    public function electiveSlots(): array
    {
        return $this->offerings()->active()->whereNotNull('elective_slot')
            ->orderBy('elective_slot')->distinct()->pluck('elective_slot')->all();
    }
}
