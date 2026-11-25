<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\SaveElectivesRequest;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\StudentElective;
use App\Services\ElectiveService;
use Illuminate\Http\Request;

/**
 * §3 Grades 11-12 elective choices, set by the Registrar per section. Sections are listed when
 * their grade level's curriculum has elective slots, so this follows the data, not fixed grades.
 */
class ElectiveController extends Controller
{
    public function __construct(private ElectiveService $electives)
    {
    }

    public function index()
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        $sections = Section::active()->where('academic_year_id', $activeYear?->id)
            ->whereNotNull('grade_level_id')
            ->whereHas('gradeLevel.offerings', fn ($q) => $q->active()->whereNotNull('elective_slot'))
            ->with('gradeLevel')->orderByGradeLevel()->get()
            ->map(function (Section $section) {
                $slots = $this->electives->slotsFor($section)->keys();
                $students = $this->electives->roster($section);
                $chosen = StudentElective::where('academic_year_id', $section->academic_year_id)
                    ->whereIn('student_id', $students->pluck('id'))->whereIn('elective_slot', $slots)->count();

                return ['section' => $section, 'slots' => $slots, 'students' => $students->count(), 'missing' => $students->count() * $slots->count() - $chosen];
            });

        return view('registrar.electives.index', ['activeYear' => $activeYear, 'rows' => $sections]);
    }

    public function show(Section $section)
    {
        $slots = $this->electives->slotsFor($section);
        abort_if($slots->isEmpty(), 404);

        $students = $this->electives->roster($section);
        $choices = StudentElective::where('academic_year_id', $section->academic_year_id)
            ->whereIn('student_id', $students->pluck('id'))->get()
            ->mapWithKeys(fn ($e) => [$e->student_id.'|'.$e->elective_slot => $e->subject_offering_id]);

        return view('registrar.electives.show', [
            'section' => $section->load(['gradeLevel', 'academicYear']),
            'slots' => $slots,
            'students' => $students,
            'choices' => $choices,
        ]);
    }

    public function update(SaveElectivesRequest $request, Section $section)
    {
        abort_if($this->electives->slotsFor($section)->isEmpty(), 404);

        $changed = $this->electives->save($section, $request->validated('choices'), $request->user());

        return back()->with('status', $changed === 0 ? 'No choices were changed.' : "{$changed} elective ".($changed === 1 ? 'choice' : 'choices').' saved.');
    }
}
