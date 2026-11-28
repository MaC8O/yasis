<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Services\Sun\AccountListingParser;
use App\Services\Sun\SunLedgerImporter;
use App\Support\FeeVisibility;
use App\Support\Sun\ListingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\BuildsSunLedger;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 8b-2: the Treasurer's dashboard and Fee Reports on the Sun ledger, the retired CSV
 * pages, the Visibility Rules page, and the synthetic listing writer the demo seeder uses.
 */
class SunFeePagesTest extends TestCase
{
    use BuildsResults, BuildsSunLedger, RefreshDatabase, SeedsCoreData;

    private StaffProfile $treasurer;

    private Student $saw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->treasurer = $this->makeStaff('treasurer', 'Treasurer', 'treasurer@test.local');
        Storage::fake(SunLedgerImporter::DISK);
        $this->saw = $this->pupil('Saw Example Student', $this->levelSection('Grade 5-A', '5'));
    }

    public function test_the_treasurer_dashboard_and_fee_reports_read_the_live_ledger(): void
    {
        $this->importListing($this->ledger(2021)->render()); // not published: the Treasurer sees it anyway
        $this->linkCode('1000286000', $this->saw);

        $this->actingAs($this->treasurer->user)->get('/treasurer/dashboard')->assertOk()
            ->assertSee('2020-2021')->assertSee('22,000')->assertSee('Accounts to match')->assertSee('Imports not yet published');
        $this->actingAs($this->treasurer->user)->get('/treasurer/reports?year=2021')->assertOk()
            ->assertSee('By grade (class this year)')->assertSee('Grade 5')
            ->assertSee('data-by-month', false)->assertSee('Dec 2020')->assertSee('52,500')
            ->assertSee('data-control-history', false)->assertSee('Student Control balance at each import');

        $csv = $this->actingAs($this->treasurer->user)->get('/treasurer/reports/outstanding?year=2021')->assertOk()->streamedContent();
        $this->assertStringContainsString('Saw Example Student', $csv);
        $this->assertStringContainsString('22000.00', $csv);
        $this->assertSame(1, AuditLog::where('action', 'Generated outstanding balance report')->count());

        $this->actingAs($this->treasurer->user)->get('/treasurer/reports/find-statement?student='.$this->saw->student_id_number)
            ->assertRedirectContains('/treasurer/ledger/accounts/');
    }

    public function test_the_retired_csv_pages_redirect_to_the_ledger_and_the_upload_is_gone(): void
    {
        foreach (['/treasurer/import' => '/treasurer/ledger', '/treasurer/validate' => '/treasurer/ledger/review',
            '/treasurer/records' => '/treasurer/ledger/accounts', '/treasurer/history' => '/treasurer/ledger',
            '/treasurer/import-template' => '/treasurer/info/source-prep', '/treasurer/reports/aging' => '/treasurer/reports'] as $from => $to) {
            $this->actingAs($this->treasurer->user)->get($from)->assertRedirect($to);
        }
        $this->actingAs($this->treasurer->user)->get("/treasurer/records/{$this->saw->id}")
            ->assertRedirect('/treasurer/ledger/accounts?search='.$this->saw->student_id_number);
        // The old upload address only redirects now: nothing can be imported through it.
        $this->actingAs($this->treasurer->user)->post('/treasurer/import', ['period' => 'Q1'])->assertRedirect('/treasurer/ledger');
        $this->assertSame(0, \App\Models\ImportBatch::count());
        $this->actingAs($this->treasurer->user)->get('/treasurer/dashboard')->assertOk()->assertDontSee('Import Records')->assertDontSee('Validate &amp; Match', false);
    }

    public function test_the_visibility_rules_page_describes_the_ledger_and_keeps_the_leadership_switch(): void
    {
        $this->actingAs($this->treasurer->user)->get('/treasurer/info/visibility-rules')->assertOk()
            ->assertSee('Every line, allowances included (T4)')->assertSee('Show them to leadership, marked');
        $this->actingAs($this->treasurer->user)->put('/treasurer/info/visibility-rules/policy', ['leadership_sees_restricted' => 1])->assertSessionHas('status');
        $this->assertTrue(FeeVisibility::leadershipSeesRestricted());
        $this->actingAs($this->treasurer->user)->get('/treasurer/info/source-prep')->assertOk()->assertSee('Account Listing');

        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $html = $this->actingAs($admin->user)->get('/admin/backup')->assertOk()->getContent();
        $this->assertStringContainsString('Data retention &amp; erasure', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_the_listing_writer_round_trips_through_the_parser(): void
    {
        $text = (new ListingWriter(2027, 20))
            ->account('1000290010', 'Invented Student One', '0.00', [[3, '300000.00', 'Charging yearly fee'], [3, '650000.00', 'Charging tuition fee'], [4, '-950000.00', 'Payment', 'CR']])
            ->account('1000290020', 'Invented Student Two', '-15000.00', [[3, '15000.00', 'Charging school ferry fee']])
            ->account('1000290030', 'Invented Student Three', '5000.00', [], openingLine: true)
            ->render();
        $r = app(AccountListingParser::class)->parse($text);

        $this->assertTrue($r['ok'], json_encode($r['errors']));
        $this->assertGreaterThan(1, substr_count($text, 'ACCOUNT LISTING'), 'pages break');
        // Four accounts with Student Control, whose balancing line makes five lines.
        $this->assertSame([4, 5, 2027, '0.00'], [count($r['accounts']), $r['line_count'], $r['header']['fiscal_year'], $r['closing_sum']]);
        $this->assertSame(['0.00', '0.00', '5000.00', '-5000.00'],
            [$r['accounts']['1000290010']['closing'], $r['accounts']['1000290020']['closing'], $r['accounts']['1000290030']['closing'], $r['accounts']['1000285005']['closing']]);
    }
}
