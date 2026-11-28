<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FeeSchedule;
use App\Models\StaffProfile;
use App\Models\SunAccount;
use App\Models\SunAccountBalance;
use App\Models\SunAccountLink;
use App\Models\SunAccountReview;
use App\Models\SunFamilyStatement;
use App\Models\SunImportBatch;
use App\Models\SunLedgerLine;
use App\Models\User;
use App\Services\RetentionService;
use App\Services\Sun\AccountListingParser;
use App\Services\Sun\FeeCategoriser;
use App\Services\Sun\SunLedgerImporter;
use App\Services\Sun\SunLinks;
use App\Support\FeeSheet2026;
use App\Support\Sun\ListingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 8b-1: the Sun Accounts ledger import (sdp_doc/YASIS_Sun_Finance_Import.md, gitignored).
 * Synthetic fixtures with invented names only (ListingWriter), never the real files.
 */
class SunLedgerImportTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $treasurer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->treasurer = $this->makeStaff('treasurer', 'Treasurer', 'treasurer@test.local');
        Storage::fake(SunLedgerImporter::DISK);
    }

    /** The standard fixture: every line type, pages breaking mid-account. */
    private function fixture(int $fy = 2021, int $pageLines = 30): ListingWriter
    {
        return (new ListingWriter($fy, $pageLines))
            ->account('1000286000', 'Saw Example Student', '25000.00', [
                [9, '52500.00', 'Charging tuition fee for Dec 2020'],
                [9, '-10500.00', 'Providing 20% edu allow on tuition fee for Dec 2020'],
                [10, '-42000.00', 'Payment monthly fee for Dec 2020', 'CR'],
                [10, '52500.00', '.harging tuition fee for Jan 2021'],
                [11, '-25000.00', 'Received on a/c', 'CR'],
                [11, '15000.00', 'Charging school ferry fee for Feb 2021'],
                [12, '-15000.00', 'Sundry receipt', 'CR'],
            ])
            ->account('1000286100', 'Naw Example Girl', '0.00', [
                [1, '300000.00', 'Charging for 2020-2021 yearly fees'],
                [2, '-300000.00', 'Payment yearly fee', 'CR', '02/12/2021', false], // typed a year late, not allocated
                [3, '20000.00', 'Charging refundable deposit'],
                [3, '-4000.00', 'educatinal allowance on deposit'],
                [4, '5000.00', 'Students cash for uniform & textbooks', 'BV'],
                [5, '2500.00', 'Something the rules do not know'],
            ])
            ->account('1000286200', 'Ko Same Name', '-5000.00', [[6, '5000.00', 'Reversing yearly fee for dropped out students']])
            ->account('1000286300', 'Ko Same Name', '0.00', [[6, '1000.00', 'Donation for Christmas']], openingLine: false);
    }

    private function upload(string $text)
    {
        return $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/upload', [
            'file' => UploadedFile::fake()->createWithContent('Student GL 2020-2021.PRN', $text),
        ]);
    }

    private function token($response): string
    {
        preg_match('/name="token" value="([0-9a-f-]{36})"/', $response->getContent(), $m);
        $this->assertNotEmpty($m, 'no upload token on the summary page');

        return $m[1];
    }

    private function import(string $text): SunImportBatch
    {
        $token = $this->token($this->upload($text)->assertOk());
        $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/import', ['token' => $token, 'file_name' => 'Student GL 2020-2021.PRN'])
            ->assertSessionHas('status');

        return SunImportBatch::latest('id')->first();
    }

    private function line(string $code, int $jline): SunLedgerLine
    {
        return SunLedgerLine::where('sun_account_code', $code)->where('journal_line', $jline)->sole();
    }

    // --- Parsing -------------------------------------------------------------------------------------------

    public function test_the_parser_reads_every_line_type_across_page_breaks(): void
    {
        $text = $this->fixture()->render();
        $this->assertGreaterThan(2, substr_count($text, 'ACCOUNT LISTING'), 'the fixture spans several pages');
        $r = app(AccountListingParser::class)->parse($text);

        $this->assertTrue($r['ok'], json_encode($r['errors']));
        $this->assertSame(['5', '16', '2021', '0.00', '0.00', 1], [(string) count($r['accounts']), (string) $r['line_count'], (string) $r['header']['fiscal_year'], $r['closing_sum'], $r['report_balance'], $r['unallocated']]);
        $saw = $r['accounts']['1000286000'];
        $this->assertSame(['25000.00', '52500.00'], [$saw['opening'], $saw['closing']]);
        $this->assertSame('-10500.00', $saw['lines'][1]['amount']);
        $this->assertSame('Providing 20% edu allow on tuition fee for Dec 2020', $saw['lines'][1]['memo']);
        $this->assertSame(['-5000.00', '0.00'], [$r['accounts']['1000286200']['opening'], $r['accounts']['1000286200']['closing']]);
        $this->assertSame('0.00', $r['accounts']['1000286300']['opening']); // no opening line
        $this->assertSame(['1000285005', '1000286300', '01/2021', '12/2021'], [$r['header']['account_from'], $r['header']['account_to'], $r['header']['period_from'], $r['header']['period_to']]);
    }

    /** @return array<string, array{0: array}> */
    public static function brokenFiles(): array
    {
        return [
            'not an account listing' => [['not_listing' => true]],
            'an unknown line' => [['garbage' => 20]],
            'a running balance break' => [['balance' => '1000286000#2']],
            'a closing balance that is not the last balance' => [['closing' => '1000286100']],
            'an account with no closing line' => [['no_closing' => '1000286200']],
            'no END OF REPORT' => [['no_end' => true]],
            'a report balance that is not the sum' => [['report_balance' => '10.00']],
        ];
    }

    /** @dataProvider brokenFiles */
    public function test_a_broken_file_is_refused_and_nothing_is_saved(array $break): void
    {
        $response = $this->upload($this->fixture()->render($break))->assertOk()
            ->assertSee("This file can't be imported", false)->assertDontSee('name="token"', false);

        $this->assertMatchesRegularExpression('/check(s)? failed/', $response->getContent());
        $this->assertSame(0, SunImportBatch::count());
        $this->assertSame(0, SunLedgerLine::count());
        $this->assertSame([], Storage::disk(SunLedgerImporter::DISK)->allFiles(), 'the refused upload is deleted');
    }

    // --- Upload, summary, import, cancel ---------------------------------------------------------------------

    public function test_the_summary_comes_first_and_import_saves_everything_once(): void
    {
        $this->upload($this->fixture()->render())->assertOk()
            ->assertSee('Financial year 2020-2021')->assertSee('Every check passed')
            ->assertSee('1 line has a typed date more than a month away', false)
            ->assertSee('1 line is not allocated', false);
        $this->assertSame(0, SunImportBatch::count());
        $this->assertCount(1, Storage::disk(SunLedgerImporter::DISK)->files('pending'));

        $batch = $this->import($this->fixture()->render());
        $this->assertSame([2021, 5, 16, 16, 0, 0, 4], [$batch->fiscal_year, $batch->accounts_count, $batch->lines_count, $batch->new_lines, $batch->updated_lines, $batch->matched_accounts, $batch->unmatched_accounts]);
        $this->assertSame('-77000.00', (string) $batch->control_balance); // the students' accounts sum to 77,000; Student Control balances it
        $this->assertSame(16, SunLedgerLine::count());
        $this->assertSame(5, SunAccountBalance::count());
        $this->assertTrue(SunAccount::where('code', '1000285005')->value('is_control'));
        // Only the first upload (shown, never imported) is still pending, for the hourly clean-up.
        $this->assertCount(1, Storage::disk(SunLedgerImporter::DISK)->files('pending'));
        Storage::disk(SunLedgerImporter::DISK)->assertExists("2021/{$batch->file_sha256}.prn");
        $this->assertSame('treasurer', AuditLog::where('action', 'Imported Sun ledger')->sole()->role);

        // Dates: the period decides the month; a typed date far from it is kept and marked.
        $late = $this->line('1000286100', 2);
        $this->assertSame(['2020-05-01', '2021-12-02', true, false], [$late->period_month->toDateString(), $late->txn_date->toDateString(), $late->date_suspect, $late->allocated]);
        $this->assertSame('2020-12-01', $this->line('1000286000', 1)->period_month->toDateString());
        $this->assertSame('2021-01-01', $this->line('1000286000', 4)->period_month->toDateString());
    }

    public function test_cancel_and_expiry_delete_the_upload(): void
    {
        $token = $this->token($this->upload($this->fixture()->render()));
        $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/cancel', ['token' => $token])->assertSessionHas('status');
        $this->assertSame([], Storage::disk(SunLedgerImporter::DISK)->allFiles());
        $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/import', ['token' => $token, 'file_name' => 'x.prn'])
            ->assertSessionHas('warning', 'That upload has expired or was cancelled. Upload the file again.');

        $token = $this->token($this->upload($this->fixture()->render()));
        $this->travel(61)->minutes();
        $this->artisan('sun:purge-pending')->assertSuccessful();
        $this->assertSame([], Storage::disk(SunLedgerImporter::DISK)->allFiles());
        $this->assertSame(0, SunImportBatch::count());
    }

    // --- Categories ------------------------------------------------------------------------------------------

    public function test_categories_come_from_the_rules_in_order_including_misspellings(): void
    {
        $this->import($this->fixture()->render());
        $category = fn ($code, $n) => $this->line($code, $n)->category;

        $this->assertSame('tuition', $category('1000286000', 1));
        $this->assertSame('allowance', $category('1000286000', 2));          // "edu allow"
        $this->assertSame('payment', $category('1000286000', 3));
        $this->assertSame('tuition', $category('1000286000', 4));            // ".harging tuition"
        $this->assertSame('payment', $category('1000286000', 5));            // "on a/c"
        $this->assertSame('transport', $category('1000286000', 6));
        $this->assertSame('payment', $category('1000286000', 7));            // any CR credit
        $this->assertSame('yearly', $category('1000286100', 1));
        $this->assertSame('deposit', $category('1000286100', 3));
        $this->assertSame('allowance', $category('1000286100', 4));          // "educatinal allowance", before "deposit"
        $this->assertSame('cash_advance', $category('1000286100', 5));
        $this->assertSame('unclassified', $category('1000286100', 6));
        $this->assertSame('reversal', $category('1000286200', 1));
        $this->assertSame('donation', $category('1000286300', 1));

        // T4 (the Treasurer's answer): families see every line, allowances included; nothing is hidden by default.
        $this->assertSame(0, SunLedgerLine::where('is_restricted', true)->count());
        $this->assertSame(['2020-12-01', null], [$this->line('1000286000', 2)->billed_month->toDateString(), $this->line('1000286100', 6)->billed_month]);
        $this->assertSame('2020-01-01', FeeCategoriser::billedMonth('Charging instructional fee for January, 2020'));
    }

    public function test_the_treasurers_overrides_survive_a_reimport_and_rule_changes(): void
    {
        $this->import($this->fixture()->render());
        $unknown = $this->line('1000286100', 6);
        $donation = $this->line('1000286300', 1);
        $this->actingAs($this->treasurer->user)->put("/treasurer/ledger/lines/{$unknown->id}", ['category' => 'remedial'])->assertSessionHas('status');
        $this->actingAs($this->treasurer->user)->put("/treasurer/ledger/lines/{$donation->id}", ['restricted' => 1])->assertSessionHas('status');
        $this->assertSame(2, AuditLog::whereIn('action', ['Changed ledger line category', 'Hid ledger line from families'])->count());

        $this->import($this->fixture()->render());
        $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/categories/reapply')->assertSessionHas('status');
        $this->assertSame(['remedial', true], [$unknown->fresh()->category, $unknown->fresh()->category_overridden]);
        $this->assertTrue($donation->fresh()->is_restricted);
        $this->assertSame(16, SunLedgerLine::count());
    }

    // --- Re-import and revert --------------------------------------------------------------------------------

    public function test_a_reimport_updates_inserts_marks_missing_and_warns_without_duplicates(): void
    {
        $first = $this->import($this->fixture()->render());
        $again = $this->import($this->fixture()->render());
        $this->assertSame([0, 0, 0], [$again->new_lines, $again->updated_lines, $again->missing_lines]);
        $this->assertTrue($again->warnings['duplicate_file']);
        $this->assertSame(16, SunLedgerLine::count());

        // Sun changed: one amount corrected, one line gone, one new line.
        $changed = (new ListingWriter(2021, 30))
            ->account('1000286000', 'Saw Example Student', '25000.00', [
                [9, '52500.00', 'Charging tuition fee for Dec 2020'],
                [9, '-10500.00', 'Providing 20% edu allow on tuition fee for Dec 2020'],
                [10, '-40000.00', 'Payment monthly fee for Dec 2020', 'CR'],
                [10, '52500.00', '.harging tuition fee for Jan 2021'],
                [11, '-25000.00', 'Received on a/c', 'CR'],
                [11, '15000.00', 'Charging school ferry fee for Feb 2021'],
                [12, '-15000.00', 'Sundry receipt', 'CR'],
                [12, '52500.00', 'Charging tuition fee for Mar 2021'],
            ])
            ->account('1000286100', 'Naw Example Girl', '0.00', [
                [1, '300000.00', 'Charging for 2020-2021 yearly fees'],
                [2, '-300000.00', 'Payment yearly fee', 'CR', '02/12/2021', false],
                [3, '20000.00', 'Charging refundable deposit'],
                [3, '-4000.00', 'educatinal allowance on deposit'],
                [4, '5000.00', 'Students cash for uniform & textbooks', 'BV'],
            ])
            ->account('1000286200', 'Ko Same Name', '-5000.00', [[6, '5000.00', 'Reversing yearly fee for dropped out students']])
            ->account('1000286300', 'Ko Same Name', '0.00', [[6, '1000.00', 'Donation for Christmas']], openingLine: false);

        $summary = $this->upload($changed->render())->assertOk();
        // The corrected payment, and the control account's balancing line that moves with it.
        $summary->assertSee('2 lines already in the ISMS have a different amount', false)->assertSee('1 line in the ISMS for this year is not in this file', false);
        $third = $this->import($changed->render());

        $this->assertSame([1, 1], [$third->new_lines, $third->missing_lines]);
        $this->assertSame('-40000.00', (string) $this->line('1000286000', 3)->amount);
        $this->assertTrue($this->line('1000286100', 6)->missing_from_latest);
        $this->assertSame(17, SunLedgerLine::count()); // nothing deleted
        $this->assertContains($this->line('1000286000', 3)->id, $third->warnings['changed_line_ids']);
        $this->assertSame(16, $first->fresh()->lines_count);
    }

    public function test_revert_restores_exactly_and_only_the_latest_batch_to_touch_a_line_may_be_reverted(): void
    {
        $first = $this->import($this->fixture()->render());

        // A second import that changes one line (built properly, so the file passes its checks).
        $second = $this->import((new ListingWriter(2021, 30))
            ->account('1000286000', 'Saw Example Student', '25000.00', [
                [9, '52500.00', 'Charging tuition fee for Dec 2020'],
                [9, '-10500.00', 'Providing 20% edu allow on tuition fee for Dec 2020'],
                [10, '-41000.00', 'Payment monthly fee for Dec 2020', 'CR'],
            ])->render());
        $this->assertSame('-41000.00', (string) $this->line('1000286000', 3)->amount);

        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$first->id}/revert")
            ->assertSessionHas('warning', fn ($m) => str_contains($m, "A newer import changed the same lines: #{$second->id}") && str_contains($m, 'Revert that one first.'));
        $this->assertNull($first->fresh()->reverted_at);

        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$second->id}/revert")->assertSessionHas('status');
        $this->assertSame(['-42000.00', false], [(string) $this->line('1000286000', 3)->amount, $this->line('1000286100', 1)->missing_from_latest]);

        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$first->id}/revert")->assertSessionHas('status');
        $this->assertSame(0, SunLedgerLine::current()->count());
        $this->assertSame(16, SunLedgerLine::count()); // marked removed, never deleted
        $this->assertSame(2, AuditLog::where('action', 'Reverted Sun ledger import')->count());

        // Importing the same file after a revert brings the lines back.
        $this->import($this->fixture()->render());
        $this->assertSame(16, SunLedgerLine::current()->count());
    }

    // --- Year-to-year warnings ---------------------------------------------------------------------------

    public function test_opening_balance_warnings_compare_with_the_previous_year(): void
    {
        $this->import((new ListingWriter(2020, 30))
            ->account('1000286000', 'Saw Example Studnt', '0.00', [[1, '25000.00', 'Charging tuition fee for Apr 2019']])
            ->account('1000286100', 'Naw Example Girl', '0.00', [[1, '7000.00', 'Charging tuition fee for Apr 2019']])->render());

        // An unlinked code printed under a new name needs no review: it isn't matched to anyone (T3 reviews are in SunAccountLinksTest).
        $this->upload($this->fixture()->render())->assertOk()
            // 1000286100 closed 7,000 but opens at 0, and Student Control closed at 32,000 Cr but opens at 0 (T8: kept as a warning).
            ->assertSee('2 accounts start the year on a different balance', false)
            ->assertDontSee('printed under a different name', false)
            ->assertSee('Student Control balance');
        $this->import($this->fixture()->render());

        $this->actingAs($this->treasurer->user)->get('/treasurer/ledger/review?year=2021')->assertOk()
            ->assertSee('Opening balance differs from last year (2)')->assertSee('Possible reused codes (0)')->assertSee('Student Control balance:');
    }

    // --- Matching ------------------------------------------------------------------------------------------

    public function test_accounts_match_by_code_only_and_the_control_account_never(): void
    {
        $this->import($this->fixture()->render());
        $saw = $this->pupil('Saw Example Student', $this->levelSection('Grade 5-A', '5'));
        $ko = $this->pupil('Ko Same Name', $this->levelSection('Grade 6-A', '6'));

        // Suggestions only: nothing is linked by name, and two accounts with one name stay separate.
        $this->actingAs($this->treasurer->user)->get('/treasurer/ledger/review?year=2021')->assertOk()
            ->assertSee('Accounts to match (4)')->assertSee('Link to Saw Example Student');
        $this->assertSame(0, SunAccountLink::count());

        $control = SunAccount::where('code', '1000285005')->sole();
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/accounts/{$control->id}/link", ['student_id_number' => $saw->student_id_number])
            ->assertSessionHas('warning', 'The Student Control account is never linked to a student.');

        $a = SunAccount::where('code', '1000286200')->sole();
        $b = SunAccount::where('code', '1000286300')->sole();
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/accounts/{$a->id}/link", ['student_id_number' => $ko->student_id_number])->assertSessionHas('status');
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/accounts/{$b->id}/link", ['student_id_number' => $ko->student_id_number])
            ->assertSessionHas('warning', 'Ko Same Name is already linked to account 1000286200.');
        $this->assertSame(0, SunAccountLink::where('sun_account_id', $b->id)->count());

        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/accounts/{$b->id}/not-isms", ['not_isms' => 1])->assertSessionHas('status');
        $this->actingAs($this->treasurer->user)->get('/treasurer/ledger/review?year=2021')->assertSee('Accounts to match (2)');

        // An open link covers later years; unlinking is refused once its lines are published.
        $batch = $this->import($this->fixture(2022)->render());
        $this->assertSame($ko->id, app(SunLinks::class)->studentFor($a->id, 2022));
        $this->assertSame(1, $batch->matched_accounts);
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$batch->id}/publish")->assertSessionHas('status');
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/accounts/{$a->id}/unlink")
            ->assertSessionHas('warning', 'This account has lines in a published import. Unpublish or revert that import before unlinking.');
        $this->assertSame(['Linked ledger account', 'Marked ledger account not an ISMS student'],
            AuditLog::where('entity_type', 'SunAccount')->orderBy('id')->pluck('action')->all());
    }

    // --- Fee schedule check ----------------------------------------------------------------------------------

    public function test_charges_are_checked_against_the_schedule_only_when_the_year_has_one(): void
    {
        // 2020-2021 has no schedule: reported once, nothing flagged.
        $this->import($this->fixture()->render());
        $this->actingAs($this->treasurer->user)->get('/treasurer/ledger/review?year=2021')->assertOk()
            ->assertSee("No fee schedule for 2020-2021, so this year's charges aren't checked against one.", false);

        // 2026-2027 has one (Grade 9: tuition 2,600,000 in 4 of 650,000; yearly fee 300,000).
        FeeSheet2026::load();
        $nine = $this->levelSection('Grade 9-A', '9');
        $onTime = $this->pupil('Thiri Example', $nine);
        $joinedLate = $this->pupil('Zaw Example', $nine);
        $overcharged = $this->pupil('Hnin Example', $nine);
        $this->import((new ListingWriter(2027, 30))
            ->account('1000291000', 'Thiri Example', '0.00', [[1, '300000.00', 'Charging yearly fee'], [1, '650000.00', 'Charging tuition fee 1st instalment'],
                [4, '650000.00', 'Charging tuition fee 2nd instalment'], [7, '650000.00', 'Charging tuition fee 3rd instalment'], [10, '650000.00', 'Charging tuition fee 4th instalment']])
            ->account('1000291100', 'Zaw Example', '0.00', [[1, '300000.00', 'Charging yearly fee'], [7, '650000.00', 'Charging tuition fee 3rd instalment'], [10, '650000.00', 'Charging tuition fee 4th instalment']])
            ->account('1000291200', 'Hnin Example', '0.00', [[1, '350000.00', 'Charging yearly fee'], [1, '2600000.00', 'Charging tuition fee for the year'], [2, '25000.00', 'Charging refundable deposit']])
            ->render());
        foreach (['1000291000' => $onTime, '1000291100' => $joinedLate, '1000291200' => $overcharged] as $code => $student) {
            $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/accounts/'.SunAccount::where('code', $code)->value('id').'/link', ['student_id_number' => $student->student_id_number]);
        }

        $page = $this->actingAs($this->treasurer->user)->get('/treasurer/ledger/review?year=2027')->assertOk();
        $page->assertSee('Hnin Example')->assertSee('Yearly fee: charged 350,000 Ks, schedule 300,000 Ks')->assertSee('Refundable deposit: charged 25,000 Ks, schedule 20,000 Ks');
        $page->assertDontSee('Thiri Example</a>', false)->assertDontSee('Zaw Example</a>', false); // full year, and two instalments after joining late
        $this->assertNotNull(FeeSchedule::first());
    }

    // --- Access and privacy ----------------------------------------------------------------------------------

    public function test_only_the_treasurer_imports_and_the_treasurer_and_admin_download(): void
    {
        $batch = $this->import($this->fixture()->render());
        $this->actingAs($this->treasurer->user)->get("/treasurer/ledger/{$batch->id}/download")->assertOk();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $this->actingAs($admin->user)->get("/admin/ledger-files/{$batch->id}")->assertOk();
        $this->actingAs($admin->user)->get('/admin/backup')->assertOk()->assertSee('Student GL 2020-2021.PRN');

        foreach ([['teacher', 'Teacher'], ['principal', 'Principal'], ['registrar', 'Registrar']] as [$role, $type]) {
            $user = $this->makeStaff($role, $type, "{$role}@test.local")->user;
            $this->actingAs($user)->get('/treasurer/ledger')->assertForbidden();
            $this->actingAs($user)->get("/treasurer/ledger/{$batch->id}/download")->assertForbidden();
            $this->actingAs($user)->get("/admin/ledger-files/{$batch->id}")->assertForbidden();
            $this->actingAs($user)->post('/treasurer/ledger/upload', ['file' => UploadedFile::fake()->createWithContent('x.prn', 'x')])->assertForbidden();
        }
        foreach (['guardian', 'student'] as $role) {
            $user = User::create(['name' => "Test {$role}", 'email' => "{$role}@test.local", 'password' => bcrypt('password'), 'status' => 'Active']);
            $user->assignRole($role);
            $this->actingAs($user)->get('/treasurer/ledger/review')->assertForbidden();
            $this->actingAs($user)->get("/treasurer/ledger/{$batch->id}/download")->assertForbidden();
        }
    }

    public function test_nothing_from_the_file_reaches_the_log(): void
    {
        $log = storage_path('logs/laravel.log');
        $before = is_file($log) ? filesize($log) : 0;

        $this->import($this->fixture()->render());
        $this->upload($this->fixture()->render(['balance' => '1000286000#2']));
        $token = $this->token($this->upload($this->fixture()->render()));
        $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/cancel', ['token' => $token]);

        $after = is_file($log) ? (string) file_get_contents($log, false, null, $before) : '';
        foreach (['Saw Example Student', 'Naw Example Girl', 'edu allow', '52,500'] as $text) {
            $this->assertStringNotContainsString($text, $after);
        }
        $this->assertStringNotContainsString('Saw Example', json_encode(AuditLog::pluck('details')));
    }

    public function test_erasing_a_student_clears_their_ledger_names_memos_reviews_and_statements(): void
    {
        $batch = $this->import($this->fixture()->render());
        $student = $this->pupil('Saw Example Student', $this->levelSection('Grade 5-A', '5'));
        $account = SunAccount::where('code', '1000286000')->sole();
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/accounts/{$account->id}/link", ['student_id_number' => $student->student_id_number]);
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$batch->id}/publish")->assertSessionHas('status');
        // The next year prints the code under another name: a review holds real names too.
        $this->import(str_replace('Saw Example Student', 'Saw Another Person ', $this->fixture(2022)->render()));
        $this->assertSame(1, SunAccountReview::count());
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        app(RetentionService::class)->eraseStudent($student, 'Erasure request', $admin->user);

        $erased = "Erased Record {$student->id}";
        $this->assertSame($erased, $account->fresh()->name_as_printed);
        $this->assertSame([$erased], SunAccountBalance::where('sun_account_id', $account->id)->distinct()->pluck('account_name_as_printed')->all());
        $this->assertSame([$erased, $erased], array_values(SunAccountReview::sole()->only(['previous_name', 'printed_name'])));
        $this->assertSame($erased, SunAccountLink::sole()->accepted_name);
        $this->assertSame(0, SunLedgerLine::where('sun_account_id', $account->id)->where('memo', '!=', '')->count());
        $content = json_encode(SunFamilyStatement::where('sun_account_id', $account->id)->pluck('content'));
        $this->assertStringNotContainsString('tuition fee for Dec 2020', $content);
        $this->assertStringContainsString('52500.00', $content); // amounts stay
        $this->assertSame('52500.00', (string) SunAccountBalance::where('sun_account_id', $account->id)->where('fiscal_year', 2021)->value('closing_balance'));
    }
}
