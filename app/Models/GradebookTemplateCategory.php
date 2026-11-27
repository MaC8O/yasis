<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One of the school's five gradebook template categories (sdp_doc/YASIS_Academic_and_Fee_Rules.md
 * §4.1). `key` and `source` are fixed; the Admin edits the name and weight. `source` is `items`
 * (teachers add graded items) or `attendance` (calculated from attendance records).
 */
class GradebookTemplateCategory extends Model
{
    public const ATTENDANCE = 'attendance';

    public const EXAMINATIONS = 'examinations';

    protected $fillable = ['key', 'name', 'weight_pct', 'sort_order', 'source', 'pending_question'];

    protected function casts(): array
    {
        return ['weight_pct' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function isAttendance(): bool
    {
        return $this->source === 'attendance';
    }

    public function pendingQuestions(): array
    {
        return $this->pending_question ? array_values(array_filter(array_map('trim', explode(',', $this->pending_question)))) : [];
    }
}
