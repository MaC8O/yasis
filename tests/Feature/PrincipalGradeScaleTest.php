<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\GradeScaleBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * The department grade scale still drives the letters shown until phase 4 switches the result
 * calculation to the grading schemes. The Principal's Setup & Controls card now shows the schemes
 * (GradingSchemeTest); the seeded department scale must stay on academic departments only.
 */
class PrincipalGradeScaleTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_seeder_creates_grade_scales_for_academic_departments_only(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame(0, GradeScaleBand::whereHas('department', fn ($q) => $q->whereNotIn('level', Department::ACADEMIC_LEVELS))->count());
        $this->assertGreaterThan(0, GradeScaleBand::count());
    }
}
