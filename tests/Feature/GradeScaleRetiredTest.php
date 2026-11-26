<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\GradeScaleBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 4b retired the department grade scale: its Admin page (with its hard-delete code) is gone,
 * nothing writes new bands, and grades come from the grading schemes. The table and its rows stay,
 * because past records may refer to them.
 */
class GradeScaleRetiredTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_the_admin_grade_scale_page_and_its_routes_are_gone(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $this->assertFalse(Route::has('admin.grade-scale.index'));
        $this->assertFalse(Route::has('admin.grade-scale.destroy'));
        $this->actingAs($admin->user)->get('/admin/grade-scale')->assertNotFound();
        $this->actingAs($admin->user)->get('/admin/dashboard')->assertOk()
            ->assertDontSee('Grade Scale (current')
            ->assertSee('Grading Schemes');
    }

    public function test_existing_bands_are_kept_and_the_seeder_writes_none(): void
    {
        $department = Department::create(['name' => 'Old School', 'level' => 'Secondary']);
        $old = GradeScaleBand::create(['department_id' => $department->id, 'letter' => 'A', 'min_score' => 90, 'gpa_point' => 4.0]);

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame([$old->id], GradeScaleBand::pluck('id')->all());
    }
}
