<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        $query = Section::query()->with(['department', 'homeroomTeacher.user', 'enrollments'])
            ->where('academic_year_id', $request->integer('academic_year', $activeYear?->id));

        if ($departmentId = $request->string('department')->value()) {
            $query->where('department_id', $departmentId);
        }

        $sections = $query->orderBy('name')->get();

        return view('registrar.sections.index', [
            'sections' => $sections,
            'departments' => Department::orderBy('name')->get(),
            'academicYears' => AcademicYear::orderByDesc('year_label')->get(),
            'activeYear' => $activeYear,
            'teachers' => StaffProfile::where('role_type', 'Teacher')->with('user')->get(),
            'filters' => $request->only(['academic_year', 'department']),
            'stats' => [
                'sections' => $sections->count(),
                'assignedTeachers' => $sections->whereNotNull('homeroom_teacher_id')->count(),
                'studentsPlaced' => $sections->sum(fn ($s) => $s->enrollments->count()),
                'openSeats' => $sections->sum(fn ($s) => max($s->capacity - $s->enrollments->count(), 0)),
            ],
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'homeroom_teacher_id' => ['nullable', 'exists:staff_profiles,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $data['academic_year_id'] = $activeYear->id;

        $section = Section::create($data);
        $audit->log($request->user(), 'Created section', 'Section', $section->id);

        return back()->with('status', "Section {$section->name} created.");
    }

    public function update(Request $request, Section $section, AuditService $audit)
    {
        $data = $request->validate([
            'homeroom_teacher_id' => ['nullable', 'exists:staff_profiles,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $section->update($data);
        $audit->log($request->user(), 'Updated section', 'Section', $section->id);

        return back()->with('status', "Section {$section->name} updated.");
    }
}
