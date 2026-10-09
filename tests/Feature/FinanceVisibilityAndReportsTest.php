<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use App\Services\FeeSummaryService;
use App\Support\FeeVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §2.2 scope guards / §9.8 visibility rules, and the finance reports built on them.
 */
class FinanceVisibilityAndReportsTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private StaffProfile $treasurer;

    private Student $student;

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
    }

    private function batch(bool $published = true): ImportBatch
    {
        return ImportBatch::create([
            'uploaded_by' => $this->treasurer->id, 'period' => 'Q1 2026', 'source_file' => 'q1.xlsx',
            'row_count' => 0, 'uploaded_at' => now(), 'published_at' => $published ? now() : null,
        ]);
    }

    private function row(ImportBatch $batch, int $amount, array $overrides = []): ImportedFeeRecord
    {
        return ImportedFeeRecord::create(array_merge([
            'import_batch_id' => $batch->id, 'student_id' => $this->student->id,
            'txn_date' => now()->toDateString(), 'amount' => $amount, 'balance' => $amount, 'status' => 'Outstanding',
        ], $overrides));
    }

    /** One visible row plus one row of every kind leadership must not see. */
    private function seedMixedRows(): void
    {
        $published = $this->batch();
        $this->row($published, 100000, ['description' => 'Visible tuition']);
        $this->row($published, 200000, ['description' => 'SDA allowance line', 'is_restricted' => true]);
        $this->row($published, 300000, ['description' => 'Held line', 'is_held' => true]);
        $this->row($this->batch(published: false), 400000, ['description' => 'Draft batch line']);
    }

    public function test_each_audience_sees_only_its_rows(): void
    {
        $this->seedMixedRows();

        $this->assertSame(4, ImportedFeeRecord::query()->visibleTo(FeeVisibility::TREASURER)->count());
        $this->assertSame(['Visible tuition'], ImportedFeeRecord::query()->visibleTo(FeeVisibility::LEADERSHIP)->pluck('description')->all());
        $this->assertSame(['Visible tuition'], ImportedFeeRecord::query()->visibleTo(FeeVisibility::FAMILY)->pluck('description')->all());
    }

    public function test_principal_vp_and_registrar_fee_pages_apply_the_leadership_rules(): void
    {
        $this->seedMixedRows();

        foreach (['principal' => 'Principal', 'vp_academic' => 'VP_Academic', 'registrar' => 'Registrar'] as $role => $type) {
            $viewer = $this->makeStaff($role, $type, "{$role}@test.local");

            $this->actingAs($viewer->user)->get(route("{$role}.fees.index"))
                ->assertOk()
                ->assertViewHas('outstandingTotal', 100000.0);

            $this->actingAs($viewer->user)->get(route("{$role}.fees.show", $this->student))
                ->assertOk()
                ->assertSee('Visible tuition')
                ->assertDontSee('SDA allowance line')
                ->assertDontSee('Held line')
                ->assertDontSee('Draft batch line');
        }
    }

    public function test_principal_dashboard_fee_figures_use_leadership_rules(): void
    {
        $this->seedMixedRows();
        $principal = $this->makeStaff('principal', 'Principal');

        $this->actingAs($principal->user)->get(route('principal.dashboard'))
            ->assertOk()
            ->assertViewHas('outstandingTotal', 100000.0);
    }

    public function test_policy_switch_shows_restricted_rows_to_leadership_but_never_to_guardians(): void
    {
        $this->seedMixedRows();

        $this->actingAs($this->treasurer->user)
            ->put(route('treasurer.info.visibility-policy'), ['leadership_sees_restricted' => '1'])
            ->assertRedirect();

        $this->assertTrue(FeeVisibility::leadershipSeesRestricted());
        $this->assertSame(2, ImportedFeeRecord::query()->visibleTo(FeeVisibility::LEADERSHIP)->count());
        $this->assertSame(1, ImportedFeeRecord::query()->visibleTo(FeeVisibility::FAMILY)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Allowed leadership to see restricted fee rows']);

        $guardianUser = User::create(['name' => 'Daw Mya', 'email' => 'mom@test.local', 'password' => Hash::make('x'), 'status' => 'Active']);
        $guardianUser->assignRole('guardian');
        Guardian::create(['user_id' => $guardianUser->id, 'relationship' => 'Mother'])->students()->attach($this->student->id, ['is_primary' => true]);

        $this->actingAs($guardianUser)->get(route('guardian.fees.index'))
            ->assertOk()
            ->assertSee('Visible tuition')
            ->assertDontSee('SDA allowance line');
    }

    public function test_only_the_treasurer_can_change_visibility(): void
    {
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $this->actingAs($registrar->user)
            ->put(route('treasurer.info.visibility-policy'), ['leadership_sees_restricted' => '1'])
            ->assertForbidden();
        $this->assertFalse(FeeVisibility::leadershipSeesRestricted());
    }

    public function test_publishing_requires_confirming_the_restricted_classification(): void
    {
        $batch = $this->batch(published: false);
        $this->row($batch, 100000, ['is_restricted' => true]);

        $this->actingAs($this->treasurer->user)->post(route('treasurer.validate.publish', $batch))
            ->assertSessionHasErrors('confirm_restricted');
        $this->assertNull($batch->fresh()->published_at);

        $this->actingAs($this->treasurer->user)->post(route('treasurer.validate.publish', $batch), ['confirm_restricted' => '1'])
            ->assertSessionHasNoErrors();
        $batch->refresh();
        $this->assertNotNull($batch->published_at);
        $this->assertSame($this->treasurer->user->id, $batch->restricted_confirmed_by);
    }

    public function test_changing_a_restriction_asks_for_reconfirmation(): void
    {
        $batch = $this->batch();
        $batch->update(['restricted_confirmed_at' => now(), 'restricted_confirmed_by' => $this->treasurer->user->id]);
        $row = $this->row($batch, 100000);

        $this->actingAs($this->treasurer->user)->post(route('treasurer.validate.toggle-restrict', $row));
        $this->assertNull($batch->fresh()->restricted_confirmed_at);

        $this->actingAs($this->treasurer->user)->post(route('treasurer.info.confirm-restricted', $batch))->assertRedirect();
        $this->assertNotNull($batch->fresh()->restricted_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Confirmed restricted classification for fee batch', 'entity_id' => $batch->id]);
    }

    public function test_visibility_page_reports_live_exposure_per_audience(): void
    {
        $this->seedMixedRows();

        $this->actingAs($this->treasurer->user)->get(route('treasurer.info.visibility-rules'))
            ->assertOk()
            ->assertViewHas('exposure', fn ($e) => $e[FeeVisibility::TREASURER] === 4 && $e[FeeVisibility::LEADERSHIP] === 1 && $e[FeeVisibility::FAMILY] === 1)
            ->assertSee('Needs confirmation');
    }

    public function test_import_keeps_the_student_name_and_flags_name_conflicts(): void
    {
        $csv = "student_id,student_name,date,description,amount,balance,status,restricted\n"
            ."YAS-2026-0001,Saw Htoo Aung,2026-07-01,Term 1 tuition,150000,0,Paid,\n"
            ."YAS-2026-0001,Naw Eh Ler,2026-07-02,Bus fee,50000,50000,Outstanding,\n";

        $this->actingAs($this->treasurer->user)->post(route('treasurer.import.store'), [
            'period' => 'Q1 2026', 'file' => UploadedFile::fake()->createWithContent('q1.csv', $csv),
        ])->assertSessionHasNoErrors();

        $rows = ImportedFeeRecord::orderBy('id')->get();
        $this->assertSame(['Saw Htoo Aung', 'Naw Eh Ler'], $rows->pluck('raw_student_name')->all());
        $this->assertSame(['Term 1 tuition', 'Bus fee'], $rows->pluck('description')->all());
        $this->assertFalse($rows[0]->has_name_conflict);
        $this->assertTrue($rows[1]->has_name_conflict);

        $this->actingAs($this->treasurer->user)->get(route('treasurer.validate.index'))
            ->assertOk()
            ->assertViewHas('nameConflicts', 1)
            ->assertSee('Name conflict');
    }

    public function test_outstanding_csv_has_full_name_and_excel_friendly_encoding(): void
    {
        $this->row($this->batch(), 150000);

        $csv = $this->actingAs($this->treasurer->user)->get(route('treasurer.reports.outstanding'))->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Student ID","Full name"', $csv);
        $this->assertStringContainsString('YAS-2026-0001,"Saw Htoo Aung"', $csv);
    }

    public function test_aged_receivables_csv_splits_balances_by_age(): void
    {
        $batch = $this->batch();
        $this->row($batch, 100000, ['txn_date' => now()->subDays(100)->toDateString(), 'balance' => 100000]);
        $this->row($batch, 50000, ['txn_date' => now()->subDays(10)->toDateString(), 'balance' => 50000]);

        $csv = $this->actingAs($this->treasurer->user)->get(route('treasurer.reports.aging'))->streamedContent();

        // Current 50,000 · 31-60 0 · 61-90 0 · over 90 100,000 · total 150,000
        $this->assertStringContainsString('"Saw Htoo Aung","High School",,50000,0,0,100000,150000', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Generated aged receivables report']);
    }

    public function test_statement_lines_treat_each_row_as_an_open_item_and_age_what_is_unpaid(): void
    {
        $batch = $this->batch();
        // 300,000 billed 120 days ago, fully paid; 200,000 billed 45 days ago, 150,000 still open.
        $this->row($batch, 300000, ['txn_date' => now()->subDays(120)->toDateString(), 'balance' => 0, 'status' => 'Paid']);
        $this->row($batch, 200000, ['txn_date' => now()->subDays(45)->toDateString(), 'balance' => 150000, 'status' => 'Partial']);

        $service = app(FeeSummaryService::class);
        $lines = $service->statementLines(ImportedFeeRecord::all());

        $this->assertEquals([300000.0, 50000.0], $lines->pluck('payment')->all());
        $this->assertEquals([0.0, 150000.0], $lines->pluck('open')->all());
        $this->assertEquals([0.0, 150000.0], $lines->pluck('balance')->all());
        $this->assertEquals(['current' => 0.0, 'days_31_60' => 150000.0, 'days_61_90' => 0.0, 'over_90' => 0.0], $service->aging($lines));

        $summary = $service->studentSummaries()->first();
        $this->assertSame(500000.0, $summary->total_billed);
        $this->assertSame(350000.0, $summary->paid);
        $this->assertSame(150000.0, $summary->balance);
        $this->assertSame('Partial', $summary->status);
    }

    public function test_family_copy_statement_is_audited_separately(): void
    {
        $this->row($this->batch(), 100000);

        $this->actingAs($this->treasurer->user)->get(route('treasurer.reports.statement', [$this->student, 'copy' => 'family']))->assertOk();

        $this->assertSame(1, AuditLog::where('action', 'Generated student fee statement (family copy)')->count());
    }
}
