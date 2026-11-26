<?php

namespace Tests\Feature;

use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 4a: `results:compare` prints, for a section and term, today's figures (department grade
 * scale) beside the new ones (grading schemes), so they can be checked on staging before phase 4b
 * changes what families see. It is read-only.
 */
class ResultComparisonTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    public function test_it_prints_the_old_and_new_figures_side_by_side(): void
    {
        $this->seedRoles();
        $this->resultsDepartment = $this->seedDepartment(); // A 90 / B 80 / C 70 / D 60 / F, GPA 4-0
        $this->seedYearWithTerms();
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->grade12Example($section, $this->terms[0]);

        $exit = Artisan::call('results:compare', ['section' => $section->id, 'term' => $this->terms[0]->id]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Grade 12-A', $out);
        $this->assertStringContainsString($student->name, $out);
        // Myanmar 66: the department scale says D (1.00); the Grade 11-12 scheme says C+ (2.33).
        $this->assertMatchesRegularExpression('/MYANMAR\s*\|\s*66(\.00)?\s*\|\s*D\s*\|\s*66\s*\|\s*C\+ \(2\.33\)/', $out);
        // Old "Term GPA" (unweighted average of department GPA points) beside the new GPA.
        $this->assertStringContainsString('GPA 3.04, Second, Pass', $out);
        $this->assertStringContainsString('Old "Term GPA"', $out);
        $this->assertSame(0, \App\Models\StudentResult::count()); // nothing stored
    }

    public function test_it_refuses_an_unknown_section(): void
    {
        $this->seedRoles();
        $this->seedYearWithTerms();

        $this->assertSame(1, Artisan::call('results:compare', ['section' => 999, 'term' => $this->terms[0]->id]));
        $this->assertStringContainsString('No section', Artisan::output());
        $this->assertInstanceOf(Department::class, $this->resultsDepartment);
    }
}
