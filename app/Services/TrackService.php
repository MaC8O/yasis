<?php

namespace App\Services;

use App\Models\Section;
use App\Models\StudentTrack;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Grade 10 and Grades 11-12 tracks (the school's answers to Q6 and Q7): each student takes one
 * whole track of subjects, science or arts, for the school year. Set by the Registrar one section
 * at a time; a track can be changed but not cleared, and each new or changed track is audited in
 * the same transaction. The tracks a grade offers are the ones on its curriculum rows.
 */
class TrackService
{
    public function __construct(protected AuditService $audit) {}

    /**
     * The section grade level's tracks with their subjects, e.g. ['arts' => [History, ...], 'science' => [...]].
     *
     * @return Collection<string, Collection<int, SubjectOffering>>
     */
    public function tracksFor(Section $section): Collection
    {
        if (! $section->grade_level_id) {
            return collect();
        }

        return SubjectOffering::active()->with('subject')->where('grade_level_id', $section->grade_level_id)
            ->whereNotNull('track')->ordered()->get()->groupBy('track')->sortKeys();
    }

    /** Active students of the section, by name. */
    public function roster(Section $section): Collection
    {
        return $section->enrollments()->where('status', 'Active')->with('student')->get()
            ->pluck('student')->filter()->sortBy('name')->values();
    }

    /** student id => track for the section's school year. */
    public function current(Section $section, Collection $studentIds): Collection
    {
        return StudentTrack::where('academic_year_id', $section->academic_year_id)->whereIn('student_id', $studentIds)->pluck('track', 'student_id');
    }

    /**
     * Save the submitted tracks: $tracks[studentId] = track. Already validated by SaveTracksRequest.
     * Returns how many were new or changed.
     *
     * @param  array<int|string, ?string>  $tracks
     */
    public function save(Section $section, array $tracks, User $actor): int
    {
        return DB::transaction(function () use ($section, $tracks, $actor) {
            $existing = StudentTrack::where('academic_year_id', $section->academic_year_id)
                ->whereIn('student_id', array_keys($tracks))->lockForUpdate()->get()->keyBy('student_id');
            $changed = 0;

            foreach ($tracks as $studentId => $track) {
                if ($track === null || $track === '') {
                    continue; // nothing chosen and nothing to keep (clearing is refused by validation)
                }
                $current = $existing->get((int) $studentId);
                if ($current?->track === $track) {
                    continue;
                }

                $from = $current?->track;
                $row = $current ?? new StudentTrack(['student_id' => (int) $studentId, 'academic_year_id' => $section->academic_year_id]);
                $row->fill(['track' => $track, 'set_by' => $actor->id])->save();

                $this->audit->log($actor, $current ? 'Changed student track' : 'Set student track', 'StudentTrack', $row->id, [
                    'student_id' => (int) $studentId, 'academic_year_id' => $section->academic_year_id, 'section' => $section->name,
                    'from' => $from, 'to' => $track,
                ]);
                $changed++;
            }

            return $changed;
        });
    }
}
