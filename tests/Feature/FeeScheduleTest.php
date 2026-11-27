<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\FeeSchedule;
use App\Models\FeeScheduleItem;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\FeeScheduleService;
use App\Support\FeeSheet2026;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/** Phase 8a (sdp_doc/YASIS_Academic_and_Fee_Rules.md §10): the fee schedule. Invented names only. */
class FeeScheduleTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $treasurer;

    private FeeSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms(); // the 2026-2027 school year
        $this->treasurer = $this->makeStaff('treasurer', 'Treasurer', 'treasurer@test.local');
        $this->schedule = FeeSchedule::findOrFail(FeeSheet2026::load());
    }

    private function item(string $levelCode, string $type): FeeScheduleItem
    {
        return $this->schedule->items()->where('fee_type', $type)
            ->where('grade_level_id', GradeLevel::where('code', $levelCode)->value('id'))->sole();
    }

    /** The whole grid as the form sends it, with $changes (item id => [amount, instalments]) applied. */
    private function grid(array $changes = []): array
    {
        return $this->schedule->items()->get()->mapWithKeys(fn ($i) => [$i->id => array_merge(
            ['amount' => (int) $i->amount] + ($i->fee_type === 'tuition' ? ['instalments' => $i->instalments] : []),
            $changes[$i->id] ?? [],
        )])->all();
    }

    private function save(array $payload)
    {
        return $this->actingAs($this->treasurer->user)->put("/treasurer/fee-schedule/{$this->schedule->id}", $payload);
    }

    private function guardianOf(string $levelCode): array
    {
        $student = $this->pupil('Ei Mon Kyaw', $this->levelSection("Grade {$levelCode}-A", $levelCode));
        $user = User::create(['name' => 'Daw Khin Lay', 'email' => 'parent@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $user->assignRole('guardian');
        Guardian::create(['user_id' => $user->id, 'relationship' => 'Mother', 'phone' => '09'])->students()->attach($student->id, ['is_primary' => true]);

        return [$user, $student];
    }

    public function test_the_2026_2027_sheet_loads_every_grade_with_its_instalments_and_flags(): void
    {
        $service = app(FeeScheduleService::class);
        $this->assertSame(42, $this->schedule->items()->count());
        $this->assertFalse($this->schedule->isPublished());

        foreach (['KG1' => ['250000', '2400000', '600000'], '6' => ['250000', '2550000', '637500'], '7' => ['300000', '2550000', '637500'],
            '9' => ['300000', '2600000', '650000'], '10' => ['300000', '2500000', '625000'], '12' => ['300000', '2600000', '650000']] as $code => [$yearly, $tuition, $each]) {
            $lines = $service->forLevel(GradeLevel::where('code', $code)->first(), $this->year);
            $this->assertSame([$yearly, $tuition, 4, [$each, $each, $each, $each], '20000', true],
                [$lines['yearly']['amount'], $lines['tuition']['amount'], $lines['tuition']['instalments'], $lines['tuition']['per_instalment'],
                    $lines['deposit']['amount'], $lines['deposit']['new_students_only']], "Grade {$code}");
        }
        $this->assertSame('Q17, Q18', $this->item('9', 'tuition')->pending_question);
        $this->assertSame('Q18', $this->item('8', 'tuition')->pending_question);
        $this->assertSame('Q18', $this->item('8', 'deposit')->pending_question);
        $this->assertNull(FeeSheet2026::load()); // never loaded twice
    }

    public function test_instalments_are_whole_kyats_and_the_last_takes_the_remainder(): void
    {
        $service = app(FeeScheduleService::class);
        $this->assertSame(['637500', '637500', '637500', '637501'], $service->instalments('2550001', 4));
        $this->assertSame(['33', '33', '34'], $service->instalments('100', 3));
        $this->assertSame(['250000'], $service->instalments('250000.00', 1));
    }

    public function test_the_treasurer_saves_changes_audited_with_before_and_after(): void
    {
        $tuition9 = $this->item('9', 'tuition');
        $this->save(['items' => $this->grid([$tuition9->id => ['amount' => 2550000]])])->assertSessionHas('status', 'Fee schedule saved (1 change).');

        $this->assertSame('2550000.00', (string) $tuition9->fresh()->amount);
        $log = AuditLog::where('action', 'Updated fee schedule')->sole();
        $this->assertSame('treasurer', $log->role);
        $this->assertSame([['grade' => 'Grade 9', 'fee' => 'Tuition', 'changes' => ['amount' => ['from' => '2600000', 'to' => '2550000']]]], $log->details['changes']);
        $this->assertArrayNotHasKey('reason', $log->details); // not published yet

        $this->save(['items' => $this->grid()])->assertSessionHas('warning', 'Nothing was changed.');
        $this->save(['items' => $this->grid([$tuition9->id => ['amount' => -1]])])->assertSessionHasErrors();
        $this->save(['items' => $this->grid([$tuition9->id => ['amount' => '2550000.50']])])->assertSessionHasErrors();
        $this->save(['items' => $this->grid([$tuition9->id => ['instalments' => 13]])])->assertSessionHasErrors();
    }

    public function test_a_published_schedule_needs_a_reason_for_every_change(): void
    {
        $this->actingAs($this->treasurer->user)->post("/treasurer/fee-schedule/{$this->schedule->id}/publish")->assertSessionHas('status');
        $deposit = $this->item('3', 'deposit');

        $this->save(['items' => $this->grid([$deposit->id => ['amount' => 25000]])])->assertSessionHasErrors('reason');
        $this->assertSame('20000.00', (string) $deposit->fresh()->amount);

        $this->save(['items' => $this->grid([$deposit->id => ['amount' => 25000]]), 'reason' => 'Board raised the deposit for 2026-2027'])
            ->assertSessionHas('status');
        $log = AuditLog::where('action', 'Updated fee schedule')->sole();
        $this->assertSame(['Board raised the deposit for 2026-2027', true], [$log->details['reason'], $log->details['published']]);
        $this->assertSame(['from' => '20000', 'to' => '25000'], $log->details['changes'][0]['changes']['amount']);
    }

    public function test_confirming_a_question_clears_only_that_flag(): void
    {
        $this->save(['items' => $this->grid(), 'confirmed' => ['Q18']])->assertSessionHas('status');

        $this->assertSame('Q17', $this->item('9', 'tuition')->pending_question);
        $this->assertSame(0, $this->schedule->items()->where('pending_question', 'like', '%Q18%')->count());
        $this->assertSame(1, $this->schedule->items()->whereNotNull('pending_question')->count());
    }

    public function test_starting_a_year_copies_the_amounts_unpublished_and_without_flags(): void
    {
        $this->actingAs($this->treasurer->user)->post("/treasurer/fee-schedule/{$this->schedule->id}/publish");
        $this->save(['items' => $this->grid([$this->item('9', 'tuition')->id => ['amount' => 2650000]]), 'reason' => 'Corrected']);
        $next = AcademicYear::create(['year_label' => '2027-2028', 'is_active' => false]);

        $this->actingAs($this->treasurer->user)->post("/treasurer/fee-schedule/start/{$next->id}")->assertSessionHas('status');
        $copy = FeeSchedule::where('academic_year_id', $next->id)->sole();
        $this->assertFalse($copy->isPublished());
        $this->assertSame(42, $copy->items()->count());
        $this->assertSame(0, $copy->items()->whereNotNull('pending_question')->count());
        $this->assertSame('2650000.00', (string) $copy->items()->where('fee_type', 'tuition')
            ->where('grade_level_id', GradeLevel::where('code', '9')->value('id'))->value('amount'));
        $this->assertSame('2026-2027', AuditLog::where('action', 'Started fee schedule')->sole()->details['copied_from']);

        $this->actingAs($this->treasurer->user)->post("/treasurer/fee-schedule/start/{$next->id}")->assertSessionHas('warning', '2027-2028 already has a fee schedule.');
    }

    public function test_families_see_only_a_published_schedule_for_their_childs_grade(): void
    {
        [$guardian, $student] = $this->guardianOf('9');
        $url = "/guardian/fees?child={$student->id}";
        $this->actingAs($guardian)->get($url)->assertOk()->assertDontSee('data-fee-card', false);

        $this->actingAs($this->treasurer->user)->post("/treasurer/fee-schedule/{$this->schedule->id}/publish");
        $this->actingAs($guardian)->get($url)->assertOk()
            ->assertSee('2026-2027 fees for Grade 9')
            ->assertSeeInOrder(['Yearly fee', 'One-time', '300,000 Ks', 'Tuition', 'paid in 4 instalments', '2,600,000 Ks', '4 instalments of 650,000 Ks', 'Refundable deposit', 'new students only', '20,000 Ks'])
            ->assertDontSee('Q17')->assertDontSee('Q18');

        $this->actingAs($this->treasurer->user)->post("/treasurer/fee-schedule/{$this->schedule->id}/unpublish");
        $this->actingAs($guardian)->get($url)->assertOk()->assertDontSee('data-fee-card', false);
        $this->assertSame(['Published fee schedule', 'Unpublished fee schedule'],
            AuditLog::where('entity_type', 'FeeSchedule')->orderBy('id')->pluck('action')->all());
    }

    public function test_leadership_reads_the_schedule_and_others_are_refused(): void
    {
        foreach (['principal' => 'Principal', 'vp_academic' => 'VP_Academic', 'registrar' => 'Registrar'] as $role => $type) {
            $staff = $this->makeStaff($role, $type, "{$role}@test.local");
            $this->actingAs($staff->user)->get("/{$role}/fee-schedule")->assertOk()
                ->assertSee('Grade 9')->assertSee('2,600,000')->assertSee('Q17')->assertDontSee('Save fees');
            $this->actingAs($staff->user)->put("/treasurer/fee-schedule/{$this->schedule->id}", ['items' => $this->grid()])->assertForbidden();
        }

        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $this->actingAs($teacher->user)->get('/treasurer/fee-schedule')->assertForbidden();
        [$guardian] = $this->guardianOf('2');
        $this->actingAs($guardian)->get('/treasurer/fee-schedule')->assertForbidden();
        $this->actingAs($this->treasurer->user)->get('/treasurer/fee-schedule')->assertOk()
            ->assertSee('Save fees')->assertSee('Publish to families')->assertSee('Kindergarten 1 to Grade 6')->assertDontSee('Reason for the change');
    }
}
