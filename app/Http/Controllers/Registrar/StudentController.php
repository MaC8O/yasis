<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::query()->with(['department', 'guardians.user', 'enrollments.section']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('student_id_number', 'like', "%{$search}%"));
        }

        if ($departmentId = $request->string('department')->value()) {
            $query->where('department_id', $departmentId);
        }

        return view('registrar.students.index', [
            'students' => $query->orderBy('last_name')->paginate(15)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'filters' => $request->only(['search', 'department']),
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
            'departments' => Department::orderBy('name')->get(),
            'sections' => Section::with('department')->whereHas('academicYear', fn ($q) => $q->where('is_active', true))->orderBy('name')->get(),
            'guardians' => Guardian::with('user')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'string', 'max:30', 'unique:students,student_id_number'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
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
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'admission_date' => $data['admission_date'],
            'department_id' => $data['department_id'],
            'enrollment_status' => 'Enrolled',
        ]);

        if ($data['guardian_mode'] === 'existing') {
            $guardian = Guardian::findOrFail($data['guardian_id']);
        } elseif ($data['guardian_mode'] === 'new') {
            $guardianUser = User::create([
                'name' => $data['guardian_name'],
                'email' => $data['guardian_email'],
                'password' => Hash::make(Str::password(12)),
                'status' => 'Pending',
            ]);
            $guardianUser->assignRole('guardian');
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

        return redirect()->route('registrar.students.index')->with('status', "{$student->first_name} {$student->last_name} registered.");
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
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Student $student, AuditService $audit)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'department_id' => ['required', 'exists:departments,id'],
        ]);

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
            ->with('status', "{$student->first_name} marked Transferred. A Transfer/Leaving Certificate draft was created.");
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
            ->with('status', "{$student->first_name} marked Graduated. Completion Certificate and final transcript drafts were created.");
    }
}
