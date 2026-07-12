<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        // Natural order so grades read 1,2,…,12 rather than the alphabetical 1,10,11,12,2,…
        $sections = $query->orderByRaw('LENGTH(name), name')->get();

        // Active students not yet placed in any section of the active year (§7.5 "place students").
        $unplacedStudents = Student::where('enrollment_status', 'Enrolled')
            ->whereDoesntHave('enrollments', function ($q) use ($activeYear) {
                $q->where('status', 'Active')
                    ->whereHas('section', fn ($s) => $s->where('academic_year_id', $activeYear?->id));
            })
            ->with('department')
            ->orderBy('name')
            ->get();

        return view('registrar.sections.index', [
            'sections' => $sections,
            'unplacedStudents' => $unplacedStudents,
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
            'name' => [
                'required', 'string', 'max:255',
                // §7.5: section name unique per (academic year, department).
                Rule::unique('sections', 'name')
                    ->where('academic_year_id', $activeYear->id)
                    ->where('department_id', $request->input('department_id')),
            ],
            'department_id' => ['required', 'exists:departments,id'],
            'homeroom_teacher_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('role_type', 'Teacher')],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'name.unique' => 'A section with this name already exists for that department in the active year.',
            'homeroom_teacher_id.exists' => 'The homeroom teacher must be a staff member with the Teacher role.',
        ]);
        $data['academic_year_id'] = $activeYear->id;

        $section = Section::create($data);
        $audit->log($request->user(), 'Created section', 'Section', $section->id);

        return back()->with('status', "Section {$section->name} created.");
    }

    public function update(Request $request, Section $section, AuditService $audit)
    {
        $data = $request->validate([
            'homeroom_teacher_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('role_type', 'Teacher')],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'homeroom_teacher_id.exists' => 'The homeroom teacher must be a staff member with the Teacher role.',
        ]);

        $section->update($data);
        $audit->log($request->user(), 'Updated section', 'Section', $section->id);

        return back()->with('status', "Section {$section->name} updated.");
    }

    /**
     * §7.5 place students: enroll one or more students into a section of the
     * active academic year. A student holds at most one active enrollment per
     * year, and the section's capacity is respected.
     */
    public function enroll(Request $request, Section $section, AuditService $audit)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'distinct', 'exists:students,id'],
        ]);

        $students = Student::whereIn('id', $data['student_ids'])->get();

        if ($inactive = $students->firstWhere('enrollment_status', '!=', 'Enrolled')) {
            return back()->withErrors([
                'student_ids' => "{$inactive->name} is not an active student and cannot be placed.",
            ]);
        }

        $alreadyPlaced = Enrollment::whereIn('student_id', $data['student_ids'])
            ->where('status', 'Active')
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $section->academic_year_id))
            ->with('student')
            ->first();

        if ($alreadyPlaced) {
            $student = $alreadyPlaced->student;

            return back()->withErrors([
                'student_ids' => "{$student->name} is already placed in a section for this academic year.",
            ]);
        }

        $openSeats = $section->capacity - $section->enrollments()->where('status', 'Active')->count();
        if (count($data['student_ids']) > $openSeats) {
            return back()->withErrors([
                'student_ids' => "Section {$section->name} has only {$openSeats} open seat(s) left.",
            ]);
        }

        DB::transaction(function () use ($data, $section, $request, $audit) {
            foreach ($data['student_ids'] as $studentId) {
                $enrollment = Enrollment::create([
                    'student_id' => $studentId,
                    'section_id' => $section->id,
                    'status' => 'Active',
                ]);
                $audit->log($request->user(), 'Enrolled student into section', 'Enrollment', $enrollment->id);
            }
        });

        $count = count($data['student_ids']);

        return back()->with('status', "{$count} student(s) placed into {$section->name}.");
    }
}
