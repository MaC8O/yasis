<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\StaffProfile;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §9.4 Validate & Match — the manual-resolution flow: confirm a match, see the result
 * on the row, recover from mistakes.
 */
class FeeManualMatchTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private StaffProfile $treasurer;

    private Student $student;

    private ImportBatch $batch;

    private string $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $department = $this->seedDepartment();
        $this->treasurer = $this->makeStaff('treasurer', 'Treasurer');
        $this->student = Student::create([
            'student_id_number' => 'YAS-2026-0001', 'name' => 'Saw Htoo Aung',
            'department_id' => $department->id, 'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear(),
        ]);
        $this->batch = ImportBatch::create([
            'uploaded_by' => $this->treasurer->id, 'period' => 'Q3 2026', 'source_file' => 'q3.csv',
            'row_count' => 2, 'uploaded_at' => now(),
        ]);
        $this->page = route('treasurer.validate.index', ['batch' => $this->batch->id]);
    }

    private function unmatched(string $key = 'YAS-2026-9001', string $name = 'Saw Htoo Aung'): ImportedFeeRecord
    {
        return ImportedFeeRecord::create([
            'import_batch_id' => $this->batch->id, 'student_id' => null,
            'raw_student_key' => $key, 'raw_student_name' => $name,
            'txn_date' => now()->toDateString(), 'amount' => 150000, 'balance' => 150000, 'status' => 'Outstanding',
        ]);
    }

    private function confirm(ImportedFeeRecord $row, string $student)
    {
        return $this->actingAs($this->treasurer->user)->from($this->page)
            ->post(route('treasurer.validate.resolve', $row), ['student' => $student]);
    }

    public function test_confirming_a_match_by_student_id_maps_the_row_and_reports_success(): void
    {
        $row = $this->unmatched();
        $this->unmatched('YAS-2026-9002', 'Su Su Aung');

        $this->confirm($row, 'YAS-2026-0001')
            ->assertRedirect($this->page.'#needs-match')
            ->assertSessionHas('status', 'Matched YAS-2026-9001 to Saw Htoo Aung (YAS-2026-0001). 1 row still needs a match.')
            ->assertSessionHas('matched_row', $row->id);

        $row->refresh();
        $this->assertSame($this->student->id, $row->student_id);
        $this->assertSame('YAS-2026-9001', $row->raw_student_key, 'the source ID is kept for the record');
        $this->assertDatabaseHas('audit_logs', ['action' => 'Resolved unmatched fee row', 'entity_id' => $row->id]);
    }

    public function test_the_success_notice_is_shown_on_the_page_after_the_redirect(): void
    {
        $row = $this->unmatched();

        $this->confirm($row, 'YAS-2026-0001');

        $this->actingAs($this->treasurer->user)->get($this->page)
            ->assertOk()
            ->assertSee('Matched YAS-2026-9001 to Saw Htoo Aung (YAS-2026-0001).')
            ->assertSee('Every row in this batch is now matched or held.')
            ->assertSee('Manually matched');
    }

    public function test_a_unique_full_name_also_works(): void
    {
        $row = $this->unmatched();

        $this->confirm($row, '  Saw Htoo Aung ')->assertSessionHas('status');

        $this->assertSame($this->student->id, $row->fresh()->student_id);
    }

    public function test_an_unknown_student_is_rejected_on_the_row_with_the_typed_value_kept(): void
    {
        $row = $this->unmatched();

        $this->confirm($row, 'YAS-0000-XXXX')
            ->assertRedirect($this->page."#match-{$row->id}")
            ->assertSessionHasErrors(['student'], null, "resolve_{$row->id}")
            ->assertSessionHas('resolve_row', $row->id);

        $this->assertNull($row->fresh()->student_id);

        $this->actingAs($this->treasurer->user)->get($this->page)
            ->assertOk()
            ->assertSee('No ISMS student has the ID or name “YAS-0000-XXXX”', false)
            ->assertSee('YAS-0000-XXXX');
    }

    public function test_an_empty_choice_is_rejected_with_guidance(): void
    {
        $row = $this->unmatched();

        $this->confirm($row, '')->assertSessionHasErrors(['student'], null, "resolve_{$row->id}");

        $this->assertNull($row->fresh()->student_id);
    }

    public function test_an_ambiguous_name_asks_for_the_id(): void
    {
        Student::create([
            'student_id_number' => 'YAS-2026-0099', 'name' => 'Saw Htoo Aung',
            'department_id' => $this->student->department_id, 'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear(),
        ]);
        $row = $this->unmatched();

        $this->confirm($row, 'Saw Htoo Aung');

        $this->assertSame('2 students are named “Saw Htoo Aung”. Use the student ID instead.',
            session('errors')->getBag("resolve_{$row->id}")->first('student'));
        $this->assertNull($row->fresh()->student_id);
    }

    public function test_matching_a_different_name_warns_the_treasurer(): void
    {
        $row = $this->unmatched('SUN-0003', 'Naw Paw Eh');

        $this->confirm($row, 'YAS-2026-0001')
            ->assertSessionHas('status')
            ->assertSessionHas('warning', 'Check this match: the export names “Naw Paw Eh”, but YAS-2026-0001 is Saw Htoo Aung.');
    }

    public function test_matching_completes_the_publish_gate(): void
    {
        $row = $this->unmatched();

        $this->actingAs($this->treasurer->user)
            ->post(route('treasurer.validate.publish', $this->batch), ['confirm_restricted' => '1'])
            ->assertSessionHasErrors('publish');

        $this->confirm($row, 'YAS-2026-0001');

        $this->actingAs($this->treasurer->user)
            ->post(route('treasurer.validate.publish', $this->batch), ['confirm_restricted' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($this->batch->fresh()->published_at);
    }

    public function test_a_manual_match_can_be_undone(): void
    {
        $row = $this->unmatched();
        $this->confirm($row, 'YAS-2026-0001');

        $this->actingAs($this->treasurer->user)->from($this->page)
            ->post(route('treasurer.validate.unmatch', $row))
            ->assertRedirect($this->page.'#needs-match')
            ->assertSessionHas('status', 'Match undone — YAS-2026-9001 needs a match again.');

        $this->assertNull($row->fresh()->student_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Undid manual fee row match', 'entity_id' => $row->id]);
    }

    public function test_rows_matched_on_import_cannot_be_unmatched_here(): void
    {
        $row = ImportedFeeRecord::create([
            'import_batch_id' => $this->batch->id, 'student_id' => $this->student->id, 'raw_student_key' => null,
            'txn_date' => now()->toDateString(), 'amount' => 1, 'balance' => 1, 'status' => 'Outstanding',
        ]);

        $this->actingAs($this->treasurer->user)->from($this->page)
            ->post(route('treasurer.validate.unmatch', $row))
            ->assertSessionHas('warning');

        $this->assertSame($this->student->id, $row->fresh()->student_id);
    }

    public function test_only_the_treasurer_can_match(): void
    {
        $row = $this->unmatched();
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $this->actingAs($registrar->user)
            ->post(route('treasurer.validate.resolve', $row), ['student' => 'YAS-2026-0001'])
            ->assertForbidden();

        $this->assertNull($row->fresh()->student_id);
    }
}
