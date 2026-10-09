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
 * Student Accounts (look up one student) and Fee Reports (school-wide answers).
 */
class TreasurerAccountsAndReportsPagesTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private StaffProfile $treasurer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $department = $this->seedDepartment();
        $this->treasurer = $this->makeStaff('treasurer', 'Treasurer');

        $batch = ImportBatch::create([
            'uploaded_by' => $this->treasurer->id, 'period' => 'Q1 2026', 'source_file' => 'q1.csv',
            'row_count' => 3, 'uploaded_at' => now(), 'published_at' => now(),
        ]);

        // Paid in full · owing but recent · owing for 120 days.
        foreach ([
            ['YAS-2026-0001', 'Aye Aye Paid', 0, 5],
            ['YAS-2026-0002', 'Bo Bo Recent', 100000, 10],
            ['YAS-2026-0003', 'Chit Chit Late', 300000, 120],
        ] as [$id, $name, $owed, $daysAgo]) {
            $student = Student::create([
                'student_id_number' => $id, 'name' => $name, 'department_id' => $department->id,
                'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear(),
            ]);
            ImportedFeeRecord::create([
                'import_batch_id' => $batch->id, 'student_id' => $student->id, 'txn_date' => now()->subDays($daysAgo)->toDateString(),
                'description' => 'Tuition', 'amount' => 300000, 'balance' => $owed, 'status' => $owed ? 'Partial' : 'Paid',
            ]);
        }
    }

    private function names($response): array
    {
        return $response->viewData('summaries')->map(fn ($s) => $s->student->name)->all();
    }

    public function test_student_accounts_tabs_filter_with_one_click(): void
    {
        $page = fn (array $q = []) => $this->actingAs($this->treasurer->user)->get(route('treasurer.records.index', $q))->assertOk();

        $all = $page()->assertSee('Student Accounts')->assertSee('Still owed');
        $this->assertSame(['Aye Aye Paid', 'Bo Bo Recent', 'Chit Chit Late'], $this->names($all));
        $this->assertEquals(['all' => 3, 'owing' => 2, 'overdue' => 1, 'paid' => 1], $all->viewData('counts')->all());

        $this->assertSame(['Bo Bo Recent', 'Chit Chit Late'], $this->names($page(['view' => 'owing'])));
        $this->assertSame(['Chit Chit Late'], $this->names($page(['view' => 'overdue'])));
        $this->assertSame(['Aye Aye Paid'], $this->names($page(['view' => 'paid'])));
    }

    public function test_status_says_in_plain_words_how_long_money_is_owed(): void
    {
        $this->actingAs($this->treasurer->user)->get(route('treasurer.records.index'))
            ->assertSee('Fully paid')
            ->assertSee('Owing · not yet overdue')
            ->assertSee('Unpaid 120 days');
    }

    public function test_search_and_sort(): void
    {
        $response = $this->actingAs($this->treasurer->user)->get(route('treasurer.records.index', ['search' => 'chit']));
        $this->assertSame(['Chit Chit Late'], $this->names($response));

        $response = $this->actingAs($this->treasurer->user)->get(route('treasurer.records.index', ['sort' => 'owed']));
        $this->assertSame(['Chit Chit Late', 'Bo Bo Recent', 'Aye Aye Paid'], $this->names($response));
    }

    public function test_fee_reports_answers_each_question(): void
    {
        $this->actingAs($this->treasurer->user)->get(route('treasurer.reports.index'))
            ->assertOk()
            ->assertSeeInOrder([
                '1. Who still owes money?', 'Chit Chit Late', 'Bo Bo Recent',
                '2. How long has it been owed?',
                '3. How much have we collected?', 'Q1 2026',
                '4. Which departments owe the most?',
                "5. Print a student's statement",
            ], false)
            ->assertViewHas('owedTotal', 400000.0)
            ->assertViewHas('owingCount', 2);
    }

    public function test_statement_picker_opens_the_students_statement(): void
    {
        $student = Student::where('student_id_number', 'YAS-2026-0003')->first();

        $this->actingAs($this->treasurer->user)
            ->get(route('treasurer.reports.find-statement', ['student' => 'YAS-2026-0003']))
            ->assertRedirect(route('treasurer.records.show', $student));

        $this->actingAs($this->treasurer->user)
            ->get(route('treasurer.reports.find-statement', ['student' => 'Nobody']))
            ->assertRedirect(route('treasurer.reports.index').'#statement')
            ->assertSessionHas('warning', 'No student found for “Nobody”.');
    }

    public function test_statement_page_uses_plain_labels(): void
    {
        $student = Student::where('student_id_number', 'YAS-2026-0003')->first();

        $this->actingAs($this->treasurer->user)->get(route('treasurer.records.show', $student))
            ->assertOk()
            ->assertSee('Charges and payments')
            ->assertSee('Print copy for the family')
            ->assertSee('How long the money has been owed');
    }

    public function test_history_counts_the_rows_that_exist_not_the_stored_file_count(): void
    {
        // The stored row_count says 3, but a 4th row was added to the batch later (e.g. by re-seeding).
        $batch = ImportBatch::firstOrFail();
        ImportedFeeRecord::create([
            'import_batch_id' => $batch->id, 'student_id' => null, 'raw_student_key' => 'ACC-1', 'is_held' => true,
            'txn_date' => now()->toDateString(), 'amount' => 50000, 'balance' => 50000, 'status' => 'Outstanding',
        ]);

        $this->actingAs($this->treasurer->user)->get(route('treasurer.history.index'))
            ->assertOk()
            ->assertViewHas('batches', fn ($b) => $b->first()->imported_fee_records_count === 4
                && $b->first()->matched_count === 3 && $b->first()->held_count === 1)
            ->assertSeeInOrder(['Q1 2026', '4', '3 matched', '1 held'], false)
            ->assertSee("removes its 4 rows", false);
    }

    public function test_revert_reports_how_many_rows_it_removed(): void
    {
        $batch = ImportBatch::firstOrFail();

        $this->actingAs($this->treasurer->user)->delete(route('treasurer.history.revert', $batch))
            ->assertSessionHas('status', 'Batch Q1 2026 reverted — its 3 rows were removed.');

        $this->assertSame(0, ImportedFeeRecord::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Reverted fee import batch', 'entity_id' => $batch->id]);
    }

    public function test_csv_downloads_use_the_same_words_as_the_screens(): void
    {
        $outstanding = $this->actingAs($this->treasurer->user)->get(route('treasurer.reports.outstanding'))->streamedContent();
        $aging = $this->actingAs($this->treasurer->user)->get(route('treasurer.reports.aging'))->streamedContent();

        foreach (['"Charged (MMK)"', '"Still owed (MMK)"', '"Days unpaid (if over 30)"'] as $header) {
            $this->assertStringContainsString($header, $outstanding);
        }
        $this->assertStringContainsString('"Under 30 days (MMK)"', $aging);
        $this->assertStringContainsString('"Still owed (MMK)"', $aging);

        foreach ([$outstanding, $aging] as $csv) {
            foreach (['billed', 'Balance (MMK)', 'Current 0-30', 'Total balance'] as $old) {
                $this->assertStringNotContainsString($old, $csv);
            }
        }

        // Chit Chit Late: 300,000 owed for 120 days.
        $this->assertStringContainsString('"Chit Chit Late"', $outstanding);
        $this->assertMatchesRegularExpression('/"Chit Chit Late".*,300000,Outstanding,120,/', $outstanding);
    }
}
