<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class StudentImportController extends Controller
{
    public function index()
    {
        return view('registrar.students.import');
    }

    public function template(): Response
    {
        $csv = "student_id_number,name,department,section,date_of_birth,gender,religious_background,admission_date,guardian_name,guardian_email,guardian_relationship,guardian_phone\n"
            ."YAS-2026-0101,Aye Chan,High School,Grade 9-A,2011-05-02,Female,Buddhist,2026-06-01,Daw Aye Aye,daw.ayeaye@example.com,Mother,+95 900-222-333\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="student_import_template.csv"',
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $import = new StudentsImport;
        Excel::import($import, $request->file('file'));
        $rows = $import->rows;

        $created = [];
        $skipped = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            $studentIdNumber = trim((string) ($row['student_id_number'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $departmentName = trim((string) ($row['department'] ?? ''));

            if ($studentIdNumber === '' || $name === '' || $departmentName === '') {
                $errors[] = "Row {$rowNum}: missing required field (student_id_number, name, department).";

                continue;
            }

            if (Student::where('student_id_number', $studentIdNumber)->exists()) {
                $skipped[] = "Row {$rowNum}: {$studentIdNumber} already exists — skipped.";

                continue;
            }

            $department = Department::whereRaw('LOWER(name) = ?', [strtolower($departmentName)])->first();
            if (! $department) {
                $errors[] = "Row {$rowNum}: department \"{$departmentName}\" not recognized.";

                continue;
            }

            $admissionDateRaw = trim((string) ($row['admission_date'] ?? ''));
            try {
                $admissionDate = $admissionDateRaw !== '' ? \Carbon\Carbon::parse($admissionDateRaw) : now();
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: invalid admission_date \"{$admissionDateRaw}\".";

                continue;
            }

            $student = Student::create([
                'student_id_number' => $studentIdNumber,
                'name' => $name,
                'date_of_birth' => trim((string) ($row['date_of_birth'] ?? '')) ?: null,
                'gender' => trim((string) ($row['gender'] ?? '')) ?: null,
                'religious_background' => trim((string) ($row['religious_background'] ?? '')) ?: null,
                'admission_date' => $admissionDate,
                'department_id' => $department->id,
                'enrollment_status' => 'Enrolled',
            ]);

            $sectionName = trim((string) ($row['section'] ?? ''));
            if ($sectionName !== '') {
                $section = Section::where('name', $sectionName)
                    ->where('department_id', $department->id)
                    ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
                    ->first();

                if ($section) {
                    Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);
                } else {
                    $errors[] = "Row {$rowNum}: section \"{$sectionName}\" not found in {$department->name} — student created without enrollment.";
                }
            }

            $guardianEmail = trim((string) ($row['guardian_email'] ?? ''));
            if ($guardianEmail !== '') {
                $guardianUser = User::where('email', $guardianEmail)->first();

                if ($guardianUser) {
                    $guardian = Guardian::where('user_id', $guardianUser->id)->first();
                } else {
                    $guardianUser = User::create([
                        'name' => trim((string) ($row['guardian_name'] ?? '')) ?: $guardianEmail,
                        'email' => $guardianEmail,
                        'password' => Hash::make(Str::password(12)),
                        'status' => 'Pending',
                    ]);
                    $guardianUser->assignRole('guardian');
                    $guardian = Guardian::create([
                        'user_id' => $guardianUser->id,
                        'relationship' => trim((string) ($row['guardian_relationship'] ?? '')) ?: null,
                        'phone' => trim((string) ($row['guardian_phone'] ?? '')) ?: null,
                    ]);
                }

                if ($guardian) {
                    $student->guardians()->syncWithoutDetaching([$guardian->id => ['is_primary' => true]]);
                }
            }

            $created[] = "Row {$rowNum}: {$studentIdNumber} — {$name}";
        }

        $audit->log($request->user(), 'Bulk-imported students ('.count($created).' created)', 'Student');

        return redirect()->route('registrar.students.import')
            ->with('status', count($created)." student(s) imported, ".count($skipped)." skipped, ".count($errors)." error(s).")
            ->with('importResults', ['created' => $created, 'skipped' => $skipped, 'errors' => $errors]);
    }
}
