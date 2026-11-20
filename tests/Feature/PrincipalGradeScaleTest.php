<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\GradeScaleBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Principal › Setup & Controls › Grade scale: only academic departments, in school order,
 * with score ranges and no fake "0.0" GPA for descriptive (lower-school) scales.
 */
class PrincipalGradeScaleTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function band(Department $d, string $letter, int $min, ?float $gpa): void
    {
        GradeScaleBand::create(['department_id' => $d->id, 'letter' => $letter, 'min_score' => $min, 'gpa_point' => $gpa]);
    }

    public function test_grade_scale_card_shows_only_academic_departments_in_school_order(): void
    {
        $this->seedRoles();
        $this->seedAcademicCalendar();
        $principal = $this->makeStaff('principal', 'Principal');

        $high = $this->seedDepartment('High School'); // GPA bands A..F via the helper
        $middle = Department::create(['name' => 'Middle School', 'level' => 'Secondary']);
        $this->band($middle, 'A', 90, 4.0);
        $elementary = Department::create(['name' => 'Elementary', 'level' => 'Primary']);
        $this->band($elementary, 'E', 85, null);
        $this->band($elementary, 'S', 60, null);
        $pre = Department::create(['name' => 'Pre-School', 'level' => 'Early Years']);
        $this->band($pre, 'O', 80, null);
        foreach (['Finance', 'Canteen', 'Maintenance'] as $admin) {
            $d = Department::create(['name' => $admin, 'level' => 'Administrative']);
            $this->band($d, 'A', 90, 4.0); // stray bands from older seeds must stay hidden
        }

        $response = $this->actingAs($principal->user)->get('/principal/governance')->assertOk();

        $response->assertDontSee('<p class="font-semibold text-sm">Finance</p>', false)
            ->assertDontSee('<p class="font-semibold text-sm">Canteen</p>', false)
            ->assertDontSee('<p class="font-semibold text-sm">Maintenance</p>', false)
            ->assertSeeInOrder(['Pre-School', 'Elementary', 'Middle School', 'High School'])
            ->assertSee('Descriptive (no GPA)')
            ->assertSee('GPA scale')
            ->assertSee('90–100%')       // top band runs to 100
            ->assertSee('80–89%')        // next band ends below the one above it
            ->assertSee('85–100%')
            ->assertSee('60–84%')
            ->assertSee('Set by the Admin');

        // Descriptive scales show "—", never a fake "0.0" GPA.
        $elementaryCard = substr($response->getContent(), strpos($response->getContent(), '>Elementary<'), 1500);
        $this->assertStringContainsString('—', $elementaryCard);
        $this->assertStringNotContainsString('0.0', $elementaryCard);
    }

    public function test_seeder_creates_grade_scales_for_academic_departments_only(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame(0, GradeScaleBand::whereHas('department', fn ($q) => $q->whereNotIn('level', Department::ACADEMIC_LEVELS))->count());
        $this->assertGreaterThan(0, GradeScaleBand::count());
    }
}
