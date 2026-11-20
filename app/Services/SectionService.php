<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\PromotionBatch;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * §7.5 Registrar-owned sections: create, edit, homeroom, archive and guarded delete.
 * Every change is written together with its audit row in one transaction.
 */
class SectionService
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function create(array $data, User $actor): Section
    {
        return DB::transaction(function () use ($data, $actor) {
            $section = Section::create($data);
            $this->audit->log($actor, 'Created section', 'Section', $section->id, ['name' => $section->name]);

            return $section;
        });
    }

    public function update(Section $section, array $data, User $actor): Section
    {
        return DB::transaction(function () use ($section, $data, $actor) {
            $section->fill($data);
            $changes = collect($section->getDirty())
                ->map(fn ($to, $field) => ['from' => $section->getOriginal($field), 'to' => $to])
                ->all();
            $section->save();

            $this->audit->log($actor, 'Updated section', 'Section', $section->id, $changes ? ['changes' => $changes] : null);

            return $section;
        });
    }

    /**
     * Warn (never block) when the chosen teacher is already homeroom elsewhere this year.
     */
    public function homeroomWarning(?int $teacherId, int $academicYearId, ?int $exceptSectionId = null): ?string
    {
        if (! $teacherId) {
            return null;
        }

        $others = Section::active()
            ->where('academic_year_id', $academicYearId)
            ->where('homeroom_teacher_id', $teacherId)
            ->when($exceptSectionId, fn ($q) => $q->where('id', '!=', $exceptSectionId))
            ->orderBy('name')->pluck('name');

        if ($others->isEmpty()) {
            return null;
        }

        return 'Note: this teacher is also homeroom teacher for '.$others->implode(', ').'.';
    }

    /**
     * Records that would be destroyed (or that block the delete) if this section were removed.
     *
     * @return list<string> human-readable reasons; empty means the section can be deleted
     */
    public function deleteBlockers(Section $section): array
    {
        $reasons = [];

        if ($n = $section->enrollments()->count()) {
            $reasons[] = "{$n} student enrollment".($n === 1 ? '' : 's');
        }
        if ($n = AttendanceRecord::where('section_id', $section->id)->count()) {
            $reasons[] = "{$n} attendance record".($n === 1 ? '' : 's');
        }
        if ($n = Grade::whereHas('assessment.category', fn ($q) => $q->where('section_id', $section->id))->count()) {
            $reasons[] = "{$n} grade".($n === 1 ? '' : 's');
        }
        if ($n = PromotionBatch::where('from_section_id', $section->id)->count()) {
            $reasons[] = "{$n} promotion batch".($n === 1 ? '' : 'es');
        }

        return $reasons;
    }

    public function delete(Section $section, User $actor): void
    {
        if ($reasons = $this->deleteBlockers($section)) {
            throw ValidationException::withMessages([
                'section' => "{$section->name} can't be deleted because it has ".implode(', ', $reasons)
                    .'. Archive it instead to keep its history.',
            ]);
        }

        DB::transaction(function () use ($section, $actor) {
            $id = $section->id;
            $name = $section->name;
            // Teaching assignments and empty gradebook categories go with it (FK cascade).
            $section->delete();
            $this->audit->log($actor, 'Deleted section', 'Section', $id, ['name' => $name]);
        });
    }

    public function archive(Section $section, User $actor): void
    {
        DB::transaction(function () use ($section, $actor) {
            $section->update(['archived_at' => now()]);
            $this->audit->log($actor, 'Archived section', 'Section', $section->id);
        });
    }

    public function restore(Section $section, User $actor): void
    {
        DB::transaction(function () use ($section, $actor) {
            $section->update(['archived_at' => null]);
            $this->audit->log($actor, 'Restored archived section', 'Section', $section->id);
        });
    }

    /**
     * Subjects a section is expected to have a teacher for: its department's catalogue, skipping
     * subjects coded for a different grade (ENG10 is not expected in Grade 9). The catalogue has
     * no grade field, so this relies on the code naming convention (trailing grade number).
     *
     * @return Collection<int, Subject>
     */
    public function expectedSubjects(Section $section, ?Collection $departmentSubjects = null): Collection
    {
        $subjects = $departmentSubjects ?? Subject::where('department_id', $section->department_id)->orderBy('name')->get();
        $grade = $section->gradeNumber();

        return $subjects->filter(function (Subject $subject) use ($grade) {
            if ($grade === null || ! preg_match('/(\d{1,2})$/', $subject->code, $m)) {
                return true;
            }

            return (int) $m[1] === $grade;
        })->values();
    }
}
