<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    /** Gradable item types (descriptive only — weighting lives on the category). */
    public const TYPES = [
        'assignment' => 'Assignment',
        'classwork' => 'Classwork',
        'group_work' => 'Group work',
        'test' => 'Test',
        'quiz' => 'Quiz',
    ];

    protected $fillable = ['category_id', 'name', 'type', 'max_score', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    /** Items in use. An archived item keeps its grades but counts in no mark; it can be restored in an open term. */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    protected static function booted(): void
    {
        // Callers that don't choose a type get one inferred from the item name.
        static::creating(function (Assessment $assessment) {
            $assessment->type ??= self::guessType($assessment->name);
        });
    }

    public static function guessType(?string $name): string
    {
        $name = mb_strtolower((string) $name);

        return match (true) {
            str_contains($name, 'quiz') => 'quiz',
            str_contains($name, 'test') || str_contains($name, 'exam') => 'test',
            str_contains($name, 'group') => 'group_work',
            str_contains($name, 'classwork') || str_contains($name, 'class work') => 'classwork',
            default => 'assignment',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function category()
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }
}
