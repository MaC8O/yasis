<?php

namespace Tests\Feature;

use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * A student can only be enrolled in an academic department, so the registration
 * dropdowns must exclude Administrative (staff) units.
 */
class StudentRegistrationDepartmentsTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_registration_department_dropdown_lists_academic_units_only(): void
    {
        $this->seedRoles();
        $this->seedDepartment('High School'); // Secondary (academic)
        Department::create(['name' => 'Finance', 'level' => 'Administrative']);
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $response = $this->actingAs($registrar->user)->get('/registrar/students/create')->assertOk();

        $departments = $response->viewData('departments');
        $this->assertTrue($departments->contains('name', 'High School'));
        $this->assertFalse($departments->contains('name', 'Finance'));
        // Every listed department is an academic level, never Administrative.
        foreach ($departments as $department) {
            $this->assertContains($department->level, Department::ACADEMIC_LEVELS);
        }
    }
}
