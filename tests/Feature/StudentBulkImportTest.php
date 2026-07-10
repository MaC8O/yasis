<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class StudentBulkImportTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_registrar_can_bulk_import_students_and_bad_rows_are_reported(): void
    {
        $this->seedRoles();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $department = $this->seedDepartment('High School');

        Student::create([
            'student_id_number' => 'YAS-2026-0099',
            'first_name' => 'Existing',
            'last_name' => 'Student',
            'admission_date' => now(),
            'department_id' => $department->id,
            'enrollment_status' => 'Enrolled',
        ]);

        $csv = "student_id_number,first_name,last_name,department,section,date_of_birth,gender,religious_background,admission_date,guardian_name,guardian_email,guardian_relationship,guardian_phone\n"
            ."YAS-2026-0201,New,Student,High School,,2011-01-01,Male,Buddhist,2026-06-01,Daw Test,daw.test@example.com,Mother,+95 900-000-111\n"
            ."YAS-2026-0099,Duplicate,Row,High School,,,,,,,,,\n"
            ."YAS-2026-0202,No,Department,Unknown Dept,,,,,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->actingAs($registrar->user)
            ->post(route('registrar.students.import.store'), ['file' => $file]);

        $response->assertRedirect(route('registrar.students.import'));

        $this->assertDatabaseHas('students', ['student_id_number' => 'YAS-2026-0201', 'first_name' => 'New']);
        $this->assertSame(1, Student::where('student_id_number', 'YAS-2026-0099')->count());
        $this->assertDatabaseMissing('students', ['student_id_number' => 'YAS-2026-0202']);

        $newStudent = Student::where('student_id_number', 'YAS-2026-0201')->firstOrFail();
        $this->assertTrue(Guardian::whereHas('user', fn ($q) => $q->where('email', 'daw.test@example.com'))->exists());
        $this->assertTrue($newStudent->guardians()->exists());

        $results = session('importResults');
        $this->assertCount(1, $results['created']);
        $this->assertCount(1, $results['skipped']);
        $this->assertCount(1, $results['errors']);
    }

    public function test_non_registrar_cannot_access_bulk_import(): void
    {
        $this->seedRoles();
        $teacher = $this->makeStaff('teacher', 'Teacher');

        $response = $this->actingAs($teacher->user)->get(route('registrar.students.import'));

        $response->assertForbidden();
    }
}
