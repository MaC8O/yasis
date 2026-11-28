<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\SunAccountBalance;
use App\Models\SunFamilyStatement;
use App\Models\SunImportBatch;
use App\Models\SunLedgerLine;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Sun\SunLedgerImporter;
use App\Support\FeeVisibility;
use App\Support\Sun\ListingWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\BuildsSunLedger;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 8b-2: what families see of the Sun ledger. Statements are built when an import is
 * published; families see every line (T4), lines hidden by hand only as "Adjustments"; an
 * unpublished import changes nothing for them. Invented names only.
 */
class SunFamilyStatementsTest extends TestCase
{
    use BuildsResults, BuildsSunLedger, RefreshDatabase, SeedsCoreData;

    private StaffProfile $treasurer;

    private Student $saw;

    private Student $naw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->treasurer = $this->makeStaff('treasurer', 'Treasurer', 'treasurer@test.local');
        Storage::fake(SunLedgerImporter::DISK);
        $this->saw = $this->pupil('Saw Example Student', $this->levelSection('Grade 5-A', '5'));
        $this->naw = $this->pupil('Naw Example Girl', $this->levelSection('Grade 6-A', '6'));
    }

    private function publishedLedger(): void
    {
        $batch = $this->importListing($this->ledger(2021)->render());
        $this->linkCode('1000286000', $this->saw);
        $this->linkCode('1000286100', $this->naw);
        $this->publish($batch);
    }

    private function hideCorrection(): void
    {
        $line = SunLedgerLine::where('memo', 'Internal correction of a posting error')->sole();
        $this->actingAs($this->treasurer->user)->put("/treasurer/ledger/lines/{$line->id}", ['restricted' => 1])->assertSessionHas('status');
    }

    public function test_every_statement_adds_up_to_the_ledger(): void
    {
        $this->publishedLedger();
        $this->hideCorrection();

        $statements = SunFamilyStatement::all();
        $this->assertCount(2, $statements); // the control account gets none
        foreach ($statements as $statement) {
            $c = $statement->content;
            $sum = BigDecimal::of($c['opening'])->plus($c['adjustments']);
            foreach ($c['lines'] as $line) {
                $sum = $sum->plus($line['amount']);
            }
            $closing = SunAccountBalance::where('sun_account_id', $statement->sun_account_id)->where('fiscal_year', 2021)->value('closing_balance');
            $this->assertTrue($sum->isEqualTo((string) $closing), "account {$statement->sun_account_id}");
            $this->assertSame((string) $closing, (string) $statement->closing_balance);
        }
    }

    public function test_families_see_every_line_and_a_hand_hidden_line_only_as_adjustments(): void
    {
        $this->publishedLedger();
        $this->hideCorrection();
        $guardian = $this->familyOf($this->saw, 'saw.parent@test.local');

        $page = $this->guardianFees($guardian, $this->saw);
        $page->assertSee('Providing 20% edu allow on tuition fee for Dec 2020')  // T4: allowances shown
            ->assertSee('Payment monthly fee for Dec 2020')
            ->assertSee('data-adjustments', false)->assertSee('3,000.00 Cr')
            ->assertSee('22,000.00')->assertSee('Owing')
            ->assertDontSee('Internal correction')->assertDontSee('hidden from families');

        // Leadership: "Adjustments" by default; with the switch on, the line itself, marked.
        $principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');
        $this->actingAs($principal->user)->get("/principal/fees/{$this->saw->id}")->assertOk()->assertSee('data-adjustments', false)->assertDontSee('Internal correction');
        SystemSetting::set(FeeVisibility::LEADERSHIP_SEES_RESTRICTED, '1');
        $this->actingAs($principal->user)->get("/principal/fees/{$this->saw->id}")->assertOk()->assertSee('Internal correction')->assertSee('hidden from families');

        // Showing it again moves it back for families.
        $line = SunLedgerLine::where('memo', 'Internal correction of a posting error')->sole();
        $this->actingAs($this->treasurer->user)->put("/treasurer/ledger/lines/{$line->id}", ['restricted' => 0]);
        $this->guardianFees($guardian, $this->saw)->assertSee('Internal correction')->assertDontSee('data-adjustments', false);
    }

    /** The same ledger with Naw's payment corrected to 300,000 (so she is settled, not in credit). */
    private function correctedLedger(): string
    {
        return (new ListingWriter(2021, 30))
            ->account('1000286000', 'Saw Example Student', '25000.00', [
                [9, '52500.00', 'Charging tuition fee for Dec 2020'], [9, '-10500.00', 'Providing 20% edu allow on tuition fee for Dec 2020'],
                [10, '-42000.00', 'Payment monthly fee for Dec 2020', 'CR'], [11, '-3000.00', 'Internal correction of a posting error'],
            ])
            ->account('1000286100', 'Naw Example Girl', '0.00', [[1, '300000.00', 'Charging for 2020-2021 yearly fees'], [2, '-300000.00', 'Payment yearly fee', 'CR']])
            ->render();
    }

    public function test_an_unpublished_import_changes_nothing_for_families_and_unpublish_or_revert_falls_back(): void
    {
        $this->publishedLedger();
        $guardian = $this->familyOf($this->naw, 'naw.parent@test.local');
        $this->guardianFees($guardian, $this->naw)->assertSee('50,000.00 Cr')->assertSee('In credit');

        // Sun corrected the payment: imported but not published, families see no change.
        $newer = $this->importListing($this->correctedLedger());
        $this->guardianFees($guardian, $this->naw)->assertSee('50,000.00 Cr');

        $this->publish($newer);
        $this->guardianFees($guardian, $this->naw)->assertSee('Settled')->assertDontSee('50,000.00 Cr');

        // Unpublishing the newer import falls back to the older published statement; so does reverting it.
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$newer->id}/unpublish")->assertSessionHas('status');
        $this->guardianFees($guardian, $this->naw)->assertSee('50,000.00 Cr');
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$newer->id}/revert")->assertSessionHas('status');
        $this->guardianFees($guardian, $this->naw)->assertSee('50,000.00 Cr');
    }

    public function test_an_older_import_cannot_be_published_over_a_newer_one(): void
    {
        $this->publishedLedger();
        $older = SunImportBatch::orderBy('id')->first();
        $newer = $this->importListing($this->correctedLedger());

        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$older->id}/unpublish");
        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$older->id}/publish")
            ->assertSessionHas('warning', "Import #{$newer->id} is newer for 2020-2021. Publish that one instead.");
    }

    public function test_the_student_sees_their_own_fees_and_the_statement_pdf_and_the_guardian_dashboard_tile(): void
    {
        $this->publishedLedger();
        $user = User::create(['name' => 'Saw Example Student', 'email' => 'saw@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $user->assignRole('student');
        $this->saw->update(['user_id' => $user->id]);

        $this->actingAs($user)->get('/student/fees')->assertOk()
            ->assertSee('data-statement="2020-2021"', false)->assertSee('Providing 20% edu allow')->assertDontSee('Payment yearly fee'); // not Naw's

        $html = null;
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $data) use (&$html) {
            $html = view($view, $data)->render();
            $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            $pdf->shouldReceive('stream')->andReturn(response('%PDF', 200, ['Content-Type' => 'application/pdf']));

            return $pdf;
        });
        $this->actingAs($user)->get('/student/fees/statement')->assertOk();
        $this->assertStringContainsString('Financial year 2020-2021', $html);
        $this->assertStringContainsString('Providing 20% edu allow', $html);

        $guardian = $this->familyOf($this->saw, 'saw.parent@test.local');
        $this->actingAs($guardian)->get("/guardian/dashboard?child={$this->saw->id}")->assertOk()->assertSeeInOrder(['Fee status', 'Owing']);
        $this->actingAs($user)->get('/guardian/fees')->assertForbidden();
    }

    public function test_leadership_pages_and_the_principal_dashboard_read_the_published_statements(): void
    {
        $this->publishedLedger();
        foreach (['principal' => 'Principal', 'vp_academic' => 'VP_Academic', 'registrar' => 'Registrar'] as $role => $type) {
            $staff = $this->makeStaff($role, $type, "{$role}@test.local");
            $this->actingAs($staff->user)->get("/{$role}/fees")->assertOk()
                ->assertSee('Saw Example Student')->assertSee('Naw Example Girl')->assertSee('22,000.00')->assertSee('50,000.00 Cr');
            $this->actingAs($staff->user)->get("/{$role}/fees?status=Owing")->assertOk()->assertSee('Saw Example Student')->assertDontSee('Naw Example Girl');
        }
        $principal = StaffProfile::where('role_type', 'Principal')->sole();
        $page = $this->actingAs($principal->user)->get('/principal/dashboard')->assertOk()->assertSee('Students owing fees')->getContent();
        $this->assertMatchesRegularExpression('/Owed to the school \(Ks\).*?22,000/s', $page);

        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $this->actingAs($teacher->user)->get('/principal/fees')->assertForbidden();
    }
}
