<?php

namespace App\Services;

use App\Models\Section;
use App\Models\StudentElective;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Grades 11-12 elective choices (sdp_doc/YASIS_Academic_and_Fee_Rules.md §3), set by the Registrar
 * one section at a time: one subject per slot per student per academic year. A choice can be
 * changed but not cleared. Each new or changed choice is audited in the same transaction.
 */
class ElectiveService
{
    public function __construct(protected AuditService $audit)
    {
    }

    /**
     * The section grade level's active elective rows, grouped by slot.
     *
     * @return Collection<string, Collection<int, SubjectOffering>>
     */
    public function slotsFor(Section $section): Collection
    {
        if (! $section->grade_level_id) {
            return collect();
        }

        return SubjectOffering::active()->with('subject')
            ->where('grade_level_id', $section->grade_level_id)
            ->whereNotNull('elective_slot')
            ->ordered()->get()
            ->groupBy('elective_slot')->sortKeys();
    }

    /** Active students of the section, by name. */
    public function roster(Section $section): Collection
    {
        return $section->enrollments()->where('status', 'Active')->with('student')->get()
            ->pluck('student')->filter()->sortBy('name')->values();
    }

    /**
     * Save the submitted choices: $choices[studentId][slot] = subject_offering_id. Already
     * validated by SaveElectivesRequest. Returns how many choices were new or changed.
     *
     * @param  array<int|string, array<string, int|string|null>>  $choices
     */
    public function save(Section $section, array $choices, User $actor): int
    {
        return DB::transaction(function () use ($section, $choices, $actor) {
            $offerings = SubjectOffering::with('subject')->whereIn('id', collect($choices)->flatten()->filter()->unique())->get()->keyBy('id');
            $existing = StudentElective::with('offering.subject')
                ->where('academic_year_id', $section->academic_year_id)
                ->whereIn('student_id', array_keys($choices))
                ->lockForUpdate()->get()
                ->keyBy(fn ($e) => $e->student_id.'|'.$e->elective_slot);
            $changed = 0;

            foreach ($choices as $studentId => $slots) {
                foreach ($slots as $slot => $offeringId) {
                    if ($offeringId === null || $offeringId === '') {
                        continue; // nothing chosen and nothing to keep (clearing is refused by validation)
                    }

                    $current = $existing->get($studentId.'|'.$slot);
                    if ($current && (int) $current->subject_offering_id === (int) $offeringId) {
                        continue;
                    }

                    $from = $current?->offering?->subject?->code;
                    $choice = $current ?? new StudentElective([
                        'student_id' => $studentId,
                        'academic_year_id' => $section->academic_year_id,
                        'elective_slot' => $slot,
                    ]);
                    $choice->subject_offering_id = $offeringId;
                    $choice->save();

                    $this->audit->log($actor, $current ? 'Changed elective choice' : 'Set elective choice', 'StudentElective', $choice->id, [
                        'student_id' => (int) $studentId,
                        'academic_year_id' => $section->academic_year_id,
                        'slot' => $slot,
                        'from' => $from,
                        'to' => $offerings[$offeringId]->subject->code,
                    ]);
                    $changed++;
                }
            }

            return $changed;
        });
    }
}
