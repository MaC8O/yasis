<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 1 of the academic rules (sdp_doc/YASIS_Academic_and_Fee_Rules.md §1): grade levels as data,
 * stored on every section, used for the expected-subjects count and the school order; the Admin
 * manages the levels and the Principal sees them read-only.
 */
class GradeLevelTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private StaffProfile $registrar;

    private StaffProfile $admin;

    private Department $department;

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->department = $this->seedDepartment();
        $this->term = $this->seedAcademicCalendar();
        $this->registrar = $this->makeStaff('registrar', 'Registrar');
        $this->admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
    }

    private function level(string $code): GradeLevel
    {
        return GradeLevel::where('code', $code)->firstOrFail();
    }

    private function section(string $name, ?string $levelCode, array $extra = []): Section
    {
        return Section::create(array_merge([
            'academic_year_id' => $this->term->academic_year_id,
            'department_id' => $this->department->id,
            'grade_level_id' => $levelCode === null ? null : $this->level($levelCode)->id,
            'name' => $name,
            'capacity' => 35,
        ], $extra));
    }

    private function backfillMigration(): object
    {
        return require database_path('migrations/2026_11_25_090100_add_grade_level_to_sections_table.php');
    }

    // ------------------------------------------------------------------ migrations

    public function test_the_school_levels_exist_in_order_after_migrating(): void
    {
        $levels = GradeLevel::ordered()->get();

        $this->assertSame(['KG1', 'KG2', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'], $levels->pluck('code')->all());
        $this->assertSame(range(1, 14), $levels->pluck('sort_order')->all());
        $this->assertSame('Kindergarten 1', $levels[0]->name);
        $this->assertSame('Grade 12', $levels[13]->name);
        $this->assertTrue($levels->every(fn ($l) => $l->archived_at === null));
    }

    public function test_the_backfill_classifies_section_names_and_lists_the_ones_it_cannot(): void
    {
        $names = [
            'Grade 9-A' => '9', 'Grade 12-B' => '12', 'grade10' => '10', 'Grade 1' => '1', 'Grade 8-B' => '8',
            'Kindergarten 1' => 'KG1', 'Kindergarten 2' => 'KG2', 'KG-1' => 'KG1', 'kg 2' => 'KG2', 'KG1-A' => 'KG1',
            'Nursery' => null, 'Kindergarten' => null, 'KG' => null, 'KG 3' => null, 'Grade 13' => null, 'Grade 0' => null, 'Senior Class' => null,
        ];
        foreach (array_keys($names) as $name) {
            $this->section($name, null);
        }
        // A section that already has a level is left alone, even if its name says otherwise.
        $kept = $this->section('Grade 3-A', '4');

        $unclassified = $this->backfillMigration()->backfill();

        foreach ($names as $name => $code) {
            $this->assertSame($code, Section::where('name', $name)->sole()->gradeLevel?->code, $name);
        }
        $this->assertSame(['Nursery', 'Kindergarten', 'KG', 'KG 3', 'Grade 13', 'Grade 0', 'Senior Class'], $unclassified);
        $this->assertSame('4', $kept->fresh()->gradeLevel->code);

        // Running it again changes nothing and reports the same names.
        $this->assertSame($unclassified, $this->backfillMigration()->backfill());
    }

    public function test_a_grade_level_in_use_cannot_be_deleted_from_the_database(): void
    {
        $this->section('Grade 9-A', '9');

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('grade_levels')->where('code', '9')->delete();
    }

    // ------------------------------------------------------------------ Registrar section form

    public function test_a_new_section_needs_a_grade_level(): void
    {
        $this->actingAs($this->registrar->user)->post('/registrar/sections', [
            'name' => 'Grade 7-A', 'department_id' => $this->department->id, 'capacity' => 30,
        ])->assertSessionHasErrors(['grade_level_id' => 'Choose the grade level for this section.']);

        $this->assertSame(0, Section::count());
    }

    public function test_a_new_section_stores_its_level_and_the_audit_records_the_code(): void
    {
        $this->actingAs($this->registrar->user)->post('/registrar/sections', [
            'name' => 'Grade 7-A', 'department_id' => $this->department->id, 'grade_level_id' => $this->level('7')->id, 'capacity' => 30,
        ])->assertSessionHasNoErrors();

        $section = Section::where('name', 'Grade 7-A')->sole();
        $this->assertSame('7', $section->gradeLevel->code);
        $log = AuditLog::where('action', 'Created section')->where('entity_id', $section->id)->sole();
        $this->assertSame('registrar', $log->role);
        $this->assertSame('7', $log->details['grade_level']);
    }

    public function test_changing_a_sections_level_is_audited_by_code(): void
    {
        $section = $this->section('Grade 9-A', '9');

        $this->actingAs($this->registrar->user)->put("/registrar/sections/{$section->id}", ['grade_level_id' => $this->level('10')->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('10', $section->fresh()->gradeLevel->code);
        $log = AuditLog::where('action', 'Updated section')->where('entity_id', $section->id)->sole();
        $this->assertSame('registrar', $log->role);
        $this->assertSame(['from' => '9', 'to' => '10'], $log->details['changes']['grade_level']);
        $this->assertArrayNotHasKey('grade_level_id', $log->details['changes']);
    }

    public function test_a_level_once_set_cannot_be_cleared(): void
    {
        $section = $this->section('Grade 9-A', '9');

        $this->actingAs($this->registrar->user)->put("/registrar/sections/{$section->id}", ['grade_level_id' => ''])
            ->assertSessionHasErrors('grade_level_id');

        $this->assertSame('9', $section->fresh()->gradeLevel->code);
    }

    public function test_an_unclassified_section_can_still_be_edited_and_given_a_level(): void
    {
        $nursery = $this->section('Nursery', null);

        $this->actingAs($this->registrar->user)->put("/registrar/sections/{$nursery->id}", ['name' => 'Nursery', 'grade_level_id' => '', 'capacity' => 20])
            ->assertSessionHasNoErrors();
        $this->assertNull($nursery->fresh()->grade_level_id);
        $this->assertSame(20, $nursery->fresh()->capacity);

        $this->actingAs($this->registrar->user)->put("/registrar/sections/{$nursery->id}", ['grade_level_id' => $this->level('KG1')->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('KG1', $nursery->fresh()->gradeLevel->code);
    }

    public function test_a_retired_level_cannot_be_chosen(): void
    {
        $level = GradeLevel::create(['code' => 'PREK', 'name' => 'Pre-KG', 'sort_order' => 99, 'archived_at' => now()]);

        $this->actingAs($this->registrar->user)->post('/registrar/sections', [
            'name' => 'Pre-KG A', 'department_id' => $this->department->id, 'grade_level_id' => $level->id, 'capacity' => 30,
        ])->assertSessionHasErrors(['grade_level_id' => 'Choose a grade level from the list.']);

        $this->actingAs($this->registrar->user)->get('/registrar/sections')->assertOk()->assertDontSee('>Pre-KG</option>', false);
    }

    // ------------------------------------------------------------------ expected subjects and order

    public function test_expected_subjects_follow_the_stored_level_not_the_name(): void
    {
        // Named "Grade 9-A" but stored as Grade 10: the stored level decides.
        $section = $this->section('Grade 9-A', '10');
        foreach (['MATH9' => 'Mathematics 9', 'MATH10' => 'Mathematics 10', 'HS-BIB' => 'Bible'] as $code => $name) {
            Subject::create(['code' => $code, 'name' => $name, 'department_id' => $this->department->id]);
        }

        $this->actingAs($this->registrar->user)->get("/registrar/sections/{$section->id}")
            ->assertOk()->assertSee('Mathematics 10')->assertSee('Bible')->assertDontSee('Mathematics 9')
            ->assertSee('Grade 10 · High School', false);
        $this->actingAs($this->registrar->user)->get('/registrar/sections')->assertOk()->assertSee('0 / 2');
    }

    public function test_a_kindergarten_or_unclassified_section_expects_the_whole_department_catalogue(): void
    {
        $kg = $this->section('Kindergarten 1', 'KG1');
        $nursery = $this->section('Nursery', null);
        foreach (['PS-ENG' => 'Early English', 'PS-NUM' => 'Numbers', 'MATH9' => 'Mathematics 9'] as $code => $name) {
            Subject::create(['code' => $code, 'name' => $name, 'department_id' => $this->department->id]);
        }

        foreach ([$kg, $nursery] as $section) {
            $this->actingAs($this->registrar->user)->get("/registrar/sections/{$section->id}")
                ->assertOk()->assertSee('Early English')->assertSee('Numbers')->assertSee('Mathematics 9');
        }
    }

    public function test_sections_are_listed_in_grade_level_order_with_unclassified_last(): void
    {
        $this->section('Grade 10-A', '10');
        $this->section('Nursery', null);
        $this->section('Grade 2-B', '2');
        $this->section('Kindergarten 2', 'KG2');
        $this->section('Grade 2-A', '2');
        $this->section('Kindergarten 1', 'KG1');

        $this->actingAs($this->registrar->user)->get('/registrar/sections')->assertOk()
            ->assertSeeInOrder(['Kindergarten 1</a>', 'Kindergarten 2</a>', 'Grade 2-A</a>', 'Grade 2-B</a>', 'Grade 10-A</a>', 'Nursery</a>'], false)
            ->assertSee('no grade level yet')->assertSee('Not set');
    }

    // ------------------------------------------------------------------ Admin page

    public function test_the_admin_adds_a_level_at_the_end_and_it_is_audited(): void
    {
        $this->actingAs($this->admin->user)->post('/admin/grade-levels', ['code' => ' prek ', 'name' => 'Pre-KG'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $level = GradeLevel::where('code', 'PREK')->sole();
        $this->assertSame(15, $level->sort_order);
        $log = AuditLog::where('action', 'Created grade level')->sole();
        $this->assertSame('admin', $log->role);
        $this->assertSame(['code' => 'PREK', 'name' => 'Pre-KG'], $log->details);
    }

    public function test_a_new_level_needs_a_unique_simple_code_and_a_unique_name(): void
    {
        $this->actingAs($this->admin->user)->post('/admin/grade-levels', ['code' => 'kg1', 'name' => 'Another'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin->user)->post('/admin/grade-levels', ['code' => 'K G', 'name' => 'Another'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin->user)->post('/admin/grade-levels', ['code' => 'PREK', 'name' => 'Grade 9'])->assertSessionHasErrors('name');

        $this->assertSame(14, GradeLevel::count());
    }

    public function test_the_admin_renames_a_level_but_its_code_stays(): void
    {
        $level = $this->level('KG1');

        $this->actingAs($this->admin->user)->put("/admin/grade-levels/{$level->id}", ['name' => 'KG One', 'code' => 'X'])
            ->assertSessionHasNoErrors();

        $this->assertSame('KG One', $level->fresh()->name);
        $this->assertSame('KG1', $level->fresh()->code);
        $this->assertSame(['code' => 'KG1', 'from' => 'Kindergarten 1', 'to' => 'KG One'], AuditLog::where('action', 'Renamed grade level')->sole()->details);

        $this->actingAs($this->admin->user)->put("/admin/grade-levels/{$level->id}", ['name' => 'Grade 2'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin->user)->put("/admin/grade-levels/{$level->id}", ['name' => 'KG One'])->assertSessionHas('warning', 'Nothing was changed.');
        $this->assertSame(1, AuditLog::where('action', 'Renamed grade level')->count());
    }

    public function test_the_admin_reorders_levels_by_moving_them(): void
    {
        $this->actingAs($this->admin->user)->post("/admin/grade-levels/{$this->level('2')->id}/move/up")->assertSessionHasNoErrors();
        $this->assertSame(['KG1', 'KG2', '2', '1', '3'], GradeLevel::ordered()->limit(5)->pluck('code')->all());

        $this->actingAs($this->admin->user)->post("/admin/grade-levels/{$this->level('KG1')->id}/move/down")->assertSessionHasNoErrors();
        $this->assertSame(['KG2', 'KG1', '2', '1', '3'], GradeLevel::ordered()->limit(5)->pluck('code')->all());
        $this->assertSame(range(1, 14), GradeLevel::ordered()->pluck('sort_order')->all());

        $log = AuditLog::where('action', 'Moved grade level')->orderByDesc('id')->first();
        $this->assertSame('admin', $log->role);
        $this->assertSame('KG1', $log->details['code']);
        $this->assertSame('down', $log->details['direction']);

        // The first can't go up, the last can't go down; nothing changes.
        $this->actingAs($this->admin->user)->post("/admin/grade-levels/{$this->level('KG2')->id}/move/up")->assertSessionHas('warning');
        $this->actingAs($this->admin->user)->post("/admin/grade-levels/{$this->level('12')->id}/move/down")->assertSessionHas('warning');
        $this->actingAs($this->admin->user)->post("/admin/grade-levels/{$this->level('12')->id}/move/sideways")->assertNotFound();
        $this->assertSame(2, AuditLog::where('action', 'Moved grade level')->count());
    }

    public function test_a_level_used_by_any_section_cannot_be_retired(): void
    {
        $this->section('Grade 9-A', '9');
        $this->section('Grade 8-Z', '8', ['archived_at' => now()]); // archived sections count too

        foreach (['9' => '1 section uses it', '8' => '1 section uses it'] as $code => $reason) {
            $this->actingAs($this->admin->user)->post("/admin/grade-levels/{$this->level($code)->id}/retire")
                ->assertSessionHas('warning', fn ($w) => str_contains($w, $reason));
            $this->assertNull($this->level($code)->archived_at);
        }
        $this->assertSame(0, AuditLog::where('action', 'Retired grade level')->count());
    }

    public function test_an_unused_level_can_be_retired_and_restored(): void
    {
        $level = $this->level('12');

        $this->actingAs($this->admin->user)->post("/admin/grade-levels/{$level->id}/retire")->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertNotNull($level->fresh()->archived_at);
        $this->assertDatabaseHas('grade_levels', ['code' => '12']); // kept, not deleted

        // Retired levels are not offered for sections.
        $this->actingAs($this->registrar->user)->get('/registrar/sections')->assertOk()->assertDontSee('<option value="'.$level->id.'">Grade 12</option>', false);

        $this->actingAs($this->admin->user)->post("/admin/grade-levels/{$level->id}/restore")->assertSessionHas('status');
        $this->assertNull($level->fresh()->archived_at);

        $this->assertSame(['Retired grade level', 'Restored grade level'],
            AuditLog::whereIn('action', ['Retired grade level', 'Restored grade level'])->orderBy('id')->pluck('action')->all());
        $this->assertTrue(AuditLog::where('entity_type', 'GradeLevel')->get()->every(fn ($l) => $l->role === 'admin'));
    }

    public function test_the_admin_page_lists_the_levels_with_their_use(): void
    {
        $this->section('Grade 9-A', '9');

        $this->actingAs($this->admin->user)->get('/admin/grade-levels')->assertOk()
            ->assertSeeInOrder(['Kindergarten 1', 'Kindergarten 2', 'Grade 1', 'Grade 12'])
            ->assertSee('In use, can')
            ->assertSee('Grade Levels'); // navigation
    }

    // ------------------------------------------------------------------ Principal and access

    public function test_the_principal_sees_the_levels_read_only(): void
    {
        $principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');
        $this->section('Grade 9-A', '9');

        $this->actingAs($principal->user)->get('/principal/governance')->assertOk()
            ->assertSee('Grade levels')
            ->assertSeeInOrder(['Kindergarten 1', 'Kindergarten 2', 'Grade 1', 'Grade 9', '1 section', 'Grade 12'])
            ->assertDontSee(route('admin.grade-levels.store'));
    }

    public function test_only_the_admin_can_manage_grade_levels(): void
    {
        $level = $this->level('5');
        $others = [
            $this->makeStaff('principal', 'Principal', 'principal@test.local')->user,
            $this->registrar->user,
            $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user,
        ];

        foreach ($others as $user) {
            $this->actingAs($user)->get('/admin/grade-levels')->assertForbidden();
            $this->actingAs($user)->post('/admin/grade-levels', ['code' => 'X1', 'name' => 'X'])->assertForbidden();
            $this->actingAs($user)->put("/admin/grade-levels/{$level->id}", ['name' => 'Changed'])->assertForbidden();
            $this->actingAs($user)->post("/admin/grade-levels/{$level->id}/move/up")->assertForbidden();
            $this->actingAs($user)->post("/admin/grade-levels/{$level->id}/retire")->assertForbidden();
        }

        $this->assertSame('Grade 5', $level->fresh()->name);
        $this->assertSame(14, GradeLevel::count());
        $this->assertSame(0, AuditLog::where('entity_type', 'GradeLevel')->count());
    }
}
