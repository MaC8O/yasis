<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PromotionBatch;
use App\Models\PromotionBatchItem;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Services\ResultFreezeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Section 9: the Registrar sees a calculated recommendation next to each decision. A different
 * decision needs a reason, and the batch and the audit log keep both.
 */
class PromotionRecommendationTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $registrar;

    private Section $section;

    private Student $passing;

    private Student $failing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->registrar = $this->makeStaff('registrar', 'Registrar');
        $this->section = $this->levelSection('Grade 2-A', '2');
        $this->passing = $this->pupil('Passing Pupil', $this->section);
        $this->failing = $this->pupil('Failing Pupil', $this->section);
        foreach ($this->terms as $term) {
            $this->setMarks($this->passing, $this->section, $term, self::KG_EXAMPLE);
            $this->setMarks($this->failing, $this->section, $term, array_merge(self::KG_EXAMPLE, ['MATH' => 20]));
        }
    }

    private function store(array $actions)
    {
        return $this->actingAs($this->registrar->user)->post("/registrar/promotions/{$this->section->id}", ['actions' => $actions]);
    }

    public function test_the_registrar_sees_each_students_recommendation(): void
    {
        $this->actingAs($this->registrar->user)->get("/registrar/promotions/{$this->section->id}/create")->assertOk()
            ->assertSee('Recommendation')
            ->assertSeeInOrder(['Failing Pupil', 'Retain', 'Average 85', 'Fail', 'provisional'])
            ->assertSeeInOrder(['Passing Pupil', 'Promote', 'Average 90', 'First', 'provisional'])
            ->assertSee('<option value="Retain" selected>', false);
    }

    public function test_a_decision_that_follows_the_recommendation_needs_no_reason(): void
    {
        $this->store([
            ['student_id' => $this->passing->id, 'action' => 'Promote'],
            ['student_id' => $this->failing->id, 'action' => 'Retain'],
        ])->assertSessionHasNoErrors()->assertRedirect('/registrar/promotions');

        $item = PromotionBatchItem::where('student_id', $this->passing->id)->sole();
        $this->assertSame(['Promote', 'Promote', null], [$item->action, $item->recommended_action, $item->override_reason]);
        $this->assertStringContainsString('Average 90', $item->recommendation_basis);
    }

    public function test_a_different_decision_needs_a_reason_and_the_audit_keeps_both(): void
    {
        $this->store([
            ['student_id' => $this->passing->id, 'action' => 'Promote'],
            ['student_id' => $this->failing->id, 'action' => 'Promote', 'override_reason' => ''],
        ])->assertSessionHasErrors('actions.1.override_reason');
        $this->assertSame(0, PromotionBatch::count());

        $this->store([
            ['student_id' => $this->passing->id, 'action' => 'Promote'],
            ['student_id' => $this->failing->id, 'action' => 'Promote', 'override_reason' => 'Passed the Math re-sit in June.'],
        ])->assertSessionHasNoErrors();

        $item = PromotionBatchItem::where('student_id', $this->failing->id)->sole();
        $this->assertSame(['Promote', 'Retain', 'Passed the Math re-sit in June.'], [$item->action, $item->recommended_action, $item->override_reason]);

        $log = AuditLog::where('action', 'Prepared promotion batch')->sole();
        $this->assertSame([[
            'student_id' => $this->failing->id, 'recommended' => 'Retain', 'decision' => 'Promote', 'reason' => 'Passed the Math re-sit in June.',
        ]], $log->details['overrides']);
    }

    public function test_the_vp_and_principal_see_the_overrides_on_the_batch(): void
    {
        $this->store([
            ['student_id' => $this->passing->id, 'action' => 'Promote'],
            ['student_id' => $this->failing->id, 'action' => 'Promote', 'override_reason' => 'Passed the Math re-sit in June.'],
        ]);
        $vp = $this->makeStaff('vp_academic', 'VP Academic');

        $this->actingAs($vp->user)->get('/vp_academic/approvals')->assertOk()
            ->assertSee('1 decision differs from the recommendation')
            ->assertSee('Failing Pupil: Promote (recommended Retain). Passed the Math re-sit in June.');
    }

    public function test_a_frozen_year_is_not_marked_provisional(): void
    {
        $principal = $this->makeStaff('principal', 'Principal');
        foreach ($this->terms as $term) {
            app(ResultFreezeService::class)->lock($term, $principal->user);
        }

        $this->actingAs($this->registrar->user)->get("/registrar/promotions/{$this->section->id}/create")->assertOk()
            ->assertSee('Average 90')
            ->assertDontSee('provisional');
    }

    public function test_a_student_without_a_result_gets_no_recommendation_and_any_decision_is_accepted(): void
    {
        $late = $this->pupil('Late Joiner', $this->section);
        $this->setMarks($late, $this->section, $this->terms[0], self::KG_EXAMPLE);

        $this->actingAs($this->registrar->user)->get("/registrar/promotions/{$this->section->id}/create")->assertOk()
            ->assertSeeInOrder(['Late Joiner', 'No recommendation']);

        $this->store([['student_id' => $late->id, 'action' => 'Retain']])->assertSessionHasNoErrors();
        $this->assertNull(PromotionBatchItem::where('student_id', $late->id)->sole()->recommended_action);
    }
}
