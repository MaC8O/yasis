<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §9.4 Treasurer Validate & Match: Restrict hides a row from families everywhere;
 * Hold parks a row out of the publish gate. Publish is blocked while unheld
 * unmatched rows remain.
 */
class FeeRowRestrictHoldTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeStudent(int $departmentId): Student
    {
        return Student::create([
            'student_id_number' => 'S-'.fake()->unique()->numberBetween(1000, 99999),
            'name' => 'Aye Min',
            'date_of_birth' => now()->subYears(14), 'gender' => 'F',
            'department_id' => $departmentId,
            'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear(),
        ]);
    }

    private function makeBatch(StaffProfile $treasurer, array $overrides = []): ImportBatch
    {
        return ImportBatch::create(array_merge([
            'uploaded_by' => $treasurer->id,
            'period' => 'Q1 2026',
            'source_file' => 'q1.xlsx',
            'row_count' => 0,
            'uploaded_at' => now(),
        ], $overrides));
    }

    private function makeRow(ImportBatch $batch, ?Student $student, array $overrides = []): ImportedFeeRecord
    {
        return ImportedFeeRecord::create(array_merge([
            'import_batch_id' => $batch->id,
            'student_id' => $student?->id,
            'raw_student_key' => $student ? null : 'UNKNOWN-1',
            'txn_date' => now()->toDateString(),
            'amount' => 150000,
            'balance' => 0,
            'status' => 'Paid',
        ], $overrides));
    }

    public function test_treasurer_can_toggle_restrict_on_a_row(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $treasurer = $this->makeStaff('treasurer', 'Treasurer');
        $row = $this->makeRow($this->makeBatch($treasurer), $this->makeStudent($department->id));

        $this->actingAs($treasurer->user)
            ->post("/treasurer/validate/{$row->id}/toggle-restrict")
            ->assertSessionHasNoErrors();

        $this->assertTrue($row->fresh()->is_restricted);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Restricted fee row', 'entity_id' => $row->id]);

        $this->actingAs($treasurer->user)->post("/treasurer/validate/{$row->id}/toggle-restrict");
        $this->assertFalse($row->fresh()->is_restricted);
    }

    public function test_treasurer_can_hold_and_release_a_row(): void
    {
        $this->seedRoles();
        $treasurer = $this->makeStaff('treasurer', 'Treasurer');
        $row = $this->makeRow($this->makeBatch($treasurer), null);

        $this->actingAs($treasurer->user)
            ->post("/treasurer/validate/{$row->id}/toggle-hold")
            ->assertSessionHasNoErrors();

        $this->assertTrue($row->fresh()->is_held);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Held fee row', 'entity_id' => $row->id]);

        $this->actingAs($treasurer->user)->post("/treasurer/validate/{$row->id}/toggle-hold");
        $this->assertFalse($row->fresh()->is_held);
    }

    public function test_publish_is_blocked_while_unheld_unmatched_rows_remain(): void
    {
        $this->seedRoles();
        $treasurer = $this->makeStaff('treasurer', 'Treasurer');
        $batch = $this->makeBatch($treasurer);
        $row = $this->makeRow($batch, null);

        $this->actingAs($treasurer->user)
            ->post("/treasurer/validate/batches/{$batch->id}/publish")
            ->assertSessionHasErrors('publish');

        $this->assertNull($batch->fresh()->published_at);

        // Holding the unmatched row parks it — publish now goes through.
        $this->actingAs($treasurer->user)->post("/treasurer/validate/{$row->id}/toggle-hold");
        $this->actingAs($treasurer->user)
            ->post("/treasurer/validate/batches/{$batch->id}/publish")
            ->assertSessionHasNoErrors();

        $this->assertNotNull($batch->fresh()->published_at);
    }

    public function test_restricted_and_held_rows_never_reach_the_guardian(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $treasurer = $this->makeStaff('treasurer', 'Treasurer');
        $student = $this->makeStudent($department->id);

        $batch = $this->makeBatch($treasurer, ['published_at' => now()]);
        $this->makeRow($batch, $student, ['amount' => 111000, 'balance' => 0]);
        $this->makeRow($batch, $student, ['amount' => 222000, 'balance' => 0, 'is_restricted' => true]);
        $this->makeRow($batch, $student, ['amount' => 333000, 'balance' => 0, 'is_held' => true]);

        $guardianUser = User::create([
            'name' => 'Daw Mya', 'email' => 'mom@test.local',
            'password' => Hash::make('password'), 'status' => 'Active',
        ]);
        $guardianUser->assignRole('guardian');
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'relationship' => 'Mother', 'phone' => '0911']);
        $guardian->students()->attach($student->id, ['is_primary' => true]);

        $this->actingAs($guardianUser)->get('/guardian/fees')
            ->assertOk()
            ->assertSee('111,000')
            ->assertDontSee('222,000')
            ->assertDontSee('333,000');
    }

    public function test_other_roles_cannot_toggle_restrict_or_hold(): void
    {
        $this->seedRoles();
        $treasurer = $this->makeStaff('treasurer', 'Treasurer');
        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');
        $row = $this->makeRow($this->makeBatch($treasurer), null);

        $this->actingAs($registrar->user)->post("/treasurer/validate/{$row->id}/toggle-restrict")->assertForbidden();
        $this->actingAs($registrar->user)->post("/treasurer/validate/{$row->id}/toggle-hold")->assertForbidden();
    }
}
