<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PrincipalRegistrationController extends Controller
{
    public function create()
    {
        return view('principal.registration.create', [
            'departments' => Department::whereIn('level', ['Secondary', 'Primary', 'Early Years'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'string', 'max:30', 'unique:students,student_id_number'],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'admission_date' => ['required', 'date'],
        ]);
        $data['enrollment_status'] = 'Enrolled';

        $student = Student::create($data);
        $audit->log($request->user(), 'Assisted student registration', 'Student', $student->id);

        return redirect()->route('principal.dashboard')
            ->with('status', "{$student->name} registered. The Registrar can complete guardian linking and section placement.");
    }
}
