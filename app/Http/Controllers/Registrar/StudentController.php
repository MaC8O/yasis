<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\UserProvisioningService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(private UserProvisioningService $provisioning) {}

    public function index(Request $request)
    {
        $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();

        $query = Student::query()->with(['department', 'guardians.user', 'enrollments.section']);

        if ($search = $request->string('search')->trim()->value()) {
            // Search matches name, student ID, or the class the student is enrolled in.
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('student_id_number', 'like', "%{$search}%")
                ->orWhereHas('enrollments.section', fn ($s) => $s->where('name', 'like', "%{$search}%")));
        }

        if ($departmentId = $request->string('department')->value()) {
            $query->where('department_id', $departmentId);
        }

        // Filter by a specific class/section (active-year enrollment).
        if ($sectionId = $request->string('section')->value()) {
            $query->whereHas('enrollments', fn ($q) => $q->where('section_id', $sectionId)->where('status', 'Active'));
        }

        return view('registrar.students.index', [
            'students' => $query->orderBy('name')->paginate(\App\Support\PerPage::resolve($request))->withQueryString(),
            'departments' => Department::academic()->orderBy('name')->get(),
            'sections' => Section::whereHas('academicYear', fn ($q) => $q->where('is_active', true))->orderByRaw('LENGTH(name), name')->get(),
            'filters' => $request->only(['search', 'department', 'section']),
            'stats' => [
                'active' => Student::where('enrollment_status', 'Enrolled')->count(),
                'newThisYear' => Student::where('enrollment_status', 'Enrolled')->whereYear('admission_date', now()->year)->count(),
                'missingGuardian' => Student::doesntHave('guardians')->count(),
                'transferred' => Student::where('enrollment_status', 'Transferred')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('registrar.students.create', [
            'departments' => Department::academic()->orderBy('name')->get(),
            // Natural order so grades read 1,2,…,12 (not the alphabetical 1,10,11,12,2,…) and every grade up to 12 is visible.
            'sections' => Section::with('department')->whereHas('academicYear', fn ($q) => $q->where('is_active', true))->orderByRaw('LENGTH(name), name')->get(),
            'guardians' => Guardian::with('user')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit, \App\Services\AvatarService $avatars)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'string', 'max:30', 'unique:students,student_id_number'],
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'admission_date' => ['required', 'date'],
            'department_id' => ['required', 'exists:departments,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'guardian_mode' => ['required', 'in:existing,new,none'],
            'guardian_id' => ['nullable', 'required_if:guardian_mode,existing', 'exists:guardians,id'],
            'guardian_name' => ['nullable', 'required_if:guardian_mode,new', 'string', 'max:255'],
            'guardian_email' => ['nullable', 'required_if:guardian_mode,new', 'email', 'unique:users,email'],
            'guardian_relationship' => ['nullable', 'string', 'max:50'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $student = Student::create([
            'student_id_number' => $data['student_id_number'],
            'name' => $data['name'],
            'photo_path' => $request->hasFile('photo') ? $avatars->storeSquare($request->file('photo'), 512, 'student-photos') : null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'admission_date' => $data['admission_date'],
            'department_id' => $data['department_id'],
            'enrollment_status' => 'Enrolled',
        ]);

        if ($data['guardian_mode'] === 'existing') {
            $guardian = Guardian::findOrFail($data['guardian_id']);
        } elseif ($data['guardian_mode'] === 'new') {
            // Same provisioning path as the dedicated guardian screen: a Pending account
            // with a portal setup invite (audit is covered by the student's own log line).
            $guardianUser = $this->provisioning->provisionAccount(
                ['name' => $data['guardian_name'], 'email' => $data['guardian_email']],
                'guardian',
                auditAction: null,
            );
            $guardian = Guardian::create([
                'user_id' => $guardianUser->id,
                'relationship' => $data['guardian_relationship'] ?? null,
                'phone' => $data['guardian_phone'] ?? null,
            ]);
        } else {
            $guardian = null;
        }

        if ($guardian) {
            $student->guardians()->attach($guardian->id, ['is_primary' => true]);
        }

        if (! empty($data['section_id'])) {
            Enrollment::create([
                'student_id' => $student->id,
                'section_id' => $data['section_id'],
                'status' => 'Active',
            ]);
        }

        $audit->log($request->user(), 'Registered student', 'Student', $student->id);

        return redirect()->route('registrar.students.index')->with('status', "{$student->name} registered.");
    }

    public function show(Student $student)
    {
        return view('registrar.students.show', [
            'student' => $student->load(['department', 'guardians.user', 'enrollments.section', 'documentRequests']),
        ]);
    }

    public function edit(Student $student)
    {
        return view('registrar.students.edit', [
            'student' => $student,
            'departments' => Department::academic()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Student $student, AuditService $audit, \App\Services\AvatarService $avatars)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'department_id' => ['required', 'exists:departments,id'],
        ]);

        if ($request->hasFile('photo')) {
            $old = $student->photo_path;
            $data['photo_path'] = $avatars->storeSquare($request->file('photo'), 512, 'student-photos');
            if ($old) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
            }
        }
        unset($data['photo']);

        $student->update($data);
        $audit->log($request->user(), 'Edited student profile', 'Student', $student->id);

        return redirect()->route('registrar.students.show', $student)->with('status', 'Student profile updated.');
    }

    public function transfer(Request $request, Student $student, AuditService $audit)
    {
        $student->update(['enrollment_status' => 'Transferred']);

        DocumentRequest::create([
            'student_id' => $student->id,
            'type' => 'Transfer/Leaving Certificate',
            'status' => 'Draft',
            'prepared_by' => $request->user()->id,
        ]);

        $audit->log($request->user(), 'Transferred/dropped student', 'Student', $student->id);

        return redirect()->route('registrar.students.show', $student)
            ->with('status', "{$student->name} marked Transferred. A Transfer/Leaving Certificate draft was created.");
    }

    public function graduate(Request $request, Student $student, AuditService $audit)
    {
        $student->update(['enrollment_status' => 'Graduated']);

        DocumentRequest::create([
            'student_id' => $student->id,
            'type' => 'Completion Certificate',
            'status' => 'Draft',
            'prepared_by' => $request->user()->id,
        ]);
        DocumentRequest::create([
            'student_id' => $student->id,
            'type' => 'Transcript',
            'status' => 'Draft',
            'prepared_by' => $request->user()->id,
        ]);

        $audit->log($request->user(), 'Processed graduation/exit', 'Student', $student->id);

        return redirect()->route('registrar.students.show', $student)
            ->with('status', "{$student->name} marked Graduated. Completion Certificate and final transcript drafts were created.");
    }
}
