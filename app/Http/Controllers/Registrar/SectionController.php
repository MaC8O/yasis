<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\SectionRequest;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\SubjectOffering;
use App\Models\TeachingAssignment;
use App\Services\AuditService;
use App\Services\SectionService;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    public function __construct(private SectionService $sections)
    {
    }

    public function index(Request $request)
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $yearId = $request->integer('academic_year') ?: $activeYear?->id;
        $search = $request->string('search')->trim()->value();
        $showArchived = $request->boolean('archived');

        $query = Section::query()
            ->with(['department', 'gradeLevel', 'homeroomTeacher.user', 'enrollments', 'teachingAssignments.teacher.user'])
            ->where('academic_year_id', $yearId)
            ->when(! $showArchived, fn ($q) => $q->active());

        if ($departmentId = $request->string('department')->value()) {
            $query->where('department_id', $departmentId);
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('homeroomTeacher.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                ->orWhereHas('teachingAssignments.teacher.user', fn ($u) => $u->where('name', 'like', "%{$search}%")));
        }

        // School order by grade level (KG1, KG2, 1 … 12), sections without a level last.
        $sections = $query->orderByGradeLevel()->get();

        $offeringsByLevel = SubjectOffering::active()->with('subject')
            ->whereIn('grade_level_id', $sections->pluck('grade_level_id')->filter()->unique())
            ->ordered()->get()->groupBy('grade_level_id');
        $coverage = $sections->mapWithKeys(function ($section) use ($offeringsByLevel) {
            $expected = $this->sections->expectedSubjects($section, $offeringsByLevel);
            $assigned = $section->teachingAssignments->pluck('subject_id');

            return [$section->id => ['assigned' => $expected->whereIn('id', $assigned)->count(), 'expected' => $expected->count()]];
        });

        $activeSections = Section::active()->where('academic_year_id', $activeYear?->id)->orderByGradeLevel()->with('enrollments')->get();

        // Active students not yet placed in any section of the active year (§7.5 "place students").
        $unplacedStudents = Student::where('enrollment_status', 'Enrolled')
            ->whereDoesntHave('enrollments', function ($q) use ($activeYear) {
                $q->where('status', 'Active')
                    ->whereHas('section', fn ($s) => $s->where('academic_year_id', $activeYear?->id));
            })
            ->with('department')
            ->orderBy('name')
            ->get();

        $teachers = StaffProfile::where('role_type', 'Teacher')->whereIn('status', ['Active', 'Probation'])
            ->with('user')->get()->sortBy('user.name')->values();
        $homerooms = Section::active()->where('academic_year_id', $yearId)->whereNotNull('homeroom_teacher_id')
            ->get(['id', 'name', 'homeroom_teacher_id'])->groupBy('homeroom_teacher_id');

        return view('registrar.sections.index', [
            'sections' => $sections,
            'grouped' => $sections->groupBy(fn ($s) => $s->department?->name ?? 'No department'),
            'coverage' => $coverage,
            'activeSections' => $activeSections,
            'unplacedStudents' => $unplacedStudents,
            'departments' => Department::academic()->orderBy('name')->get(),
            'gradeLevels' => GradeLevel::active()->ordered()->get(),
            'academicYears' => AcademicYear::orderByDesc('year_label')->get(),
            'activeYear' => $activeYear,
            'yearId' => $yearId,
            'teachers' => $teachers,
            'homerooms' => $homerooms,
            'filters' => ['department' => $request->string('department')->value(), 'search' => $search, 'archived' => $showArchived],
            'stats' => [
                'sections' => $sections->count(),
                'assignedTeachers' => $sections->whereNotNull('homeroom_teacher_id')->count(),
                'studentsPlaced' => $sections->sum(fn ($s) => $s->enrollments->where('status', 'Active')->count()),
                'openSeats' => $sections->sum(fn ($s) => max($s->capacity - $s->enrollments->where('status', 'Active')->count(), 0)),
            ],
        ]);
    }

    public function show(Section $section, TeachingAssignmentService $assignments)
    {
        $section->load(['department', 'gradeLevel', 'academicYear', 'homeroomTeacher.user', 'teachingAssignments.teacher.user', 'teachingAssignments.subject']);

        $expected = $this->sections->expectedSubjects($section);
        $current = $section->teachingAssignments->keyBy('subject_id');
        // Expected subjects first, then any extra subject already assigned outside the expected set.
        $subjects = $expected->concat($section->teachingAssignments->pluck('subject')->filter()
            ->reject(fn ($s) => $expected->contains('id', $s->id)))->values();

        $gradeCounts = $subjects->mapWithKeys(fn ($s) => [$s->id => $assignments->gradeCount($section->id, $s->id)]);

        return view('registrar.sections.show', [
            'section' => $section,
            'roster' => $section->enrollments()->with('student')->orderByDesc('status')->get()
                ->sortBy(fn ($e) => [$e->status !== 'Active', $e->student?->name])->values(),
            'subjects' => $subjects,
            'expectedIds' => $expected->pluck('id'),
            'current' => $current,
            'gradeCounts' => $gradeCounts,
            'teachers' => StaffProfile::where('role_type', 'Teacher')->whereIn('status', ['Active', 'Probation'])
                ->with('user')->get()->sortBy('user.name')->values(),
            'deleteBlockers' => $this->sections->deleteBlockers($section),
            'tab' => request('tab') === 'students' ? 'students' : 'assignments',
        ]);
    }

    public function store(SectionRequest $request)
    {
        $section = $this->sections->create($request->validated(), $request->user());
        $warning = $this->sections->homeroomWarning($section->homeroom_teacher_id, $section->academic_year_id, $section->id);

        return back()->with('status', "Section {$section->name} created.")->with('warning', $warning);
    }

    public function update(SectionRequest $request, Section $section)
    {
        $this->sections->update($section, $request->validated(), $request->user());
        $warning = $this->sections->homeroomWarning($section->homeroom_teacher_id, $section->academic_year_id, $section->id);

        return back()->with('status', "Section {$section->name} updated.")->with('warning', $warning);
    }

    public function destroy(Request $request, Section $section)
    {
        try {
            $this->sections->delete($section, $request->user());
        } catch (ValidationException $e) {
            // Offer Archive right where the refusal is shown.
            return back()->withErrors($e->errors())->with('archiveOffer', $section->id);
        }

        return redirect()->route('registrar.sections.index')->with('status', "Section {$section->name} deleted.");
    }

    public function archive(Request $request, Section $section)
    {
        $this->sections->archive($section, $request->user());

        return redirect()->route('registrar.sections.index')
            ->with('status', "Section {$section->name} archived. Its students, attendance and grades are kept; use “Show archived” to find it.");
    }

    public function restore(Request $request, Section $section)
    {
        $this->sections->restore($section, $request->user());

        return back()->with('status', "Section {$section->name} restored.");
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

        if ($section->isArchived()) {
            return back()->withErrors(['student_ids' => "{$section->name} is archived. Restore it before placing students."]);
        }

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
