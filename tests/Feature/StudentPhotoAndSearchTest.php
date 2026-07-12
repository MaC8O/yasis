<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class StudentPhotoAndSearchTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_registration_accepts_and_normalizes_a_student_photo(): void
    {
        Storage::fake('public');
        $this->seedRoles();
        $department = $this->seedDepartment('High School');
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $this->actingAs($registrar->user)->post('/registrar/students', [
            'student_id_number' => 'YAS-2026-9001',
            'name' => 'Saw Htoo Aung',
            'photo' => UploadedFile::fake()->image('portrait.png', 1200, 800),
            'admission_date' => now()->toDateString(),
            'department_id' => $department->id,
            'guardian_mode' => 'none',
        ])->assertRedirect();

        $student = Student::where('student_id_number', 'YAS-2026-9001')->firstOrFail();
        $this->assertNotNull($student->photo_path);
        $this->assertStringEndsWith('.jpg', $student->photo_path); // normalized to JPEG
        Storage::disk('public')->assertExists($student->photo_path);
    }

    public function test_a_photo_can_be_added_after_registration_via_edit(): void
    {
        Storage::fake('public');
        $this->seedRoles();
        $department = $this->seedDepartment('High School');
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $student = Student::create([
            'student_id_number' => 'YAS-2026-9002', 'name' => 'Bulk Imported',
            'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled',
        ]);
        $this->assertNull($student->photo_path);

        $this->actingAs($registrar->user)->put("/registrar/students/{$student->id}", [
            'name' => $student->name,
            'department_id' => $department->id,
            'photo' => UploadedFile::fake()->image('later.jpg', 900, 900),
        ])->assertRedirect();

        $student->refresh();
        $this->assertNotNull($student->photo_path);
        Storage::disk('public')->assertExists($student->photo_path);
    }

    public function test_students_can_be_searched_and_filtered_by_class(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment('High School');
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $grade9 = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 30]);
        $grade10 = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 10-A', 'capacity' => 30]);

        $inNine = Student::create(['student_id_number' => 'N-1', 'name' => 'Nine Student', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        $inTen = Student::create(['student_id_number' => 'T-1', 'name' => 'Ten Student', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        Enrollment::create(['student_id' => $inNine->id, 'section_id' => $grade9->id, 'status' => 'Active']);
        Enrollment::create(['student_id' => $inTen->id, 'section_id' => $grade10->id, 'status' => 'Active']);

        // Free-text search by class name.
        $bySearch = $this->actingAs($registrar->user)->get('/registrar/students?search=Grade 9-A');
        $bySearch->assertSee('Nine Student')->assertDontSee('Ten Student');

        // Class dropdown filter.
        $byFilter = $this->actingAs($registrar->user)->get("/registrar/students?section={$grade10->id}");
        $byFilter->assertSee('Ten Student')->assertDontSee('Nine Student');
    }
}
