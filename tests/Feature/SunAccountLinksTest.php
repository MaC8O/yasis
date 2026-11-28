<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\SunAccount;
use App\Models\SunAccountLink;
use App\Models\SunAccountReview;
use App\Models\SunFamilyStatement;
use App\Models\SunImportBatch;
use App\Services\Sun\SunLedgerImporter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\BuildsSunLedger;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 8b-2, T3 (the Treasurer: a code can pass to another student after one leaves): links
 * cover ranges of financial years, and a linked code printed under another name is held until
 * the Treasurer says whether it is the same student. Invented names only.
 */
class SunAccountLinksTest extends TestCase
{
    use BuildsResults, BuildsSunLedger, RefreshDatabase, SeedsCoreData;

    private StaffProfile $treasurer;

    private Student $first;

    private Student $second;

    private SunImportBatch $batch2021;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->treasurer = $this->makeStaff('treasurer', 'Treasurer', 'treasurer@test.local');
        Storage::fake(SunLedgerImporter::DISK);

        $this->first = $this->pupil('Saw Example Student', $this->levelSection('Grade 5-A', '5'));
        $this->second = $this->pupil('Thiri New Pupil', $this->levelSection('Grade 6-A', '6'));
        $this->batch2021 = $this->importListing($this->ledger(2021)->render());
        $this->linkCode('1000286000', $this->first);
        $this->publish($this->batch2021);
    }

    private function account(): SunAccount
    {
        return SunAccount::where('code', '1000286000')->sole();
    }

    public function test_a_renamed_code_is_held_from_every_family_and_only_the_treasurer_sees_it(): void
    {
        $this->publish($this->importListing($this->ledger(2022, ['1000286000' => 'Thiri New Pupil'])->render()));
        $review = SunAccountReview::sole();
        $this->assertSame([SunAccountReview::PENDING, 2022, 'Saw Example Student', 'Thiri New Pupil'],
            [$review->status, $review->fiscal_year, $review->previous_name, $review->printed_name]);
        $this->assertNull(SunFamilyStatement::where('fiscal_year', 2022)->where('sun_account_id', $this->account()->id)->value('student_id'));

        // Neither the old student's family, nor any other, sees the held year.
        $this->guardianFees($this->familyOf($this->first, 'first@test.local'), $this->first)
            ->assertSee('data-statement="2020-2021"', false)->assertDontSee('data-statement="2021-2022"', false);
        $this->guardianFees($this->familyOf($this->second, 'second@test.local'), $this->second)
            ->assertSee('No fee records have been published yet')->assertDontSee('Charging tuition fee for Dec 2020');
        $principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');
        $this->actingAs($principal->user)->get('/principal/fees')->assertOk()->assertDontSee('Saw Example Student');
        $this->actingAs($principal->user)->get("/principal/fees/{$this->first->id}")->assertOk()->assertDontSee('data-statement="2021-2022"', false);

        // Only the Treasurer sees it, on Ledger Review and the account page.
        $this->actingAs($this->treasurer->user)->get('/treasurer/ledger/review?year=2022')->assertOk()
            ->assertSee('Possible reused codes (1)')->assertSee('Thiri New Pupil')->assertSee('Same student (the name was corrected)');
        $this->actingAs($this->treasurer->user)->get('/treasurer/ledger/accounts/'.$this->account()->id.'?year=2022')->assertOk()->assertSee('data-held', false);
    }

    public function test_same_student_records_the_accepted_name_so_a_reimport_does_not_ask_again(): void
    {
        $text = $this->ledger(2022, ['1000286000' => 'Saw Example Studnt'])->render();
        $this->publish($this->importListing($text));
        $review = SunAccountReview::sole();

        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/reviews/{$review->id}/same")->assertSessionHas('status');
        $this->assertSame([SunAccountReview::SAME, 'Saw Example Studnt'], [$review->fresh()->status, SunAccountLink::sole()->accepted_name]);
        $this->assertSame(1, AuditLog::where('action', 'Confirmed ledger account is the same student')->count());
        $guardian = $this->familyOf($this->first, 'first@test.local');
        $this->guardianFees($guardian, $this->first)->assertSee('data-statement="2021-2022"', false);

        // Re-importing the same year with the accepted name opens nothing.
        $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/upload', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('again.PRN', $text)])
            ->assertOk()->assertDontSee('printed under a different name', false);
        $this->importListing($text);
        $this->assertSame(0, SunAccountReview::pending()->count());
    }

    public function test_spacing_and_case_alone_do_not_open_a_review(): void
    {
        $this->publish($this->importListing($this->ledger(2022, ['1000286000' => '  SAW   example  STUDENT'])->render()));

        $this->assertSame(0, SunAccountReview::count());
        $this->guardianFees($this->familyOf($this->first, 'first@test.local'), $this->first)->assertSee('data-statement="2021-2022"', false);
    }

    public function test_a_different_student_ends_the_old_link_and_links_the_new_one(): void
    {
        $this->publish($this->importListing($this->ledger(2022, ['1000286000' => 'Thiri New Pupil'])->render()));
        $review = SunAccountReview::sole();

        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/reviews/{$review->id}/relink", ['student_id_number' => $this->second->student_id_number])
            ->assertSessionHas('status');

        $links = SunAccountLink::orderBy('id')->get();
        $this->assertSame([[$this->first->id, 2021, 2021], [$this->second->id, 2022, null]],
            $links->map(fn ($l) => [$l->student_id, $l->from_fiscal_year, $l->to_fiscal_year])->all());
        $this->assertSame(SunAccountReview::RELINKED, $review->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'Linked reused ledger account to another student')->count());

        $this->guardianFees($this->familyOf($this->first, 'first@test.local'), $this->first)
            ->assertSee('data-statement="2020-2021"', false)->assertDontSee('data-statement="2021-2022"', false);
        $this->guardianFees($this->familyOf($this->second, 'second@test.local'), $this->second)
            ->assertSee('data-statement="2021-2022"', false)->assertDontSee('data-statement="2020-2021"', false);
    }

    public function test_one_open_link_per_code_and_per_student_is_enforced_by_the_database(): void
    {
        $base = ['accepted_name' => 'x', 'from_fiscal_year' => 2021, 'created_at' => now(), 'updated_at' => now()];
        $other = SunAccount::where('code', '1000286100')->sole();

        try {
            SunAccountLink::insert($base + ['sun_account_id' => $this->account()->id, 'student_id' => $this->second->id]);
            $this->fail('a second open link for one code was saved');
        } catch (QueryException) {
        }
        try {
            SunAccountLink::insert($base + ['sun_account_id' => $other->id, 'student_id' => $this->first->id]);
            $this->fail('a second open link for one student was saved');
        } catch (QueryException) {
        }

        // An ended link frees both: the code and the student can each have a new open link.
        SunAccountLink::sole()->update(['to_fiscal_year' => 2021]);
        SunAccountLink::insert($base + ['sun_account_id' => $other->id, 'student_id' => $this->first->id, 'from_fiscal_year' => 2022]);
        $this->assertSame(2, SunAccountLink::count());
    }

    public function test_unlinking_voids_the_link_keeps_it_and_takes_the_statements_away(): void
    {
        $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/accounts/'.$this->account()->id.'/unlink')
            ->assertSessionHas('warning', 'This account has lines in a published import. Unpublish or revert that import before unlinking.');

        $this->actingAs($this->treasurer->user)->post("/treasurer/ledger/{$this->batch2021->id}/unpublish")->assertSessionHas('status');
        $this->actingAs($this->treasurer->user)->post('/treasurer/ledger/accounts/'.$this->account()->id.'/unlink')->assertSessionHas('status');

        $this->assertNotNull(SunAccountLink::sole()->voided_at);
        $this->assertSame(0, SunFamilyStatement::whereNotNull('student_id')->count());
    }
}
