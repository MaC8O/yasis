<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A school grade level (KG1, KG2, 1 … 12). Grading, curriculum, report card and fee rules are
 * keyed by `code`, which never changes once created; the Admin may rename, reorder and retire.
 */
class GradeLevel extends Model
{
    protected $fillable = ['code', 'name', 'sort_order', 'archived_at'];

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
}
