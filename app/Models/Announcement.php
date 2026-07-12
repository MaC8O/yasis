<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['author_id', 'title', 'body', 'audience_type', 'audience_id', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function author()
    {
        return $this->belongsTo(StaffProfile::class, 'author_id');
    }

    /**
     * Announcements a staff member should receive: school-wide, all-staff, their
     * own department, or a section they teach / are homeroom for. This is the
     * read-side counterpart to AnnouncementService's audience resolution.
     */
    public function scopeVisibleToStaff($query, StaffProfile $staff)
    {
        $sectionIds = $staff->teachingAssignments()->pluck('section_id')
            ->merge($staff->homeroomSections()->pluck('id'))->unique()->all();

        return $query->where(function ($q) use ($staff, $sectionIds) {
            $q->whereIn('audience_type', ['School', 'Staff'])
                ->orWhere(fn ($d) => $d->where('audience_type', 'Department')->where('audience_id', $staff->department_id))
                ->orWhere(fn ($s) => $s->where('audience_type', 'Section')->whereIn('audience_id', $sectionIds));
        });
    }
}
