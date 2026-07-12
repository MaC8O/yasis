<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $fillable = [
        'academic_year_id', 'title', 'event_type', 'status', 'start_date', 'end_date',
        'description', 'created_by', 'created_by_role', 'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /** Workflow: Registrar submissions land as Pending; Admin approval makes them Published (visible to all). */
    public const STATUS_PENDING = 'Pending';

    public const STATUS_PUBLISHED = 'Published';

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Event types and the badge colour used to render them. "YASIS Event" is the
     * catch-all for school activities/functions; Holiday/Exam/Term Break are the
     * calendar-shaping types the school marks up front.
     */
    public const TYPES = [
        'Holiday' => 'pink',
        'Exam' => 'yellow',
        'Term Break' => 'blue',
        'Meeting' => 'neutral',
        'Activity' => 'green',
        'Other' => 'neutral',
    ];

    /** Accent hex per type, shared by every calendar view (grid chips, rails, tiles). */
    public const TYPE_HEX = [
        'Holiday' => '#c0392b',
        'Exam' => '#b7960b',
        'Term Break' => '#2f6fb0',
        'Meeting' => '#6b7280',
        'Activity' => '#2E8B57',
        'Other' => '#8b5cf6',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** Inclusive end date — single-day events end the day they start. */
    public function getEffectiveEndAttribute()
    {
        return $this->end_date ?? $this->start_date;
    }

    public function getColorAttribute(): string
    {
        return self::TYPES[$this->event_type] ?? 'neutral';
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        $start = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Any event whose span overlaps the month window.
        return $query->where('start_date', '<=', $end)
            ->where(function ($q) use ($start) {
                $q->whereNull('end_date')->where('start_date', '>=', $start)
                    ->orWhere('end_date', '>=', $start);
            });
    }
}
